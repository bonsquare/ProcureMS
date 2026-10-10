<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A pointer to a file in its owner's Google Drive. Only the owner ever lists or opens it. */
class DriveFile extends Model
{
    protected $fillable = ['user_id', 'name', 'drive_file_id', 'mime', 'size'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
