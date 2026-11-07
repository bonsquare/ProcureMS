<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A message a user sent to Customer Services; the bot forwards it to Telegram and a reply there is saved as a SupportReply. */
class SupportMessage extends Model
{
    protected $fillable = ['organization_id', 'school_id', 'user_id', 'body', 'telegram_message_id', 'telegram_status', 'status', 'type', 'subject', 'assigned_name', 'assigned_telegram_id', 'resolved_at'];

    protected $attributes = ['status' => 'pending'];

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }

    public function user()
    {
        return $this->belongsTo(User::class)->withoutGlobalScopes();
    }

    public function school()
    {
        return $this->belongsTo(School::class)->withoutGlobalScopes();
    }

    public function replies()
    {
        return $this->hasMany(SupportReply::class)->orderBy('id');
    }
}
