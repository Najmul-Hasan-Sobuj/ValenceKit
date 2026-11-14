<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GmailMailbox;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class GmailConnectionController extends Controller
{
    public function disconnect(Request $request): RedirectResponse
    {
        abort_unless($request->user('admin')->can('contact.manage') && $request->user('admin')->can('contact.view'), 403);
        $mailbox = GmailMailbox::query()->first();
        if ($mailbox && $mailbox->refresh_token !== '') {
            try {
                $response = Http::asForm()->connectTimeout(5)->timeout(15)->post('https://oauth2.googleapis.com/revoke', ['token' => $mailbox->refresh_token]);
                if (! $response->successful() && ! ($response->status() === 400 && $response->json('error') === 'invalid_token')) {
                    throw new \RuntimeException('Gmail revocation failed.');
                }
            } catch (\Throwable) {
                return back()->withErrors(['gmail' => 'Could not revoke Gmail access. Please try again.']);
            }
            $mailbox->update(['refresh_token' => '']);
        }
        $request->session()->forget('gmail_oauth_state');

        return back()->with('success', 'Gmail disconnected. Conversation history is preserved.');
    }

    public function connect(Request $request): RedirectResponse
    {
        abort_unless($request->user('admin')->can('contact.manage') && $request->user('admin')->can('contact.view'), 403);
        if (! config('services.gmail.client_id') || ! config('services.gmail.client_secret')) {
            return redirect()->route('admin.contact.index')->withErrors(['gmail' => 'Configure the Google OAuth client first.']);
        }

        $state = Str::random(64);
        $request->session()->put('gmail_oauth_state', $state);

        return redirect()->away('https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => config('services.gmail.client_id'),
            'redirect_uri' => route('admin.contact.gmail.callback'),
            'response_type' => 'code',
            'scope' => 'https://mail.google.com/ https://www.googleapis.com/auth/userinfo.email',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
        ]));
    }

    public function callback(Request $request): RedirectResponse
    {
        abort_unless($request->user('admin')->can('contact.manage') && $request->user('admin')->can('contact.view'), 403);
        $state = $request->session()->pull('gmail_oauth_state');
        abort_unless(is_string($state) && is_string($request->query('state')) && hash_equals($state, $request->query('state')), 403);
        if (! is_string($request->query('code')) || ! $request->query('code')) {
            return redirect()->route('admin.contact.index')->withErrors(['gmail' => 'Google authorization was not completed.']);
        }

        try {
            $tokens = Http::asForm()->timeout(15)->post('https://oauth2.googleapis.com/token', [
                'client_id' => config('services.gmail.client_id'),
                'client_secret' => config('services.gmail.client_secret'),
                'code' => $request->query('code'),
                'redirect_uri' => route('admin.contact.gmail.callback'),
                'grant_type' => 'authorization_code',
            ])->throw()->json();

            if (! is_string($tokens['access_token'] ?? null) || ! is_string($tokens['refresh_token'] ?? null) || ! in_array('https://mail.google.com/', explode(' ', $tokens['scope'] ?? ''), true)) {
                throw new \RuntimeException('Google did not grant the required mailbox access.');
            }

            $profile = Http::withToken($tokens['access_token'])->timeout(15)->get('https://www.googleapis.com/oauth2/v2/userinfo')->throw()->json();
            if (! is_string($profile['email'] ?? null) || ! ($profile['verified_email'] ?? false) || ! filter_var($profile['email'], FILTER_VALIDATE_EMAIL)) {
                throw new \RuntimeException('Google did not return a verified email address.');
            }

            $existing = GmailMailbox::query()->firstOrCreate(
                ['singleton' => true],
                ['email' => $profile['email'], 'refresh_token' => $tokens['refresh_token']],
            );
            if (strcasecmp($existing->email, $profile['email']) !== 0) {
                return redirect()->route('admin.contact.index')->withErrors(['gmail' => 'Reconnect the existing Gmail account to preserve conversation history.']);
            }
            $existing->update(['refresh_token' => $tokens['refresh_token']]);
        } catch (\Throwable) {
            return redirect()->route('admin.contact.index')->withErrors(['gmail' => 'Could not connect Gmail. Please try again.']);
        }

        return redirect()->route('admin.contact.index')->with('success', 'Gmail account connected.');
    }
}
