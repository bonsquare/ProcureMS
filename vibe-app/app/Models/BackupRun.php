<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BackupRun extends Model
{
    public const SUCCESS = 'success';

    public const FAILED = 'failed';

    protected $fillable = ['type', 'status', 'size', 'drive_file_id', 'file_name', 'created_by', 'error'];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function downloads(): HasMany
    {
        return $this->hasMany(BackupDownload::class)->latest();
    }

    public function isDownloadable(): bool
    {
        return $this->status === self::SUCCESS && filled($this->drive_file_id);
    }
}
