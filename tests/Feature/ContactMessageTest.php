<?php

use App\Jobs\ImportGmailThread;
use App\Models\Admin;
use App\Models\ContactMessage;
use App\Models\GmailMailbox;
use App\Services\AuditLogService;
use App\Services\GmailImap;
use App\Services\GmailMessageParser;
use App\Services\GmailSender;
use App\Services\GmailSync;
use App\Services\GmailToken;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Symfony\Component\Mime\Email;

it('revokes Gmail access while preserving history and the connected identity', function (int $status, array $body) {
    config(['inertia.ssr.enabled' => false]);
    $this->seed(RoleAndPermissionSeeder::class);
    $admin = Admin::factory()->create();
    $mailbox = GmailMailbox::create(['email' => 'mailbox@example.com', 'refresh_token' => 'test-refresh']);
    Http::preventStrayRequests();
    Http::fake(['oauth2.googleapis.com/revoke' => Http::response($body, $status)]);
    $this->actingAs($admin, 'admin')->delete(route('admin.contact.gmail.disconnect'))->assertForbidden();
    Http::assertNothingSent();
    $admin->givePermissionTo(['contact.view', 'contact.manage']);
    $this->delete(route('admin.contact.gmail.disconnect'))->assertSessionHas('success');
    Http::assertSent(fn ($request) => $request->url() === 'https://oauth2.googleapis.com/revoke' && $request['token'] === 'test-refresh');
    expect($mailbox->fresh()->refresh_token)->toBe('');
    expect($mailbox->fresh()->email)->toBe('mailbox@example.com');
    $this->get(route('admin.contact.index'))->assertOk()->assertInertia(fn ($page) => $page->where('gmail.email', null));
    expect(fn () => (new GmailToken)->accessToken($mailbox->fresh()))->toThrow(RuntimeException::class, 'Gmail is disconnected.');
})->with([[200, []], [400, ['error' => 'invalid_token']]]);

it('preserves the connection if Google revocation fails', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $admin = Admin::factory()->create();
    $admin->givePermissionTo(['contact.view', 'contact.manage']);
    $mailbox = GmailMailbox::create(['email' => 'mailbox@example.com', 'refresh_token' => 'test-refresh']);
    Http::preventStrayRequests();
    Http::fake(['oauth2.googleapis.com/revoke' => Http::response('', 503)]);
    $this->actingAs($admin, 'admin')->delete(route('admin.contact.gmail.disconnect'))->assertSessionHasErrors('gmail');
    expect($mailbox->fresh()->refresh_token)->toBe('test-refresh');
    Http::assertSentCount(1);
});

it('limits expensive Gmail operations per admin', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $admin = Admin::factory()->create();
    $admin->givePermissionTo(['contact.view', 'contact.manage']);
    $this->actingAs($admin, 'admin');
    foreach (range(1, 6) as $attempt) {
        $this->post(route('admin.contact.sync'))->assertSessionHasErrors('gmail');
    }
    $this->postJson(route('admin.contact.sync'))->assertTooManyRequests();
    $other = Admin::factory()->create();
    $other->givePermissionTo(['contact.view', 'contact.manage']);
    $this->actingAs($other, 'admin')->post(route('admin.contact.sync'))->assertSessionHasErrors('gmail');
});

it('validates and stores website contact messages', function () {
    $this->postJson(route('contact.store'), [])->assertUnprocessable()->assertJsonValidationErrors(['name', 'email', 'message']);
    $this->post(route('contact.store'), [
        'name' => 'Example Visitor', 'email' => 'visitor@example.com',
        'message' => 'Please contact me.', 'phone' => '12345', 'referral_source' => 'Search',
    ])->assertSessionHasNoErrors();
    $this->assertDatabaseHas('contact_messages', ['email' => 'visitor@example.com', 'message' => 'Please contact me.']);
});

it('protects the contact inbox and loads conversations in bounded cursor batches', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $admin = Admin::factory()->create();
    $this->actingAs($admin, 'admin')->get(route('admin.contact.index'))->assertForbidden();
    $this->getJson(route('admin.contact.more'))->assertForbidden();
    $admin->givePermissionTo('contact.view');
    foreach (range(1, 16) as $number) {
        ContactMessage::create(['name' => "Visitor $number", 'email' => 'visitor@example.com', 'message' => 'Hello']);
    }
    $this->get(route('admin.contact.index'))->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/contact/Index')->has('messages.data', 15));
    $first = $this->getJson(route('admin.contact.more'))->assertOk()->assertJsonCount(15, 'data');
    $cursor = $first->json('next_cursor');
    expect($cursor)->not->toBeNull();
    ContactMessage::create(['name' => 'New visitor', 'email' => 'new@example.com', 'message' => 'Hello']);
    $second = $this->getJson(route('admin.contact.more', ['cursor' => $cursor]))->assertOk()->assertJsonCount(1, 'data');
    expect($second->json('data.0.name'))->toBe('Visitor 1');
    expect($second->json('next_cursor'))->toBeNull();
});

it('loads a conversation history in bounded batches and excludes internal notifications', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $admin = Admin::factory()->create();
    $contact = ContactMessage::create(['name' => 'Visitor', 'email' => 'visitor@example.com', 'message' => 'Hello']);
    $this->actingAs($admin, 'admin')->getJson(route('admin.contact.messages', $contact))->assertForbidden();
    $admin->givePermissionTo('contact.view');
    foreach (range(1, 21) as $number) {
        $contact->replies()->create(['body' => "Reply $number"]);
    }
    $contact->replies()->create(['body' => 'Internal notification', 'notification' => true]);
    $this->getJson(route('admin.contact.more'))->assertOk()->assertJsonPath('data.0.latest_reply.body', 'Reply 21');
    $first = $this->getJson(route('admin.contact.messages', $contact))->assertOk()->assertJsonCount(10, 'data');
    expect($first->json('data.0.body'))->toBe('Reply 21');
    $contact->replies()->create(['body' => 'New reply during pagination']);
    $second = $this->getJson(route('admin.contact.messages', ['contactMessage' => $contact, 'cursor' => $first->json('next_cursor')]))->assertOk()->assertJsonCount(10, 'data');
    expect($second->json('data.0.body'))->toBe('Reply 11');
    $third = $this->getJson(route('admin.contact.messages', ['contactMessage' => $contact, 'cursor' => $second->json('next_cursor')]))->assertOk()->assertJsonCount(1, 'data');
    expect($third->json('data.0.body'))->toBe('Reply 1');
    expect($third->json('next_cursor'))->toBeNull();
});

it('searches conversations on the server across cursor pages', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $admin = Admin::factory()->create();
    $admin->givePermissionTo('contact.view');
    foreach (range(1, 20) as $number) {
        ContactMessage::create(['name' => "Client $number", 'email' => "client$number@example.com", 'subject' => 'Project Alpha', 'message' => 'Hello']);
    }
    ContactMessage::create(['name' => 'Other', 'email' => 'other@example.com', 'message' => 'Hello']);
    $this->actingAs($admin, 'admin')->get(route('admin.contact.index', ['search' => 'Alpha']))->assertOk()
        ->assertInertia(fn ($page) => $page->where('filters.search', 'Alpha')->has('messages.data', 15));
    $first = $this->getJson(route('admin.contact.more', ['search' => 'Alpha']))->assertOk()->assertJsonCount(15, 'data');
    $this->getJson(route('admin.contact.more', ['search' => 'Alpha', 'cursor' => $first->json('next_cursor')]))->assertOk()->assertJsonCount(5, 'data');
    $this->getJson(route('admin.contact.more', ['search' => 'other@example.com']))->assertOk()->assertJsonCount(1, 'data');
});

it('limits conversation previews while keeping the full thread message', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $admin = Admin::factory()->create();
    $admin->givePermissionTo('contact.view');
    $contact = ContactMessage::create(['name' => 'Visitor', 'email' => 'visitor@example.com', 'message' => 'Hello']);
    $contact->replies()->create(['body' => str_repeat('x', 500)]);
    $this->actingAs($admin, 'admin')->getJson(route('admin.contact.more'))->assertOk()
        ->assertJsonPath('data.0.latest_reply.body', str_repeat('x', 200));
    $this->getJson(route('admin.contact.messages', $contact))->assertOk()
        ->assertJsonPath('data.0.body', str_repeat('x', 500));
});

it('shows existing Gmail HTML documents as readable text without changing stored messages', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $admin = Admin::factory()->create();
    $admin->givePermissionTo('contact.view');
    $contact = ContactMessage::create(['name' => 'Visitor', 'email' => 'visitor@example.com', 'message' => 'Hello']);
    $html = '<!DOCTYPE html><html><head><style>body {color:red}</style></head><body><p>Hello &amp; welcome</p><script>bad()</script><p>Next line</p></body></html>';
    $contact->replies()->create(['body' => $html, 'gmail_message_id' => '12345', 'incoming' => true]);

    $this->actingAs($admin, 'admin')->getJson(route('admin.contact.messages', $contact))->assertOk()
        ->assertJsonPath('data.0.body', "Hello & welcome\nNext line")
        ->assertJsonMissingPath('data.0.gmail_message_id');
    $this->getJson(route('admin.contact.more'))->assertOk()
        ->assertJsonPath('data.0.latest_reply.body', "Hello & welcome\nNext line");
    $this->assertDatabaseHas('contact_replies', ['contact_message_id' => $contact->id, 'body' => $html]);
});

it('shows a helpful preview when an HTML header exceeds the preview limit', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $admin = Admin::factory()->create();
    $admin->givePermissionTo('contact.view');
    $contact = ContactMessage::create(['name' => 'Visitor', 'email' => 'visitor@example.com', 'message' => 'Hello']);
    $html = '<!DOCTYPE html><html><head><style>'.str_repeat('a', 250).'</style></head><body><p>Readable message</p></body></html>';
    $contact->replies()->create(['body' => $html, 'gmail_message_id' => '12345', 'incoming' => true]);

    $this->actingAs($admin, 'admin')->getJson(route('admin.contact.more'))->assertOk()
        ->assertJsonPath('data.0.latest_reply.body', '[Open message to read]');
    $this->getJson(route('admin.contact.messages', $contact))->assertOk()
        ->assertJsonPath('data.0.body', 'Readable message');
});

it('authorizes and stores replies only when email delivery is configured', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $admin = Admin::factory()->create();
    $message = ContactMessage::create(['name' => 'Visitor', 'email' => 'visitor@example.com', 'message' => 'Hello']);
    $url = route('admin.contact.reply', $message);
    $this->actingAs($admin, 'admin')->post($url, ['body' => 'Hello'])->assertForbidden();
    $admin->givePermissionTo(['contact.view', 'contact.reply']);
    $this->postJson($url, [])->assertUnprocessable()->assertJsonValidationErrors('body');
    $this->post($url, ['body' => 'Hello'])->assertSessionHasErrors('body');
    $this->assertDatabaseCount('contact_replies', 0);
    GmailMailbox::create(['email' => 'mailbox@example.com', 'refresh_token' => 'test-refresh']);
    $sender = Mockery::mock(GmailSender::class);
    $sender->shouldReceive('notifyIfNeeded')->once();
    $sender->shouldReceive('send')->once()->andReturn('reply@example.com');
    $this->app->instance(GmailSender::class, $sender);
    $this->post($url, ['body' => 'Hello'])->assertSessionHasNoErrors();
    $this->assertDatabaseHas('contact_replies', ['contact_message_id' => $message->id, 'body' => 'Hello']);
});

it('does not record a reply when email is not sent', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $admin = Admin::factory()->create();
    $admin->givePermissionTo(['contact.view', 'contact.reply']);
    $message = ContactMessage::create(['name' => 'Visitor', 'email' => 'visitor@example.com', 'message' => 'Hello']);
    GmailMailbox::create(['email' => 'mailbox@example.com', 'refresh_token' => 'test-refresh']);
    $sender = Mockery::mock(GmailSender::class);
    $sender->shouldReceive('notifyIfNeeded')->once();
    $sender->shouldReceive('send')->once()->andThrow(new RuntimeException('Delivery failed'));
    $this->app->instance(GmailSender::class, $sender);

    $this->actingAs($admin, 'admin')->post(route('admin.contact.reply', $message), ['body' => 'Hello'])->assertSessionHasErrors('body');
    $this->assertDatabaseCount('contact_replies', 0);
});

it('requires permission and validates OAuth state when connecting Gmail', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $admin = Admin::factory()->create();
    $this->actingAs($admin, 'admin')->get(route('admin.contact.gmail.connect'))->assertForbidden();
    $admin->givePermissionTo(['contact.view', 'contact.manage']);
    config(['services.gmail.client_id' => 'test-client', 'services.gmail.client_secret' => 'test-secret']);
    $this->get(route('admin.contact.gmail.connect'))->assertRedirectContains('accounts.google.com');
    $this->get(route('admin.contact.gmail.callback', ['state' => 'invalid', 'code' => 'sample']))->assertForbidden();
});

it('stores a Gmail refresh token encrypted after valid consent', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $admin = Admin::factory()->create();
    $admin->givePermissionTo(['contact.view', 'contact.manage']);
    config(['services.gmail.client_id' => 'test-client', 'services.gmail.client_secret' => 'test-secret']);
    Http::preventStrayRequests();
    Http::fake([
        'oauth2.googleapis.com/token' => Http::response(['access_token' => 'test-access', 'refresh_token' => 'test-refresh', 'scope' => 'https://mail.google.com/']),
        'www.googleapis.com/oauth2/v2/userinfo' => Http::response(['email' => 'mailbox@example.com', 'verified_email' => true]),
    ]);
    $this->actingAs($admin, 'admin')->withSession(['gmail_oauth_state' => 'valid-state'])
        ->get(route('admin.contact.gmail.callback', ['state' => 'valid-state', 'code' => 'sample']))->assertRedirect(route('admin.contact.index'))->assertSessionHas('success');
    $mailbox = GmailMailbox::firstOrFail();
    expect($mailbox->refresh_token)->toBe('test-refresh');
    $this->assertDatabaseMissing('gmail_mailboxes', ['refresh_token' => 'test-refresh']);
});

it('syncs only messages in website threads and does not duplicate them', function () {
    $mailbox = GmailMailbox::create(['email' => 'mailbox@example.com', 'refresh_token' => 'test-refresh']);
    $contact = ContactMessage::create(['name' => 'Visitor', 'email' => 'visitor@example.com', 'message' => 'Hello']);
    $contact->replies()->create(['body' => 'Hello', 'notification' => true, 'rfc_message_id' => 'sent@example.com']);
    $tokens = Mockery::mock(GmailToken::class);
    $tokens->shouldReceive('accessToken')->twice()->andReturn('test-access');
    $imap = Mockery::mock(GmailImap::class);
    $imap->shouldReceive('connect')->twice();
    $imap->shouldReceive('uidValidity')->twice()->andReturn(null);
    $imap->shouldReceive('search')->once()->with('HEADER Message-ID "<sent@example.com>"')->andReturn(['1']);
    $imap->shouldReceive('fetch')->once()->with('1', 'BODY.PEEK[HEADER.FIELDS (MESSAGE-ID FROM TO CC)]')->andReturn(['* 1 FETCH (BODY[HEADER.FIELDS]', "From: mailbox@example.com\r\nTo: mailbox@example.com\r\nMessage-ID: <sent@example.com>\r\n\r\n"]);
    $imap->shouldReceive('fetch')->once()->with('1', 'X-GM-THRID')->andReturn(['* 1 FETCH (X-GM-THRID 123)']);
    $imap->shouldReceive('search')->twice()->with('X-GM-THRID 123')->andReturn(['2', '3']);
    $reply = "From: visitor@example.com\r\nTo: mailbox@example.com\r\nMessage-ID: <reply@example.com>\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\nHello back";
    $unrelated = "From: other@example.com\r\nTo: mailbox@example.com\r\nMessage-ID: <other@example.com>\r\n\r\nPrivate";
    $imap->shouldReceive('fetch')->twice()->with('2', 'X-GM-MSGID INTERNALDATE')->andReturn(['* 2 FETCH (X-GM-MSGID 456 INTERNALDATE "12-Nov-2026 08:30:00 +0000"']);
    $imap->shouldReceive('fetch')->once()->with('2', 'BODY.PEEK[]<0.1048576>')->andReturn(['* 2 FETCH (BODY[]', $reply]);
    $imap->shouldReceive('fetch')->twice()->with('3', 'X-GM-MSGID INTERNALDATE')->andReturn(['* 3 FETCH (X-GM-MSGID 789']);
    $imap->shouldReceive('fetch')->twice()->with('3', 'BODY.PEEK[]<0.1048576>')->andReturn(['* 3 FETCH (BODY[]', $unrelated]);
    $sender = Mockery::mock(GmailSender::class);
    $service = new GmailSync($tokens, $imap, new GmailMessageParser, $sender);
    $service->sync($mailbox);
    $service->sync($mailbox);
    $this->assertDatabaseHas('contact_replies', ['contact_message_id' => $contact->id, 'rfc_message_id' => 'reply@example.com', 'incoming' => true]);
    expect($contact->replies()->where('rfc_message_id', 'reply@example.com')->first()->created_at->toDateTimeString())->toBe('2026-11-12 08:30:00');
    $this->assertDatabaseMissing('contact_replies', ['rfc_message_id' => 'other@example.com']);
    $this->assertDatabaseCount('contact_replies', 2);
});

it('searches Gmail for exact client conversations and excludes unrelated messages', function () {
    $mailbox = GmailMailbox::create(['email' => 'mailbox@example.com', 'refresh_token' => 'test-refresh']);
    $tokens = Mockery::mock(GmailToken::class);
    $tokens->shouldReceive('accessToken')->once()->andReturn('test-access');
    $imap = Mockery::mock(GmailImap::class);
    $imap->shouldReceive('connect')->once();
    $imap->shouldReceive('search')->once()->with('OR OR FROM "client@example.com" TO "client@example.com" CC "client@example.com"')->andReturn(['1', '2', '3']);
    $imap->shouldReceive('fetch')->once()->with('3', Mockery::any())->andReturn(['* 3 FETCH (X-GM-THRID 30', "From: mailbox@example.com\r\nTo: other@example.com\r\nCc: client@example.com\r\nSubject: Update\r\n\r\n"]);
    $imap->shouldReceive('fetch')->once()->with('2', Mockery::any())->andReturn(['* 2 FETCH (X-GM-THRID 20 INTERNALDATE "12-Nov-2026 08:30:00 +0000"', "From: other@example.com\r\nTo: mailbox@example.com\r\nSubject: Private\r\n\r\n"]);
    $imap->shouldReceive('fetch')->once()->with('1', Mockery::any())->andReturn(['* 1 FETCH (X-GM-THRID 10 INTERNALDATE "12-Nov-2026 08:30:00 +0000"', "From: client@example.com\r\nTo: mailbox@example.com\r\nSubject: Project\r\n\r\n"]);

    $threads = (new GmailSync($tokens, $imap, new GmailMessageParser, Mockery::mock(GmailSender::class)))->searchThreads($mailbox, 'client@example.com');

    expect($threads['threads'])->toHaveCount(2)->and($threads['threads'][0]['id'])->toBe('30')->and($threads['threads'][1]['subject'])->toBe('Project')->and($threads['next_before'])->toBeNull();
});

it('resumes a Gmail thread after a failed message without refetching completed messages', function () {
    $mailbox = GmailMailbox::create(['email' => 'mailbox@example.com', 'refresh_token' => 'test-refresh']);
    $contact = ContactMessage::create(['name' => 'Client', 'email' => 'client@example.com', 'message' => 'Hello', 'gmail_thread_id' => '123', 'initiated_by_admin' => true]);
    $tokens = Mockery::mock(GmailToken::class);
    $tokens->shouldReceive('accessToken')->twice()->andReturn('test-access');
    $imap = Mockery::mock(GmailImap::class);
    $imap->shouldReceive('connect')->twice();
    $imap->shouldReceive('uidValidity')->twice()->andReturn(42);
    $imap->shouldReceive('search')->once()->with('X-GM-THRID 123')->andReturn(['1', '2']);
    $imap->shouldReceive('search')->once()->with('X-GM-THRID 123 UID 2:*')->andReturn(['1', '2']);
    $imap->shouldReceive('fetch')->once()->with('1', 'X-GM-MSGID INTERNALDATE')->andReturn(['X-GM-MSGID 101']);
    $imap->shouldReceive('fetch')->twice()->with('2', 'X-GM-MSGID INTERNALDATE')->andReturn(['X-GM-MSGID 102']);
    $imap->shouldReceive('fetch')->once()->with('1', 'BODY.PEEK[]<0.1048576>')->andReturn(['', "From: client@example.com\r\nTo: mailbox@example.com\r\nMessage-ID: <one@example.com>\r\n\r\nOne"]);
    $imap->shouldReceive('fetch')->twice()->with('2', 'BODY.PEEK[]<0.1048576>')->andReturn(['', ''], ['', "From: client@example.com\r\nTo: mailbox@example.com\r\nMessage-ID: <two@example.com>\r\n\r\nTwo"]);
    $sync = new GmailSync($tokens, $imap, new GmailMessageParser, Mockery::mock(GmailSender::class));
    try {
        $sync->sync($mailbox);
        $this->fail('The failed message should stop the sync.');
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('Gmail returned an empty message.');
    }
    expect((int) $contact->fresh()->gmail_last_uid)->toBe(1);
    $sync->sync($mailbox);
    expect((int) $contact->fresh()->gmail_last_uid)->toBe(2);
    $this->assertDatabaseCount('contact_replies', 2);
});

it('reads long Gmail search response lines without truncation', function () {
    $line = str_repeat('1 ', 40000)."\r\n";
    $stream = fopen('php://temp', 'r+');
    fwrite($stream, $line);
    rewind($stream);
    $imap = new GmailImap;
    (new ReflectionProperty($imap, 'stream'))->setValue($imap, $stream);
    expect((new ReflectionMethod($imap, 'readLine'))->invoke($imap))->toBe($line);
});

it('rescans a Gmail thread if the mailbox UID validity changes', function () {
    $mailbox = GmailMailbox::create(['email' => 'mailbox@example.com', 'refresh_token' => 'test-refresh']);
    $contact = ContactMessage::create(['name' => 'Client', 'email' => 'client@example.com', 'message' => 'Hello', 'gmail_thread_id' => '123', 'initiated_by_admin' => true]);
    $contact->forceFill(['gmail_uid_validity' => 10, 'gmail_last_uid' => 99])->save();
    $tokens = Mockery::mock(GmailToken::class);
    $tokens->shouldReceive('accessToken')->once()->andReturn('test-access');
    $imap = Mockery::mock(GmailImap::class);
    $imap->shouldReceive('connect')->once();
    $imap->shouldReceive('uidValidity')->once()->andReturn(11);
    $imap->shouldReceive('search')->once()->with('X-GM-THRID 123')->andReturn(['1']);
    $imap->shouldReceive('fetch')->once()->with('1', 'X-GM-MSGID INTERNALDATE')->andReturn(['X-GM-MSGID 101']);
    $imap->shouldReceive('fetch')->once()->with('1', 'BODY.PEEK[]<0.1048576>')->andReturn(['', "From: client@example.com\r\nTo: mailbox@example.com\r\nMessage-ID: <one@example.com>\r\n\r\nOne"]);
    (new GmailSync($tokens, $imap, new GmailMessageParser, Mockery::mock(GmailSender::class)))->sync($mailbox);
    expect((int) $contact->fresh()->gmail_uid_validity)->toBe(11)->and((int) $contact->fresh()->gmail_last_uid)->toBe(1);
    $this->assertDatabaseHas('contact_replies', ['contact_message_id' => $contact->id, 'body' => 'One']);
});

it('loads older Gmail search results in bounded batches', function () {
    $mailbox = GmailMailbox::create(['email' => 'mailbox@example.com', 'refresh_token' => 'test-refresh']);
    $tokens = Mockery::mock(GmailToken::class);
    $tokens->shouldReceive('accessToken')->twice()->andReturn('test-access');
    $imap = Mockery::mock(GmailImap::class);
    $imap->shouldReceive('connect')->twice();
    $imap->shouldReceive('search')->once()->with('OR OR FROM "client@example.com" TO "client@example.com" CC "client@example.com"')->andReturn(array_map('strval', range(1, 41)));
    $imap->shouldReceive('search')->once()->with('UID 1:1 OR OR FROM "client@example.com" TO "client@example.com" CC "client@example.com"')->andReturn(['1']);
    $imap->shouldReceive('fetch')->times(41)->andReturn(['* 1 FETCH (X-GM-THRID 10', "From: client@example.com\r\nTo: mailbox@example.com\r\nSubject: Project\r\n\r\n"]);
    $sync = new GmailSync($tokens, $imap, new GmailMessageParser, Mockery::mock(GmailSender::class));

    $first = $sync->searchThreads($mailbox, 'client@example.com');
    $next = $sync->searchThreads($mailbox, 'client@example.com', $first['next_before']);

    expect($first['next_before'])->toBe('2')->and($next['next_before'])->toBeNull()->and($next['threads'])->toHaveCount(1);
});

it('imports only a selected verified client thread and syncs its messages', function () {
    $mailbox = GmailMailbox::create(['email' => 'mailbox@example.com', 'refresh_token' => 'test-refresh']);
    $tokens = Mockery::mock(GmailToken::class);
    $tokens->shouldReceive('accessToken')->twice()->andReturn('test-access');
    $imap = Mockery::mock(GmailImap::class);
    $imap->shouldReceive('connect')->twice();
    $imap->shouldReceive('uidValidity')->twice()->andReturn(null);
    $imap->shouldReceive('fetch')->once()->with('1', Mockery::pattern('/HEADER.FIELDS/'))->andReturn(['* 1 FETCH (X-GM-THRID 10', "From: client@example.com\r\nTo: mailbox@example.com\r\nSubject: Project\r\n\r\n"]);
    $imap->shouldReceive('search')->twice()->with('X-GM-THRID 10')->andReturn(['1'], ['1', '2']);
    $imap->shouldReceive('fetch')->twice()->with('1', 'X-GM-MSGID INTERNALDATE')->andReturn(['* 1 FETCH (X-GM-MSGID 99']);
    $imap->shouldReceive('fetch')->once()->with('1', 'BODY.PEEK[]<0.1048576>')->andReturn(['* 1 FETCH', "From: client@example.com\r\nTo: mailbox@example.com\r\nMessage-ID: <mail@example.com>\r\n\r\nHello"]);
    $imap->shouldReceive('fetch')->once()->with('2', 'X-GM-MSGID INTERNALDATE')->andReturn(['* 2 FETCH (X-GM-MSGID 100']);
    $imap->shouldReceive('fetch')->once()->with('2', 'BODY.PEEK[]<0.1048576>')->andReturn(['* 2 FETCH', "From: client@example.com\r\nTo: mailbox@example.com\r\nMessage-ID: <new@example.com>\r\n\r\nNew reply"]);

    $sync = new GmailSync($tokens, $imap, new GmailMessageParser, Mockery::mock(GmailSender::class));
    $contact = $sync->importThread($mailbox, 'client@example.com', '10', '1');
    $sync->sync($mailbox);

    expect($contact->subject)->toBe('Project');
    $this->assertDatabaseHas('contact_replies', ['contact_message_id' => $contact->id, 'body' => 'Hello', 'incoming' => true]);
    $this->assertDatabaseHas('contact_replies', ['contact_message_id' => $contact->id, 'body' => 'New reply', 'incoming' => true]);
    $this->assertDatabaseCount('contact_replies', 2);
});

it('rejects a forged Gmail thread selection', function () {
    $mailbox = GmailMailbox::create(['email' => 'mailbox@example.com', 'refresh_token' => 'test-refresh']);
    $tokens = Mockery::mock(GmailToken::class);
    $tokens->shouldReceive('accessToken')->once()->andReturn('test-access');
    $imap = Mockery::mock(GmailImap::class);
    $imap->shouldReceive('connect')->once();
    $imap->shouldReceive('fetch')->once()->andReturn(['* 1 FETCH (X-GM-THRID 10', "From: other@example.com\r\nTo: mailbox@example.com\r\n\r\n"]);

    expect(fn () => (new GmailSync($tokens, $imap, new GmailMessageParser, Mockery::mock(GmailSender::class)))->importThread($mailbox, 'client@example.com', '10', '1'))->toThrow(ValidationException::class);
    $this->assertDatabaseCount('contact_messages', 0);
});

it('requires contact permissions to search and import Gmail threads', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $admin = Admin::factory()->create();
    $admin->givePermissionTo('contact.view');
    $this->actingAs($admin, 'admin')->postJson(route('admin.contact.gmail.search'), ['email' => 'client@example.com'])->assertForbidden();
    $this->post(route('admin.contact.gmail.import'), ['email' => 'client@example.com', 'thread_id' => '1', 'uid' => '1'])->assertForbidden();
});

it('queues selected Gmail conversations and restricts batch status to its owner', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $admin = Admin::factory()->create();
    $admin->givePermissionTo(['contact.view', 'contact.manage']);
    GmailMailbox::create(['email' => 'mailbox@example.com', 'refresh_token' => 'test-refresh']);
    Bus::fake();

    $response = $this->actingAs($admin, 'admin')->postJson(route('admin.contact.gmail.import'), ['email' => 'client@example.com', 'threads' => [['thread_id' => '10', 'uid' => '1'], ['thread_id' => '11', 'uid' => '2']]])->assertAccepted();
    Bus::assertBatched(fn ($batch) => $batch->jobs->count() === 2 && $batch->jobs->every(fn ($job) => $job instanceof ImportGmailThread));
    $batchId = $response->json('batch_id');
    $this->getJson(route('admin.contact.gmail.import-status', $batchId))->assertOk();
    $other = Admin::factory()->create();
    $other->givePermissionTo(['contact.view', 'contact.manage']);
    $this->actingAs($other, 'admin')->getJson(route('admin.contact.gmail.import-status', $batchId))->assertNotFound();
});

it('imports a queued thread without sending email', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $admin = Admin::factory()->create(['is_active' => true]);
    $admin->givePermissionTo(['contact.view', 'contact.manage']);
    $mailbox = GmailMailbox::create(['email' => 'mailbox@example.com', 'refresh_token' => 'test-refresh']);
    $contact = ContactMessage::create(['name' => 'Client', 'email' => 'client@example.com', 'message' => '', 'initiated_by_admin' => true]);
    $sync = Mockery::mock(GmailSync::class);
    $sync->shouldReceive('importThread')->once()->withArgs(fn ($account, $email, $thread, $uid) => $account->is($mailbox) && $email === 'client@example.com' && $thread === '10' && $uid === '1')->andReturn($contact);
    $audit = Mockery::mock(AuditLogService::class);
    $audit->shouldReceive('record')->once();

    (new ImportGmailThread($admin->id, $mailbox->id, 'client@example.com', '10', '1'))->handle($sync, $audit);
});

it('does not import a queued thread after permission is revoked', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $admin = Admin::factory()->create(['is_active' => true]);
    $admin->givePermissionTo('contact.view');
    $mailbox = GmailMailbox::create(['email' => 'mailbox@example.com', 'refresh_token' => 'test-refresh']);
    $sync = Mockery::mock(GmailSync::class);
    $sync->shouldNotReceive('importThread');
    $audit = Mockery::mock(AuditLogService::class);
    $audit->shouldNotReceive('record');

    (new ImportGmailThread($admin->id, $mailbox->id, 'client@example.com', '10', '1'))->handle($sync, $audit);
});

it('starts a new conversation only for an authorized admin and stores the sent message', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $admin = Admin::factory()->create();
    $payload = ['name' => 'Visitor', 'email' => 'visitor@example.com', 'body' => 'Welcome'];
    $this->actingAs($admin, 'admin')->post(route('admin.contact.start'), $payload)->assertForbidden();
    $admin->givePermissionTo(['contact.view', 'contact.reply']);
    GmailMailbox::create(['email' => 'mailbox@example.com', 'refresh_token' => 'test-refresh']);
    $sender = Mockery::mock(GmailSender::class);
    $sender->shouldReceive('send')->once()->andReturn('new@example.com');
    $this->app->instance(GmailSender::class, $sender);
    $this->post(route('admin.contact.start'), $payload)->assertSessionHasNoErrors();
    $this->assertDatabaseHas('contact_messages', ['email' => 'visitor@example.com', 'initiated_by_admin' => true]);
    $this->assertDatabaseHas('contact_replies', ['rfc_message_id' => 'new@example.com', 'body' => 'Welcome']);
});

it('keeps a website message when its Gmail notification fails', function () {
    GmailMailbox::create(['email' => 'mailbox@example.com', 'refresh_token' => 'test-refresh']);
    $sender = Mockery::mock(GmailSender::class);
    $sender->shouldReceive('notifyIfNeeded')->once()->andThrow(new RuntimeException('Delivery failed'));
    $this->app->instance(GmailSender::class, $sender);
    $this->post(route('contact.store'), ['name' => 'Visitor', 'email' => 'visitor@example.com', 'message' => 'Help'])->assertSessionHasNoErrors();
    $this->assertDatabaseHas('contact_messages', ['email' => 'visitor@example.com', 'message' => 'Help']);
});

it('keeps the existing Gmail connection when another account is authorized', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $admin = Admin::factory()->create();
    $admin->givePermissionTo(['contact.view', 'contact.manage']);
    GmailMailbox::create(['email' => 'existing@example.com', 'refresh_token' => 'existing-token']);
    config(['services.gmail.client_id' => 'test-client', 'services.gmail.client_secret' => 'test-secret']);
    Http::preventStrayRequests();
    Http::fake([
        'oauth2.googleapis.com/token' => Http::response(['access_token' => 'test-access', 'refresh_token' => 'new-token', 'scope' => 'https://mail.google.com/']),
        'www.googleapis.com/oauth2/v2/userinfo' => Http::response(['email' => 'other@example.com', 'verified_email' => true]),
    ]);
    $this->actingAs($admin, 'admin')->withSession(['gmail_oauth_state' => 'valid-state'])
        ->get(route('admin.contact.gmail.callback', ['state' => 'valid-state', 'code' => 'sample']))->assertSessionHasErrors('gmail');
    expect(GmailMailbox::firstOrFail()->email)->toBe('existing@example.com');
    expect(GmailMailbox::firstOrFail()->refresh_token)->toBe('existing-token');
});

it('does not leave an empty conversation after a send failure', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $admin = Admin::factory()->create();
    $admin->givePermissionTo(['contact.view', 'contact.reply']);
    GmailMailbox::create(['email' => 'mailbox@example.com', 'refresh_token' => 'test-refresh']);
    $sender = Mockery::mock(GmailSender::class);
    $sender->shouldReceive('send')->once()->andThrow(new RuntimeException('Delivery failed'));
    $this->app->instance(GmailSender::class, $sender);
    $this->actingAs($admin, 'admin')->post(route('admin.contact.start'), ['name' => 'Visitor', 'email' => 'visitor@example.com', 'body' => 'Welcome'])->assertSessionHasErrors('body');
    $this->assertDatabaseCount('contact_messages', 0);
});

it('forwards a website inquiry only once when notification is retried', function () {
    $mailbox = GmailMailbox::create(['email' => 'mailbox@example.com', 'refresh_token' => 'test-refresh']);
    $contact = ContactMessage::create(['name' => 'Visitor', 'email' => 'visitor@example.com', 'message' => 'Hello']);
    $tokens = Mockery::mock(GmailToken::class);
    $sender = Mockery::mock(GmailSender::class, [$tokens])->makePartial();
    $sender->shouldReceive('notify')->once()->andReturn('notification@example.com');
    $sender->notifyIfNeeded($mailbox, $contact);
    $sender->notifyIfNeeded($mailbox, $contact);
    $this->assertDatabaseHas('contact_replies', ['contact_message_id' => $contact->id, 'rfc_message_id' => 'notification@example.com', 'notification' => true]);
    $this->assertDatabaseCount('contact_replies', 1);
});

it('does not allow unauthorized admins to sync Gmail conversations', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $admin = Admin::factory()->create();
    $admin->givePermissionTo('contact.view');
    $this->actingAs($admin, 'admin')->post(route('admin.contact.sync'))->assertForbidden();
});

it('enforces contact role permissions and reflects changes on the next request', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $viewer = Admin::factory()->create();
    $other = Admin::factory()->create();
    $role = Role::create(['name' => '[QA Test] Contact viewer', 'guard_name' => 'admin']);
    $role->givePermissionTo('contact.view');
    $viewer->assignRole($role);
    $contact = ContactMessage::create(['name' => '[QA Test] Visitor', 'email' => 'qa@example.invalid', 'message' => 'Hello', 'initiated_by_admin' => true]);

    $this->actingAs($viewer, 'admin')->get(route('admin.contact.index'))->assertOk();
    $this->getJson(route('admin.contact.messages', $contact))->assertOk();
    $this->post(route('admin.contact.reply', $contact), ['body' => 'Hello'])->assertForbidden();
    $this->post(route('admin.contact.start'), [])->assertForbidden();
    $this->post(route('admin.contact.sync'))->assertForbidden();
    $this->get(route('admin.contact.gmail.connect'))->assertForbidden();

    $role->givePermissionTo(['contact.reply', 'contact.manage']);
    $this->post(route('admin.contact.reply', $contact), ['body' => 'Hello'])->assertSessionHasErrors('body');
    $this->post(route('admin.contact.sync'))->assertSessionHasErrors('gmail');

    $role->revokePermissionTo('contact.view');
    $viewer->unsetRelation('roles')->unsetRelation('permissions');
    $this->get(route('admin.contact.index'))->assertForbidden();
    $this->post(route('admin.contact.sync'))->assertForbidden();
    $this->actingAs($other, 'admin')->get(route('admin.contact.index'))->assertForbidden();
});

it('syncs Gmail when an authorized admin requests it', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $admin = Admin::factory()->create();
    $admin->givePermissionTo(['contact.view', 'contact.manage']);
    GmailMailbox::create(['email' => 'mailbox@example.com', 'refresh_token' => 'test-refresh']);
    $sync = Mockery::mock(GmailSync::class);
    $sync->shouldReceive('sync')->once();
    $this->app->instance(GmailSync::class, $sync);

    $this->actingAs($admin, 'admin')->post(route('admin.contact.sync'))
        ->assertSessionHas('success', 'Gmail conversations synced.');
});

it('shows an error when an authorized Gmail sync fails', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $admin = Admin::factory()->create();
    $admin->givePermissionTo(['contact.view', 'contact.manage']);
    GmailMailbox::create(['email' => 'mailbox@example.com', 'refresh_token' => 'test-refresh']);
    $sync = Mockery::mock(GmailSync::class);
    $sync->shouldReceive('sync')->once()->andThrow(new RuntimeException('Sensitive details'));
    $this->app->instance(GmailSync::class, $sync);

    $this->actingAs($admin, 'admin')->post(route('admin.contact.sync'))
        ->assertSessionHasErrors(['gmail' => 'Could not sync Gmail. Please check the connection and try again.']);
});

it('does not replace the Gmail connection when consent lacks the required scope', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $admin = Admin::factory()->create();
    $admin->givePermissionTo(['contact.view', 'contact.manage']);
    GmailMailbox::create(['email' => 'existing@example.com', 'refresh_token' => 'existing-token']);
    config(['services.gmail.client_id' => 'test-client', 'services.gmail.client_secret' => 'test-secret']);
    Http::preventStrayRequests();
    Http::fake(['oauth2.googleapis.com/token' => Http::response(['access_token' => 'test-access', 'refresh_token' => 'new-token', 'scope' => 'openid'])]);
    $this->actingAs($admin, 'admin')->withSession(['gmail_oauth_state' => 'valid-state'])
        ->get(route('admin.contact.gmail.callback', ['state' => 'valid-state', 'code' => 'sample']))->assertSessionHasErrors('gmail');
    expect(GmailMailbox::firstOrFail()->refresh_token)->toBe('existing-token');
});

it('runs scheduled Gmail sync when a mailbox is connected', function () {
    GmailMailbox::create(['email' => 'mailbox@example.com', 'refresh_token' => 'test-refresh']);
    $sync = Mockery::mock(GmailSync::class);
    $sync->shouldReceive('sync')->once();
    $this->app->instance(GmailSync::class, $sync);
    $this->artisan('contact:sync-gmail')->assertSuccessful();
});

it('refreshes Gmail access without exposing tokens in failure messages', function () {
    $mailbox = GmailMailbox::create(['email' => 'mailbox@example.com', 'refresh_token' => 'test-refresh']);
    config(['services.gmail.client_id' => 'test-client', 'services.gmail.client_secret' => 'test-secret']);
    Http::preventStrayRequests();
    Http::fake(['oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_grant'], 400)]);
    expect(fn () => (new GmailToken)->accessToken($mailbox))
        ->toThrow(RuntimeException::class, 'Gmail authorization failed. Reconnect the mailbox.');
});

it('addresses notifications and replies to the correct participants and thread', function () {
    $mailbox = GmailMailbox::create(['email' => 'mailbox@example.com', 'refresh_token' => 'test-refresh']);
    $contact = ContactMessage::create(['name' => 'Visitor', 'email' => 'visitor@example.com', 'phone' => '12345', 'message' => 'Question']);
    $tokens = Mockery::mock(GmailToken::class);
    $sender = new class($tokens) extends GmailSender
    {
        public Email $captured;

        protected function deliver(GmailMailbox $mailbox, Email $email): string
        {
            $this->captured = $email;

            return 'notification@example.com';
        }
    };
    $sender->notify($mailbox, $contact);
    expect($sender->captured->getTo()[0]->getAddress())->toBe('mailbox@example.com');
    expect($sender->captured->getReplyTo()[0]->getAddress())->toBe('visitor@example.com');
    expect($sender->captured->getTextBody())->toContain('Question', '12345');
    $contact->replies()->create(['body' => 'Question', 'rfc_message_id' => 'notification@example.com', 'notification' => true]);
    $sender->send($mailbox, $contact, 'Our answer');
    expect($sender->captured->getTo()[0]->getAddress())->toBe('visitor@example.com');
    expect($sender->captured->getHeaders()->get('In-Reply-To')->getBodyAsString())->toContain('notification@example.com');
    $contact->update(['subject' => 'Project discussion']);
    $sender->send($mailbox, $contact, 'Follow up');
    expect($sender->captured->getSubject())->toBe('Re: Project discussion');
});

it('reuses an unexpired Gmail token during a sync', function () {
    $mailbox = GmailMailbox::create(['email' => 'mailbox@example.com', 'refresh_token' => 'test-refresh']);
    config(['services.gmail.client_id' => 'test-client', 'services.gmail.client_secret' => 'test-secret']);
    Http::preventStrayRequests();
    Http::fake(['oauth2.googleapis.com/token' => Http::response(['access_token' => 'test-access', 'expires_in' => 3600])]);
    $tokens = new GmailToken;
    expect($tokens->accessToken($mailbox))->toBe('test-access');
    expect($tokens->accessToken($mailbox))->toBe('test-access');
    Http::assertSentCount(1);
});

it('does not attach a Gmail thread when the matching header belongs to another sender', function () {
    $mailbox = GmailMailbox::create(['email' => 'mailbox@example.com', 'refresh_token' => 'test-refresh']);
    $contact = ContactMessage::create(['name' => 'Visitor', 'email' => 'visitor@example.com', 'message' => 'Hello']);
    $contact->replies()->create(['body' => 'Hello', 'notification' => true, 'rfc_message_id' => 'sent@example.com']);
    $tokens = Mockery::mock(GmailToken::class);
    $tokens->shouldReceive('accessToken')->once()->andReturn('test-access');
    $imap = Mockery::mock(GmailImap::class);
    $imap->shouldReceive('connect')->once();
    $imap->shouldReceive('search')->once()->with('HEADER Message-ID "<sent@example.com>"')->andReturn(['1']);
    $imap->shouldReceive('fetch')->once()->with('1', 'BODY.PEEK[HEADER.FIELDS (MESSAGE-ID FROM TO CC)]')->andReturn(['* FETCH', "From: other@example.com\r\nTo: mailbox@example.com\r\nMessage-ID: <sent@example.com>\r\n\r\n"]);
    $sender = Mockery::mock(GmailSender::class);
    (new GmailSync($tokens, $imap, new GmailMessageParser, $sender))->sync($mailbox);
    expect($contact->fresh()->gmail_thread_id)->toBeNull();
});
