<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'helloasso_item_id',
        'helloasso_order_id',
        'first_name',
        'last_name',
        'email',
        'tier_name',
        'crem_number',
        'crem_photo_url',
        'is_excluded',
        'recovery_option',
        'has_error',
        'error_message',
        'amphitheater_id',
        'seat_number',
        'is_manually_placed',
        'is_manually_edited',
        'is_present',
        'marked_present_at',
        'synced_at',
    ];

    protected $casts = [
        'is_excluded' => 'boolean',
        'has_error' => 'boolean',
        'is_manually_placed' => 'boolean',
        'is_manually_edited' => 'boolean',
        'is_present' => 'boolean',
        'seat_number' => 'string',
        'synced_at' => 'datetime',
        'marked_present_at' => 'datetime',
    ];

    public function amphitheater(): BelongsTo
    {
        return $this->belongsTo(Amphitheater::class);
    }

    public function manualPlacementLogs(): HasMany
    {
        return $this->hasMany(ManualPlacementLog::class)->orderByDesc('created_at');
    }

    public function scopeOrderBySeat($query)
    {
        return $query->orderByRaw("
            CASE
                WHEN seat_number = 'Table 1' THEN -2
                WHEN seat_number = 'Table 2' THEN -1
                WHEN seat_number IS NULL      THEN 999999
                ELSE CAST(seat_number AS INTEGER)
            END ASC
        ");
    }

    public function fullName(): string
    {
        return $this->first_name.' '.$this->last_name;
    }

    public function cremPrefix(): ?string
    {
        return $this->crem_number ? substr($this->crem_number, 0, 1) : null;
    }
}
