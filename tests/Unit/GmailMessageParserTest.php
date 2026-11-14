<?php

use App\Services\GmailMessageParser;

it('extracts plain text and addresses from multipart mail without attachments', function () {
    $raw = "From: Visitor <visitor@example.com>\r\nTo: Admin <admin@example.com>\r\nMessage-ID: <unique@example.com>\r\nContent-Type: multipart/mixed; boundary=\"example-boundary\"\r\n\r\n"
        ."--example-boundary\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\nHello=20there\r\n"
        ."--example-boundary\r\nContent-Type: text/plain\r\nContent-Disposition: attachment; filename=\"secret.txt\"\r\n\r\nattachment contents\r\n"
        ."--example-boundary--\r\n";

    expect((new GmailMessageParser)->parse($raw))->toBe([
        'message_id' => 'unique@example.com',
        'from' => 'visitor@example.com',
        'to' => ['admin@example.com'],
        'subject' => '',
        'body' => 'Hello there',
    ]);
});

it('reads actual addresses instead of email-like display names', function () {
    $raw = "From: \"visitor@example.com\" <other@example.com>\r\nTo: \"Customer, Support\" <mailbox@example.com>, visitor@example.com\r\nMessage-ID: <id@example.com>\r\n\r\nHello";
    $message = (new GmailMessageParser)->parse($raw);
    expect($message['from'])->toBe('other@example.com');
    expect($message['to'])->toBe(['mailbox@example.com', 'visitor@example.com']);
});

it('renders HTML-only messages as readable text without scripts', function () {
    $raw = "From: visitor@example.com\r\nTo: mailbox@example.com\r\nMessage-ID: <html@example.com>\r\nContent-Type: text/html; charset=UTF-8\r\n\r\n<p>Hello &amp; welcome</p><script>bad()</script><p>Next line</p>";
    expect((new GmailMessageParser)->parse($raw)['body'])->toBe("Hello & welcome\nNext line");
});

it('converts an HTML document mislabeled as plain text', function () {
    $html = '<!DOCTYPE html><html><head><style>body { color: red }</style></head><body><p>Hello &amp; welcome</p><script>bad()</script><p>Next line</p></body></html>';
    $raw = "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n".base64_encode($html);

    expect((new GmailMessageParser)->parse($raw)['body'])->toBe("Hello & welcome\nNext line");
});

it('preserves plain text that contains code examples', function () {
    $raw = "Content-Type: text/plain; charset=UTF-8\r\n\r\nUse <div> here and </div> there.";

    expect((new GmailMessageParser)->parse($raw)['body'])->toBe('Use <div> here and </div> there.');
});

it('removes HTML layout whitespace without joining table cells', function () {
    $raw = "Content-Type: text/html; charset=UTF-8\r\n\r\n".
        "<html><body><p>Welcome</p>\r\n   \r\n &nbsp;\r\n <table>\r\n <tr><td>Amount</td><td>700</td></tr>\r\n </table>\r\n <p>Thank you</p></body></html>";

    expect((new GmailMessageParser)->parse($raw)['body'])->toBe("Welcome\n\nAmount 700\n\nThank you");
});

it('normalizes whitespace-only lines in plain text while preserving code indentation', function () {
    $raw = "Content-Type: text/plain; charset=UTF-8\r\n\r\nHello\r\n  \r\n\t\r\n    code();\r\n";

    expect((new GmailMessageParser)->parse($raw)['body'])->toBe("Hello\n\n    code();");
});

it('reads the text part when a large multipart message is truncated', function () {
    $raw = "From: visitor@example.com\r\nTo: mailbox@example.com\r\nMessage-ID: <large@example.com>\r\nContent-Type: multipart/mixed; boundary=\"boundary\"\r\n\r\n--boundary\r\nContent-Type: text/plain\r\n\r\nA readable message";
    expect((new GmailMessageParser)->parse($raw)['body'])->toBe('A readable message');
});
