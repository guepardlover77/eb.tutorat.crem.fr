<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Amphitheater extends Model
{
    protected $fillable = ['name', 'capacity', 'sort_order', 'seat_layout'];

    protected $casts = [
        'seat_layout' => 'array',
    ];

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function placedCount(): int
    {
        return $this->students()->count();
    }

    public function seatCount(): int
    {
        return $this->seat_layout ? count($this->seat_layout) : $this->capacity;
    }

    public function fillRate(): float
    {
        $total = $this->seatCount();
        if ($total === 0) return 0;
        return round(($this->placedCount() / $total) * 100, 1);
    }
}
