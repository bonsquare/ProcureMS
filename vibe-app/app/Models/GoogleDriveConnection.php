<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoogleDriveConnection extends Model
{
    public const CONNECTED = 'connected';

    public const NEEDS_RECONNECT = 'needs_reconnect';

    protected $fillable = ['user_id', 'google_email', 'access_token', 'refresh_token', 'expires_at', 'root_folder_id', 'folder_ids', 'status', 'connected_at'];

    protected $hidden = ['access_token', 'refresh_token'];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'expires_at' => 'datetime',
            'connected_at' => 'datetime',
            'folder_ids' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isConnected(): bool
    {
        return $this->status === self::CONNECTED;
    }
}
