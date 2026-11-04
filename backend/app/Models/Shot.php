<?php

namespace App\Models;

use App\Support\Media;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Shot extends Model
{
    /** Un seul décor par showroom : la voiture « tourne » devant le même fond. */
    public const DEFAULTS = [
        ['main', 'Le showroom'],
    ];

    /** Angles possibles d'une photo extérieure (reconnus par l'IA ou choisis à la main). */
    public const KEYS = ['front_left', 'front', 'front_right', 'right', 'rear_right', 'rear', 'rear_left', 'left'];

    /** Zone au sol : hauteur de la voiture en % de la hauteur de l'image, distance au bas, décalage horizontal. */
    public const DEFAULT_PLACEMENT = ['height' => 42, 'bottom' => 12, 'offset_x' => 0];

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
        return Photo::VIEWS[$key] ?? ($key === 'main' ? 'Le showroom' : 'Extérieur');
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
            'placement' => array_intersect_key(array_merge(self::DEFAULT_PLACEMENT, $this->placement ?? []), self::DEFAULT_PLACEMENT),
            'configured' => (bool) $this->image_path,
            'overlay_url' => url('/api/v1/shots/'.$this->id.'/overlay'),
        ];
    }
}
