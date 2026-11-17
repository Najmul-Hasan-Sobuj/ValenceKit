<?php

namespace App\Services;

use App\Models\ContactMessage;
use App\Models\ContactReply;
use App\Models\GmailMailbox;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class GmailSync
{
    public function __construct(private GmailToken $tokens, private GmailImap $imap, private GmailMessageParser $parser, private GmailSender $sender) {}

    public function sync(GmailMailbox $mailbox): void
    {
        if ($mailbox->refresh_token === '') {
            return;
        }
        Cache::lock('gmail-contact-sync', 600)->block(2, function () use ($mailbox) {
            $this->syncMailbox($mailbox);
        });
    }

    public function syncSingle(GmailMailbox $mailbox, ContactMessage $contact): void
    {
        $this->imap->connect($mailbox->email, $this->tokens->accessToken($mailbox));
        $this->syncContact($contact, $mailbox);
    }

    /** @return array{threads: list<array{id: string, uid: string, subject: string, date: string|null, imported: bool}>, next_before: string|null} */
    public function searchThreads(GmailMailbox $mailbox, string $email, ?string $before = null): array
    {
        $email = strtolower($email);
        $this->imap->connect($mailbox->email, $this->tokens->accessToken($mailbox));
        $address = addcslashes($email, '"\\');
        if ($before !== null && (int) $before <= 1) {
            return ['threads' => [], 'next_before' => null];
        }
        $range = $before === null ? '' : 'UID 1:'.((int) $before - 1).' ';
        $uids = array_reverse($this->imap->search($range.'OR OR FROM "'.$address.'" TO "'.$address.'" CC "'.$address.'"'));
        $threads = [];
        $processed = 0;
        $lastUid = null;
        foreach (array_slice($uids, 0, 40) as $uid) {
            $processed++;
            $lastUid = $uid;
            $response = $this->imap->fetch($uid, 'X-GM-THRID INTERNALDATE BODY.PEEK[HEADER.FIELDS (MESSAGE-ID FROM TO CC SUBJECT)]');
            $metadata = $response[0] ?? '';
            $message = $this->parser->parse($response[1] ?? '');
            if (! preg_match('/X-GM-THRID\s+(\d+)/i', $metadata, $match) || ! $this->belongsToClient($message, $mailbox, $email) || isset($threads[$match[1]])) {
                continue;
            }
            preg_match('/INTERNALDATE\s+"([^"]+)"/i', $metadata, $date);
            $threads[$match[1]] = [
                'id' => $match[1], 'uid' => $uid,
                'subject' => $message['subject'] ?: '(No subject)',
                'date' => $date[1] ?? null,
                'imported' => false,
            ];
            if (count($threads) === 20) {
                break;
            }
        }

        $importedIds = ContactMessage::query()->whereIn('gmail_thread_id', array_keys($threads))->pluck('gmail_thread_id')->all();
        foreach ($threads as &$thread) {
            $thread['imported'] = in_array($thread['id'], $importedIds, true);
        }
        unset($thread);

        return ['threads' => array_values($threads), 'next_before' => $processed < count($uids) ? $lastUid : null];
    }

    public function importThread(GmailMailbox $mailbox, string $email, string $threadId, string $uid): ContactMessage
    {
        return Cache::lock('gmail-import-'.$threadId, 600)->block(2, function () use ($mailbox, $email, $threadId, $uid) {
            $this->imap->connect($mailbox->email, $this->tokens->accessToken($mailbox));
            $response = $this->imap->fetch($uid, 'X-GM-THRID INTERNALDATE BODY.PEEK[HEADER.FIELDS (MESSAGE-ID FROM TO CC SUBJECT)]');
            $message = $this->parser->parse($response[1] ?? '');
            if (! preg_match('/X-GM-THRID\s+(\d+)/i', $response[0] ?? '', $match) || $match[1] !== $threadId || ! $this->belongsToClient($message, $mailbox, strtolower($email))) {
                throw ValidationException::withMessages(['thread_id' => 'This conversation does not match the selected client.']);
            }

            $contact = ContactMessage::query()->firstOrCreate(['gmail_thread_id' => $threadId], [
                'name' => $email, 'email' => strtolower($email), 'message' => '',
                'subject' => mb_substr($message['subject'], 0, 255), 'initiated_by_admin' => true,
            ]);
            if (strcasecmp($contact->email, $email) !== 0) {
                throw ValidationException::withMessages(['thread_id' => 'This conversation has already been imported for another client.']);
            }
            $this->syncContact($contact, $mailbox);

            return $contact;
        });
    }

    /** @param array{from: string, to: list<string>} $message */
    private function belongsToClient(array $message, GmailMailbox $mailbox, string $email): bool
    {
        $mailboxEmail = strtolower($mailbox->email);

        return ($message['from'] === $email && in_array($mailboxEmail, $message['to'], true))
            || ($message['from'] === $mailboxEmail && in_array($email, $message['to'], true));
    }

    private function syncMailbox(GmailMailbox $mailbox): void
    {
        ContactMessage::query()->where('initiated_by_admin', false)->whereDoesntHave('replies', fn ($query) => $query->where('notification', true))
            ->chunkById(25, function ($contacts) use ($mailbox) {
                foreach ($contacts as $contact) {
                    $this->sender->notifyIfNeeded($mailbox, $contact);
                }
            });
        $this->imap->connect($mailbox->email, $this->tokens->accessToken($mailbox));

        ContactMessage::query()->where(fn ($query) => $query->whereNotNull('gmail_thread_id')->orWhereHas('replies', fn ($query) => $query->whereNotNull('rfc_message_id')->where('incoming', false)))
            ->chunkById(25, function ($contacts) use ($mailbox) {
                foreach ($contacts as $contact) {
                    $this->syncContact($contact, $mailbox);
                }
            });
    }

    private function syncContact(ContactMessage $contact, GmailMailbox $mailbox): void
    {
        Cache::lock('gmail-thread-'.$contact->id, 600)->block(2, function () use ($contact, $mailbox) {
            $this->syncContactMessages($contact, $mailbox);
        });
    }

    private function syncContactMessages(ContactMessage $contact, GmailMailbox $mailbox): void
    {
        $threadId = $contact->gmail_thread_id;
        if (! $threadId) {
            $messageId = $contact->replies()->whereNotNull('rfc_message_id')->where('incoming', false)->oldest('id')->value('rfc_message_id');
            if (! $messageId || preg_match('/[^\x21-\x7e]|["\\\\]/', $messageId)) {
                return;
            }
            $uids = $this->imap->search('HEADER Message-ID "<'.$messageId.'>"');
            foreach ($uids as $uid) {
                $identity = $this->imap->fetch($uid, 'BODY.PEEK[HEADER.FIELDS (MESSAGE-ID FROM TO CC)]');
                $origin = $this->parser->parse($identity[1] ?? '');
                if ($origin['message_id'] !== $messageId || $origin['from'] !== strtolower($mailbox->email)) {
                    continue;
                }
                if (! in_array(strtolower($contact->email), $origin['to'], true) && ! in_array(strtolower($mailbox->email), $origin['to'], true)) {
                    continue;
                }
                $header = implode('', $this->imap->fetch($uid, 'X-GM-THRID'));
                if (preg_match('/X-GM-THRID\s+(\d+)/i', $header, $matches)) {
                    $threadId = $matches[1];
                    break;
                }
            }
            if (! $threadId) {
                return;
            }
            $contact->update(['gmail_thread_id' => $threadId]);
        }
        if (! ctype_digit($threadId)) {
            return;
        }
        $uidValidity = $this->imap->uidValidity();
        if ($uidValidity !== null && (int) $contact->gmail_uid_validity !== $uidValidity) {
            $contact->forceFill(['gmail_uid_validity' => $uidValidity, 'gmail_last_uid' => 0])->saveQuietly();
        }
        $lastUid = $uidValidity === null ? 0 : (int) $contact->gmail_last_uid;
        $criteria = 'X-GM-THRID '.$threadId.($lastUid > 0 ? ' UID '.($lastUid + 1).':*' : '');
        $uids = $this->imap->search($criteria);
        sort($uids, SORT_NUMERIC);
        foreach ($uids as $uid) {
            if (! ctype_digit($uid) || (int) $uid <= $lastUid) {
                continue;
            }
            $this->syncMessage($contact, $mailbox, $uid);
            if ($uidValidity !== null) {
                $contact->forceFill(['gmail_last_uid' => (int) $uid])->saveQuietly();
                $lastUid = (int) $uid;
            }
        }
    }

    private function syncMessage(ContactMessage $contact, GmailMailbox $mailbox, string $uid): void
    {
        $metadata = implode('', $this->imap->fetch($uid, 'X-GM-MSGID INTERNALDATE'));
        if (! preg_match('/X-GM-MSGID\s+(\d+)/i', $metadata, $matches)) {
            throw new RuntimeException('Could not read Gmail message metadata.');
        }
        $gmailId = $matches[1];
        if ($contact->replies()->where('gmail_message_id', $gmailId)->exists()) {
            return;
        }
        $response = $this->imap->fetch($uid, 'BODY.PEEK[]<0.1048576>');
        $raw = $response[1] ?? '';
        if ($raw === '') {
            throw new RuntimeException('Gmail returned an empty message.');
        }
        $message = $this->parser->parse($raw);
        if ($message['message_id'] !== '') {
            $existing = ContactReply::query()->where('rfc_message_id', $message['message_id'])->first();
            if ($existing) {
                if ((int) $existing->contact_message_id === (int) $contact->id) {
                    $existing->update(['gmail_message_id' => $gmailId]);
                }

                return;
            }
        }
        $fromMailbox = $message['from'] === strtolower($mailbox->email);
        $fromContact = $message['from'] === strtolower($contact->email);
        if (! $message['message_id'] || (! $fromMailbox && ! $fromContact)) {
            return;
        }
        if ($fromMailbox && ! in_array(strtolower($contact->email), $message['to'], true)) {
            return;
        }
        if ($fromContact && ! in_array(strtolower($mailbox->email), $message['to'], true)) {
            return;
        }
        $reply = $contact->replies()->make([
            'body' => $message['body'] !== '' ? $message['body'] : '[No readable text. View this message in Gmail.]',
            'rfc_message_id' => $message['message_id'],
            'gmail_message_id' => $gmailId,
            'incoming' => $fromContact,
        ]);
        if (preg_match('/INTERNALDATE\s+"([^"]+)"/i', $metadata, $date)) {
            try {
                $reply->created_at = Carbon::parse($date[1])->utc();
            } catch (\Throwable) {
                // Retain the sync time if Gmail returned an invalid date.
            }
        }
        $reply->save();
    }
}
