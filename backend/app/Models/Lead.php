<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Lead extends Model
{
    public const TYPES = ['vehicle', 'trade_in', 'other'];

    protected $guarded = ['id'];

    public function garage(): BelongsTo
    {
        return $this->belongsTo(Garage::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class)->withTrashed();
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'message' => $this->message,
            'vehicle' => $this->vehicle ? ['id' => $this->vehicle->id, 'label' => $this->vehicle->label(), 'price' => $this->vehicle->price] : null,
            'trade_in' => $this->trade_in_plate || $this->trade_in_mileage ? ['plate' => $this->trade_in_plate, 'mileage' => $this->trade_in_mileage] : null,
            'status' => $this->status,
            'note' => $this->note,
            'source' => $this->source,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
