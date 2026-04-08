<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TutoringMember extends Model
{
    protected $fillable = ['crem_number', 'first_name', 'last_name'];
}
