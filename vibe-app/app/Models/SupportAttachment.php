<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A photo or PDF on a support request. The file stays in the sender's own Google Drive; only its link is kept here. */
class SupportAttachment extends Model
{
    protected $fillable = ['support_message_id', 'support_reply_id', 'user_id', 'drive_file_id', 'name', 'mime', 'size'];
}
