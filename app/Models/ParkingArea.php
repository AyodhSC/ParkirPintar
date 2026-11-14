<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ParkingArea extends Model
{
    protected $fillable = ['name', 'capacity_motor', 'capacity_mobil'];

    public function records(): HasMany
    {
        return $this->hasMany(ParkingRecord::class);
    }
}
