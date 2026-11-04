<?php

namespace App\Models;

use App\Support\Media;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Photo extends Model
{
    public const KINDS = ['exterior', 'interior', 'detail'];

    protected $guarded = ['id'];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function renders(): HasMany
    {
        return $this->hasMany(Render::class);
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'vehicle_id' => $this->vehicle_id,
            'shot_key' => $this->shot_key,
            'kind' => $this->kind,
            'url' => Media::url($this->path),
            'thumb_url' => Media::url($this->thumb_path ?? $this->path),
            'position' => $this->position,
        ];
    }
}
