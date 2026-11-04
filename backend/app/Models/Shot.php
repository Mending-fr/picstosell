<?php

namespace App\Models;

use App\Support\Media;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Shot extends Model
{
    /** Angles extérieurs par défaut, dans l'ordre de la prise de vue. */
    public const DEFAULTS = [
        ['front_left', 'Avant gauche'],
        ['front', 'De face'],
        ['front_right', 'Avant droit'],
        ['right', 'Côté droit'],
        ['rear', 'Arrière'],
        ['left', 'Côté gauche'],
    ];

    public const KEYS = ['front', 'front_left', 'front_right', 'left', 'right', 'rear', 'rear_left', 'rear_right'];

    public const DEFAULT_PLACEMENT = ['scale' => 66, 'bottom' => 12, 'offset_x' => 0];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['placement' => 'array'];
    }

    public function showroom(): BelongsTo
    {
        return $this->belongsTo(Showroom::class);
    }

    public static function labelFor(?string $key): string
    {
        foreach (self::DEFAULTS as [$k, $label]) {
            if ($k === $key) {
                return $label;
            }
        }

        return match ($key) {
            'rear_left' => 'Arrière gauche',
            'rear_right' => 'Arrière droit',
            null => 'Photo libre',
            default => $key,
        };
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'showroom_id' => $this->showroom_id,
            'key' => $this->key,
            'label' => $this->label,
            'position' => $this->position,
            'image_url' => Media::url($this->image_path),
            'placement' => array_merge(self::DEFAULT_PLACEMENT, $this->placement ?? []),
            'configured' => (bool) $this->image_path,
            'overlay_url' => url('/api/v1/shots/'.$this->id.'/overlay'),
        ];
    }
}
