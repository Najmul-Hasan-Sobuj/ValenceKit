<?php

namespace App\Models;

use App\Services\GmailMessageParser;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['body', 'rfc_message_id', 'gmail_message_id', 'incoming', 'notification'])]
#[Hidden(['gmail_message_id'])]
class ContactReply extends Model
{
    /** @return Attribute<string, string> */
    protected function body(): Attribute
    {
        return Attribute::make(
            get: function (string $value, array $attributes): string {
                if (empty($attributes['gmail_message_id'])) {
                    return $value;
                }
                $body = GmailMessageParser::readableBody($value);

                return $body === '' && $value !== '' ? '[Open message to read]' : $body;
            },
        );
    }
}
