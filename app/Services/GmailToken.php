<?php

namespace App\Services;

use App\Models\GmailMailbox;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GmailToken
{
    private ?string $cachedToken = null;

    private ?int $mailboxId = null;

    private int $expiresAt = 0;

    public function accessToken(GmailMailbox $mailbox): string
    {
        if ($mailbox->refresh_token === '') {
            throw new RuntimeException('Gmail is disconnected. Reconnect the mailbox.');
        }
        if ($this->mailboxId === $mailbox->id && $this->cachedToken !== null && time() + 60 < $this->expiresAt) {
            return $this->cachedToken;
        }
        $response = Http::asForm()->timeout(15)->post('https://oauth2.googleapis.com/token', [
            'client_id' => config('services.gmail.client_id'),
            'client_secret' => config('services.gmail.client_secret'),
            'refresh_token' => $mailbox->refresh_token,
            'grant_type' => 'refresh_token',
        ]);

        $token = $response->json('access_token');
        if ($response->failed() || ! is_string($token) || $token === '') {
            throw new RuntimeException('Gmail authorization failed. Reconnect the mailbox.');
        }

        $this->mailboxId = $mailbox->id;
        $this->cachedToken = $token;
        $this->expiresAt = time() + max(0, (int) $response->json('expires_in', 0));

        return $token;
    }
}
