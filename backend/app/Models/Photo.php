<?php

namespace App\Models;

use App\Support\Media;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Photo extends Model
{
    public const KINDS = ['exterior', 'interior', 'detail'];

    /** Angles extérieurs, dans l'ordre où ils s'affichent sur l'annonce (le tour de la voiture). */
    public const VIEWS = [
        'front_left' => 'Avant gauche',
        'front' => 'De face',
        'front_right' => 'Avant droit',
        'right' => 'Côté droit',
        'rear_right' => 'Arrière droit',
        'rear' => 'Arrière',
        'rear_left' => 'Arrière gauche',
        'left' => 'Côté gauche',
    ];

    /** Libellés proposés pour l'intérieur et les détails, dans l'ordre d'affichage. */
    public const INTERIOR_LABELS = ['Tableau de bord', 'Compteur', 'Places avant', 'Places arrière', 'Coffre', 'Écran', 'Intérieur'];

    public const DETAIL_LABELS = ['Jante', 'Pneu', 'Phare', 'Défaut', 'Équipement', 'Document', 'Détail'];

    /** Rang d'affichage : extérieur dans l'ordre du tour, puis intérieur, puis détails. */
    public static function rank(string $kind, ?string $view, ?string $label): int
    {
        if ($kind === 'exterior') {
            $i = array_search($view, array_keys(self::VIEWS), true);

            return $i === false ? 50 : $i * 2;
        }
        $list = $kind === 'interior' ? self::INTERIOR_LABELS : self::DETAIL_LABELS;
        $i = array_search($label, $list, true);

        return ($kind === 'interior' ? 100 : 200) + ($i === false ? 40 : $i * 2);
    }

    public function displayLabel(): string
    {
        return match ($this->kind) {
            'exterior' => self::VIEWS[$this->shot_key] ?? 'Extérieur',
            'interior' => $this->label ?: 'Intérieur',
            default => $this->label ?: 'Détail',
        };
    }

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
            'label' => $this->label,
            'display_label' => $this->displayLabel(),
            'classified' => $this->classified,
            'url' => Media::url($this->path),
            'thumb_url' => Media::url($this->thumb_path ?? $this->path),
            'position' => $this->position,
        ];
    }
}
