<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManualPlacementLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'student_id',
        'from_amphitheater',
        'from_seat',
        'to_amphitheater',
        'to_seat',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
