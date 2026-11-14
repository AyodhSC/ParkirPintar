<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParkingRecord extends Model
{
    protected $fillable = [
        'parking_area_id', 'plate_number', 'vehicle_type',
        'check_in_at', 'check_out_at', 'fee', 'status',
    ];

    protected $casts = [
        'check_in_at' => 'datetime',
        'check_out_at' => 'datetime',
    ];

    public function area(): BelongsTo
    {
        return $this->belongsTo(ParkingArea::class, 'parking_area_id');
    }
}
