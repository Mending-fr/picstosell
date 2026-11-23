<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryPlate extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['sure' => 'boolean', 'dismissed' => 'boolean'];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function frame(): BelongsTo
    {
        return $this->belongsTo(InventoryFrame::class, 'frame_id');
    }
}
