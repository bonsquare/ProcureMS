<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A message a user sent to Customer Services; the bot forwards it to Telegram and a reply there is saved as a SupportReply. */
class SupportMessage extends Model
{
    protected $fillable = ['organization_id', 'school_id', 'user_id', 'body', 'telegram_message_id', 'telegram_status', 'status', 'type', 'subject', 'assigned_name', 'assigned_telegram_id', 'resolved_at', 'telegram_thread_id', 'telegram_thread_card_id'];

    protected $attributes = ['status' => 'pending'];

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }

    /** The ticket number: the date and time the request was made, then its number (SUP-20261107-1745-0007). */
    public function getTicketNoAttribute(): string
    {
        return 'SUP-'.$this->created_at->format('Ymd-Hi').'-'.str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }

    public function getFiledAtAttribute(): string
    {
        return $this->created_at->format('M d, Y g:i A');
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

    /** The files of the first message of the request (the ones of an answer belong to that answer). */
    public function attachments()
    {
        return $this->hasMany(SupportAttachment::class)->whereNull('support_reply_id')->orderBy('id');
    }
}
