<?php

namespace App\Models;

use App\Support\Media;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Render extends Model
{
    public const STATUSES = ['pending', 'processing', 'to_review', 'approved', 'failed', 'kept_original'];

    public const RATINGS = ['publishable', 'redo', 'unusable'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['cost_usd' => 'decimal:4'];
    }

    public function photo(): BelongsTo
    {
        return $this->belongsTo(Photo::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function aiConfig(): BelongsTo
    {
        return $this->belongsTo(AiConfig::class);
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'photo_id' => $this->photo_id,
            'vehicle_id' => $this->vehicle_id,
            'shot_key' => $this->photo?->shot_key,
            'status' => $this->status,
            'url' => Media::url($this->output_path),
            'provider' => $this->provider,
            'model' => $this->model,
            'method' => $this->method,
            'cost_usd' => number_format((float) $this->cost_usd, 3, '.', ''),
            'duration_ms' => $this->duration_ms,
            'error' => $this->error,
            'rating' => $this->rating,
            'attempt' => $this->attempt,
            'ai_config_id' => $this->ai_config_id,
            'comparison_id' => $this->comparison_id,
        ];
    }
}
