<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportReply extends Model
{
    protected $fillable = ['support_message_id', 'body', 'telegram_message_id', 'sender', 'user_id'];

    public function attachments()
    {
        return $this->hasMany(SupportAttachment::class)->orderBy('id');
    }
}
