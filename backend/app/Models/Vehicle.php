<?php

namespace App\Models;

use App\Support\Media;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vehicle extends Model
{
    use SoftDeletes;

    public const STATUSES = ['to_photograph', 'processing', 'to_review', 'ready', 'online', 'sold', 'archived'];

    public const FUELS = ['essence', 'diesel', 'hybride', 'hybride_rechargeable', 'electrique', 'gpl', 'autre'];

    public const GEARBOXES = ['manuelle', 'automatique'];

    public const FUEL_LABELS = [
        'essence' => 'Essence', 'diesel' => 'Diesel', 'hybride' => 'Hybride',
        'hybride_rechargeable' => 'Hybride rechargeable', 'electrique' => 'Électrique',
        'gpl' => 'GPL', 'autre' => 'Autre',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'first_registration' => 'date',
            'equipment' => 'array',
            'published_at' => 'datetime',
            'sold_at' => 'datetime',
        ];
    }

    public function garage(): BelongsTo
    {
        return $this->belongsTo(Garage::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class)->orderBy('position')->orderBy('id');
    }

    public function renders(): HasMany
    {
        return $this->hasMany(Render::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('status', 'online')->whereNotNull('published_at');
    }

    /** Normalise une plaque française : AB123CD -> AB-123-CD. */
    public static function normalizePlate(string $plate): string
    {
        $p = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $plate));
        if (preg_match('/^([A-Z]{2})(\d{3})([A-Z]{2})$/', $p, $m)) {
            return "{$m[1]}-{$m[2]}-{$m[3]}";
        }

        return $p;
    }

    public static function normalizeFuel(?string $v): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }
        $s = mb_strtolower(trim($v));
        $s = strtr($s, ['é' => 'e', 'è' => 'e']);

        return match (true) {
            in_array($s, self::FUELS, true) => $s,
            str_contains($s, 'rechargeable') || str_contains($s, 'plug') => 'hybride_rechargeable',
            str_contains($s, 'hyb') || str_contains($s, 'hyd') || str_contains($s, 'e-tech') || str_contains($s, 'mild') || str_contains($s, 'myld') => 'hybride',
            str_contains($s, 'diesel') || str_contains($s, 'gasoil') || str_contains($s, 'gazole') || str_contains($s, 'dci') => 'diesel',
            str_contains($s, 'elec') => 'electrique',
            str_contains($s, 'gpl') || str_contains($s, 'lpg') => 'gpl',
            str_contains($s, 'ess') || str_contains($s, 'petrol') || str_contains($s, 'tce') => 'essence',
            default => 'autre',
        };
    }

    public static function normalizeGearbox(?string $v): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }
        $s = mb_strtolower(trim($v));

        return str_starts_with($s, 'auto') || str_contains($s, 'edc') || str_contains($s, 'cvt') ? 'automatique' : 'manuelle';
    }

    public function label(): string
    {
        return trim(($this->make ?? '').' '.($this->model ?? '')) ?: $this->plate;
    }

    public function daysInStock(): int
    {
        return (int) $this->created_at?->startOfDay()->diffInDays(now()->startOfDay());
    }

    /** Photo de couverture : rendu validé de l'avant gauche, sinon premier rendu validé, sinon première photo. */
    public function coverPath(): ?string
    {
        $renders = $this->relationLoaded('renders') ? $this->renders : $this->renders()->get();
        $approved = $renders->whereNull('comparison_id')->where('status', 'approved')->filter(fn ($r) => $r->output_path);
        $front = $approved->first(fn ($r) => $r->photo?->shot_key === 'front_left');
        if ($front || $approved->isNotEmpty()) {
            return ($front ?? $approved->first())->output_path;
        }
        $photos = $this->relationLoaded('photos') ? $this->photos : $this->photos()->get();

        return $photos->first()?->thumb_path ?? $photos->first()?->path;
    }

    /** Photos à montrer publiquement, dans l'ordre : rendus validés, sinon photo d'origine conservée, puis photos libres. */
    public function publicImages(): array
    {
        $this->loadMissing(['photos', 'renders']);
        $latest = $this->latestRendersByPhoto();
        $images = [];
        foreach ($this->photos as $photo) {
            $r = $latest[$photo->id] ?? null;
            if ($photo->kind === 'exterior') {
                if ($r && $r->status === 'approved' && $r->output_path) {
                    $images[] = ['url' => Media::url($r->output_path), 'label' => Shot::labelFor($photo->shot_key)];
                } elseif (! $r || $r->status === 'kept_original') {
                    $images[] = ['url' => Media::url($photo->path), 'label' => Shot::labelFor($photo->shot_key)];
                }
            } else {
                $images[] = ['url' => Media::url($photo->path), 'label' => $photo->kind === 'interior' ? 'Intérieur' : 'Détail'];
            }
        }

        return $images;
    }

    /** Dernier rendu (hors comparatif) pour chaque photo. */
    public function latestRendersByPhoto(): array
    {
        $out = [];
        foreach ($this->renders->whereNull('comparison_id')->sortBy('id') as $r) {
            $out[$r->photo_id] = $r;
        }

        return $out;
    }

    /** Recalcule le statut à partir des photos et des rendus (sans toucher aux statuts online/sold/archived). */
    public function refreshStatus(): void
    {
        if (in_array($this->status, ['online', 'sold', 'archived'], true)) {
            return;
        }
        $this->load(['photos', 'renders']);
        $exterior = $this->photos->where('kind', 'exterior');
        if ($exterior->isEmpty()) {
            $this->status = 'to_photograph';
        } else {
            $latest = $this->latestRendersByPhoto();
            $states = $exterior->map(fn ($p) => ($latest[$p->id] ?? null)?->status)->values();
            if ($states->filter()->isEmpty()) {
                $this->status = 'to_photograph'; // photos reçues, traitement pas encore lancé
            } elseif ($states->contains(null)) {
                $this->status = 'processing';
            } elseif ($states->contains(fn ($s) => in_array($s, ['pending', 'processing'], true))) {
                $this->status = 'processing';
            } elseif ($states->contains(fn ($s) => in_array($s, ['to_review', 'failed'], true))) {
                $this->status = 'to_review';
            } else {
                $this->status = 'ready';
            }
        }
        $this->save();
    }

    public function toApi(bool $full = false): array
    {
        $data = [
            'id' => $this->id,
            'plate' => $this->plate,
            'vin' => $this->vin,
            'make' => $this->make,
            'model' => $this->model,
            'version' => $this->version,
            'first_registration' => $this->first_registration?->toDateString(),
            'fuel' => $this->fuel,
            'gearbox' => $this->gearbox,
            'power_hp' => $this->power_hp,
            'fiscal_hp' => $this->fiscal_hp,
            'color' => $this->color,
            'doors' => $this->doors,
            'seats' => $this->seats,
            'mileage' => $this->mileage,
            'price' => $this->price,
            'description' => $this->description,
            'equipment' => $this->equipment ?? [],
            'history_url' => $this->history_url,
            'status' => $this->status,
            'photographer_id' => $this->photographer_id,
            'days_in_stock' => $this->daysInStock(),
            'leads_count' => $this->leads_count ?? $this->leads()->count(),
            'photos_count' => $this->photos_count ?? $this->photos()->count(),
            'exterior_photos_count' => $this->exterior_photos_count ?? $this->photos()->where('kind', 'exterior')->count(),
            'cover_url' => Media::url($this->coverPath()),
            'published_at' => $this->published_at?->toIso8601String(),
            'sold_at' => $this->sold_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
        if ($full) {
            $this->loadMissing(['photos', 'renders.photo']);
            $data['photos'] = $this->photos->map->toApi()->values();
            $data['renders'] = collect($this->latestRendersByPhoto())->map->toApi()->values();
        }

        return $data;
    }
}
