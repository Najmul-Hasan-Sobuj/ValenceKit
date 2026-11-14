<?php

use App\Services\GmailImap;

it('returns clean message identifiers from Gmail search responses', function () {
    [$client, $server] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
    $imap = new GmailImap;
    (new ReflectionProperty($imap, 'stream'))->setValue($imap, $client);
    fwrite($server, "* SEARCH 123 456\r\nA1 OK Completed\r\n");

    expect($imap->search('ALL'))->toBe(['123', '456']);
    fclose($server);
});

it('reads IMAP literals without mistaking message content for protocol responses', function () {
    $sockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
    expect($sockets)->not->toBeFalse();
    [$client, $server] = $sockets;
    $imap = new GmailImap;
    (new ReflectionProperty($imap, 'stream'))->setValue($imap, $client);
    $body = "Hello\r\nA1 OK inside the message\r\n";
    fwrite($server, '* 7 FETCH (UID 8 BODY[] {'.strlen($body)."}\r\n".$body.")\r\nA1 OK Completed\r\n");

    $response = $imap->fetch('8', 'BODY.PEEK[]');

    expect($response[1])->toBe($body);
    expect(fgets($server))->toBe("A1 UID FETCH 8 (BODY.PEEK[])\r\n");
    fclose($server);
});

it('rejects failed IMAP commands without exposing the server response', function () {
    [$client, $server] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
    $imap = new GmailImap;
    (new ReflectionProperty($imap, 'stream'))->setValue($imap, $client);
    fwrite($server, "A1 NO secret-server-detail\r\n");

    expect(fn () => $imap->search('X-GM-THRID 1'))->toThrow(RuntimeException::class, 'Gmail rejected the request.');
    fclose($server);
});

it('finds the Gmail All Mail folder by its special-use flag', function () {
    $method = new ReflectionMethod(GmailImap::class, 'allMailFolder');
    $lines = [
        '* LIST (\\HasNoChildren \\Sent) "/" "[Google Mail]/Sent Mail"',
        '* LIST (\\HasNoChildren \\All) "/" "[Google Mail]/All Mail"',
    ];
    expect($method->invoke(new GmailImap, $lines))->toBe('[Google Mail]/All Mail');
    expect($method->invoke(new GmailImap, [$lines[0]]))->toBeNull();
});
