<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyncLog extends Model
{
    protected $fillable = [
        'started_at',
        'finished_at',
        'status',
        'new_records',
        'updated_records',
        'error_message',
        'continuation_token',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];
}
