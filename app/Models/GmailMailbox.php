<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['email', 'refresh_token'])]
class GmailMailbox extends Model
{
    protected $hidden = ['refresh_token'];

    protected function casts(): array
    {
        return ['refresh_token' => 'encrypted'];
    }
}
