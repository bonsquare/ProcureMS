<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportReply extends Model
{
    protected $fillable = ['support_message_id', 'body', 'telegram_message_id'];
}
