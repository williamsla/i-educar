<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IdiarioSyncState extends Model
{
    public const IDLE = 'idle';

    public const QUEUED = 'queued';

    public const SYNCING = 'syncing';

    public const COMPLETED = 'completed';

    public const FAILED = 'failed';

    protected $fillable = [
        'requested_at',
        'status',
        'done_percentage',
        'last_checked_at',
        'last_error',
        'dismissed_at',
        'finished_at',
        'diary_started_at',
        'sync_again',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'last_checked_at' => 'datetime',
        'dismissed_at' => 'datetime',
        'finished_at' => 'datetime',
        'diary_started_at' => 'datetime',
        'sync_again' => 'boolean',
    ];
}
