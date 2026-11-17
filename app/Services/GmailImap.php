<?php

namespace App\Services;

use RuntimeException;

/**
 * IMAP XOAUTH2 authentication adapted from Webklex/php-imap.
 * Copyright (c) 2016 Malte Goldenbaum. MIT License:
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */
class GmailImap
{
    /** @var resource|null */
    private $stream = null;

    private int $sequence = 0;

    private ?int $uidValidity = null;

    public function connect(string $email, #[\SensitiveParameter] string $token): void
    {
        $stream = @stream_socket_client('ssl://imap.gmail.com:993', $errorCode, $errorMessage, 15, STREAM_CLIENT_CONNECT);
        if ($stream === false) {
            throw new RuntimeException('Could not connect to Gmail.');
        }
        $this->stream = $stream;
        stream_set_timeout($this->stream, 15);
        if (! str_starts_with($this->readLine(), '* OK')) {
            throw new RuntimeException('Gmail did not accept the connection.');
        }

        $credentials = base64_encode("user=$email\1auth=Bearer $token\1\1");
        $this->command('AUTHENTICATE XOAUTH2 '.$credentials);
        $folder = $this->allMailFolder($this->command('LIST "" "*"'));
        if ($folder === null) {
            throw new RuntimeException('Gmail All Mail folder is not available over IMAP.');
        }
        $response = $this->command('EXAMINE "'.addcslashes($folder, '\\"').'"');
        $this->uidValidity = preg_match('/\[UIDVALIDITY\s+(\d+)\]/i', implode('', $response), $match) ? (int) $match[1] : null;
    }

    public function uidValidity(): ?int
    {
        return $this->uidValidity;
    }

    /** @param list<string> $lines */
    private function allMailFolder(array $lines): ?string
    {
        foreach ($lines as $line) {
            if (preg_match('/^\* LIST \([^)]*\\\\All(?=[\s)])[^)]*\)\s+"[^"]*"\s+"((?:[^"\\\\]|\\\\.)+)"/i', $line, $match)) {
                return stripcslashes($match[1]);
            }
        }

        return null;
    }

    /** @return list<string> */
    public function search(string $criteria): array
    {
        $response = $this->command('UID SEARCH '.$criteria);
        foreach ($response as $line) {
            if (preg_match('/^\* SEARCH(?: ([0-9]+(?: [0-9]+)*))?\r?\n$/', $line, $matches)) {
                return isset($matches[1]) ? explode(' ', $matches[1]) : [];
            }
        }

        return [];
    }

    /** @return list<string> */
    public function fetch(string $uid, string $items): array
    {
        if (! ctype_digit($uid)) {
            throw new RuntimeException('Invalid Gmail message identifier.');
        }

        return $this->command('UID FETCH '.$uid.' ('.$items.')');
    }

    /** @return list<string> */
    private function command(string $command): array
    {
        $tag = 'A'.++$this->sequence;
        $this->write($tag.' '.$command."\r\n");
        $response = [];
        while (true) {
            $line = $this->readLine();
            if (str_starts_with($line, '+')) {
                $this->write("\r\n");

                continue;
            }
            if (str_starts_with($line, $tag.' ')) {
                if (! str_starts_with($line, $tag.' OK ')) {
                    throw new RuntimeException('Gmail rejected the request.');
                }

                return $response;
            }
            $response[] = $line;
            if (preg_match('/\{([0-9]+)\}\r?\n$/', $line, $matches)) {
                $length = (int) $matches[1];
                if ($length > 10485760) {
                    throw new RuntimeException('Gmail message exceeds the size limit.');
                }
                $literal = '';
                while (strlen($literal) < $length) {
                    $remaining = $length - strlen($literal);
                    if ($remaining <= 0) {
                        break;
                    }
                    $chunk = fread($this->stream, min(65536, $remaining));
                    if ($chunk === false || $chunk === '') {
                        throw new RuntimeException('Gmail connection ended while reading a message.');
                    }
                    $literal .= $chunk;
                }
                $response[] = $literal;
            }
        }
    }

    private function readLine(): string
    {
        $line = '';
        do {
            $part = fgets($this->stream, 65537);
            if ($part === false) {
                throw new RuntimeException('Gmail connection ended unexpectedly.');
            }
            $line .= $part;
            if (strlen($line) > 16777216) {
                throw new RuntimeException('Gmail response exceeds the size limit.');
            }
        } while (! str_ends_with($part, "\n"));

        return $line;
    }

    private function write(string $data): void
    {
        while ($data !== '') {
            $written = fwrite($this->stream, $data);
            if ($written === false || $written === 0) {
                throw new RuntimeException('Could not write to Gmail.');
            }
            $data = substr($data, $written);
        }
    }

    public function __destruct()
    {
        if (is_resource($this->stream)) {
            fclose($this->stream);
        }
    }
}
