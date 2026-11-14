<?php

namespace App\Services;

use Symfony\Component\Mime\Address;

class GmailMessageParser
{
    /** @return array{message_id: string, from: string, to: list<string>, subject: string, body: string} */
    public function parse(string $raw): array
    {
        [$headers, $body] = $this->split($raw);
        $headers = preg_replace('/\r?\n[ \t]+/', ' ', $headers);
        preg_match('/^From:\s*([^\r\n]+)/im', $headers, $fromHeader);
        preg_match('/^To:\s*([^\r\n]+)/im', $headers, $toHeader);
        preg_match('/^Cc:\s*([^\r\n]+)/im', $headers, $ccHeader);
        preg_match('/^Message-ID:\s*([^\r\n]+)/im', $headers, $idHeader);
        preg_match('/^Subject:\s*([^\r\n]+)/im', $headers, $subjectHeader);
        $from = $this->addresses($fromHeader[1] ?? '');
        $to = array_merge($this->addresses($toHeader[1] ?? ''), $this->addresses($ccHeader[1] ?? ''));
        $parts = $this->textParts($headers, $body);

        $plain = $parts['plain'] ?? null;

        return [
            'message_id' => trim($idHeader[1] ?? '', " <>\t\r\n"),
            'from' => $from[0] ?? '',
            'to' => $to,
            'subject' => trim(mb_decode_mimeheader($subjectHeader[1] ?? '')),
            'body' => $plain !== null ? self::readableBody($plain) : self::htmlToText($parts['html'] ?? ''),
        ];
    }

    public static function readableBody(string $body): string
    {
        if (preg_match('/^\s*(?:<!doctype\s+html\b|<html\b)/i', $body)) {
            return self::htmlToText($body);
        }

        return self::normalizeBlankLines($body);
    }

    private static function htmlToText(string $html): string
    {
        $html = preg_replace('/<(head|script|style)\b[^>]*>.*?(?:<\/\1\s*>|$)/is', '', $html);
        $html = preg_replace('/<(?:br\b[^>]*|\/(?:p|div|tr|li|h[1-6])\s*)>/i', "\n", $html);
        $html = preg_replace('/<\/t[dh]\s*>/i', ' ', $html);
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[\t \x{00A0}]+/u', ' ', $text);
        $text = preg_replace('/ *\R */u', "\n", $text);

        return self::normalizeBlankLines($text);
    }

    private static function normalizeBlankLines(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/\n[\t \x{00A0}]*(?=\n)/u', "\n", $text);

        return trim(preg_replace('/\n{3,}/', "\n\n", $text));
    }

    /** @return list<string> */
    private function addresses(string $header): array
    {
        $result = [];
        foreach (preg_split('/,(?=(?:[^"]*"[^"]*")*[^"]*$)/', $header) as $address) {
            try {
                $result[] = strtolower(Address::create(trim($address))->getAddress());
            } catch (\Throwable) {
                continue;
            }
        }

        return $result;
    }

    /** @return array{0: string, 1: string} */
    private function split(string $raw): array
    {
        $parts = preg_split('/\r?\n\r?\n/', $raw, 2);

        return [$parts[0], $parts[1] ?? ''];
    }

    /** @return array{plain?: string, html?: string} */
    private function textParts(string $headers, string $body, int $depth = 0): array
    {
        if ($depth > 12) {
            return [];
        }
        $headers = preg_replace('/\r?\n[ \t]+/', ' ', $headers);
        preg_match('/^Content-Type:\s*([^\r\n]+)/im', $headers, $typeMatch);
        $type = $typeMatch[1] ?? 'text/plain';
        preg_match('/^Content-Disposition:\s*([^\r\n]+)/im', $headers, $dispositionMatch);
        $disposition = $dispositionMatch[1] ?? '';
        if (preg_match('/^attachment\b/i', $disposition) || preg_match('/(?:^|;)\s*(?:filename|name)\s*=/i', $type.';'.$disposition)) {
            return [];
        }
        if (preg_match('/^multipart\//i', $type) && preg_match('/boundary\s*=\s*(?:"([^"]+)"|([^;\s]+))/i', $type, $boundaryMatch)) {
            $boundary = $boundaryMatch[1] !== '' ? $boundaryMatch[1] : $boundaryMatch[2];
            $segments = preg_split('/^--'.preg_quote($boundary, '/').'(?:--)?[ \t]*\r?$/m', $body);
            $hasClosingBoundary = preg_match('/^--'.preg_quote($boundary, '/').'--[ \t]*\r?$/m', $body) === 1;
            $result = [];
            foreach (array_slice($segments, 1, $hasClosingBoundary ? -1 : null) as $segment) {
                if (trim($segment) === '') {
                    continue;
                }
                [$partHeaders, $partBody] = $this->split(ltrim($segment, "\r\n"));
                $result += $this->textParts($partHeaders, $partBody, $depth + 1);
            }

            return $result;
        }
        if (! preg_match('/^text\/(plain|html)\b/i', $type, $kind)) {
            return [];
        }
        preg_match('/^Content-Transfer-Encoding:\s*([^\s]+)/im', $headers, $encoding);
        $body = match (strtolower($encoding[1] ?? '')) {
            'base64' => base64_decode($body, true) ?: '',
            'quoted-printable' => quoted_printable_decode($body),
            default => $body,
        };
        if (preg_match('/charset\s*=\s*(?:"([^"]+)"|([^;\s]+))/i', $type, $charsetMatch)) {
            $charset = $charsetMatch[1] !== '' ? $charsetMatch[1] : $charsetMatch[2];
            try {
                $body = mb_convert_encoding($body, 'UTF-8', $charset);
            } catch (\ValueError) {
                return [];
            }
        }

        return [strtolower($kind[1]) => $body];
    }
}
