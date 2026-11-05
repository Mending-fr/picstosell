<?php

namespace App\Models;

use App\Support\Media;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Garage extends Model
{
    public const STATUSES = ['pilot', 'onboarding', 'demo', 'active', 'paused'];

    public const DEFAULT_SETTINGS = [
        'hide_plate' => true,
        'add_logo' => false,
        'review_before_publish' => true,
        'export_size' => 'marketplace',
        // Compteur « voitures du mois / quota » dans le menu du garage (réglé par Mending)
        'show_usage' => false,
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['settings' => 'array'];
    }

    protected static function booted(): void
    {
        static::creating(function (Garage $g) {
            if (! $g->slug) {
                $base = Str::slug($g->name) ?: 'garage';
                $slug = $base;
                $i = 2;
                while (static::where('slug', $slug)->exists()) {
                    $slug = $base.'-'.$i++;
                }
                $g->slug = $slug;
            }
            $g->settings = array_merge(self::DEFAULT_SETTINGS, $g->settings ?? []);
        });

        static::created(function (Garage $g) {
            $g->ensureShowroom();
            if ($g->isDemo()) {
                $g->fillDemoData();
            }
        });

        static::updated(function (Garage $g) {
            if ($g->wasChanged('status') && $g->isDemo()) {
                $g->fillDemoData();
            }
        });
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function showrooms(): HasMany
    {
        return $this->hasMany(Showroom::class);
    }

    public function aiConfig(): BelongsTo
    {
        return $this->belongsTo(AiConfig::class);
    }

    /** Garage de démonstration : les services externes (hors IA photo et texte) renvoient des données factices. */
    public function isDemo(): bool
    {
        return $this->status === 'demo';
    }

    /** Complète un garage de démo avec des coordonnées et des demandes fictives, sans écraser ce qui est saisi. */
    public function fillDemoData(): void
    {
        $this->fill(array_filter([
            'phone' => $this->phone ? null : '02 00 00 00 00',
            'email' => $this->email ? null : 'contact@garage-demo.test',
            'address' => $this->address ? null : '1 rue de la Démonstration',
            'city' => $this->city ? null : 'Valognes',
            'hours' => $this->hours ? null : "Lundi au vendredi : 8 h 30 - 18 h 30\nSamedi : 9 h - 12 h",
            'about' => $this->about ? null : "Garage de démonstration. Les coordonnées et les demandes de clients sont fictives.",
        ]))->saveQuietly();

        if ($this->leads()->doesntExist()) {
            $samples = [
                ['type' => 'vehicle', 'name' => 'Client démo 1', 'phone' => '06 00 00 00 01', 'message' => "Bonjour, la voiture est-elle toujours disponible ? Je peux passer l'essayer samedi."],
                ['type' => 'trade_in', 'name' => 'Client démo 2', 'phone' => '06 00 00 00 02', 'message' => 'Je voudrais faire reprendre ma voiture pour un SUV.', 'trade_in_plate' => 'AA-000-AA', 'trade_in_mileage' => 112000],
                ['type' => 'other', 'name' => 'Client démo 3', 'phone' => '06 00 00 00 03', 'message' => 'Proposez-vous un financement sur 48 mois ?'],
            ];
            foreach ($samples as $l) {
                $this->leads()->create($l + ['status' => 'new', 'source' => 'démo']);
            }
        }
    }

    public function setting(string $key): mixed
    {
        return ($this->settings ?? [])[$key] ?? self::DEFAULT_SETTINGS[$key] ?? null;
    }

    /**
     * Décor à utiliser : « own » (la photo du garage), « lib:ID » (un décor Mending),
     * ou null pour le choix du garage. Sans choix : sa photo s'il en a une, sinon le décor Mending par défaut.
     *
     * @return array{path: ?string, placement: array, key: string}
     */
    public function backdrop(?string $choice = null): array
    {
        $choice ??= $this->settings['backdrop'] ?? null;
        $shot = Shot::whereHas('showroom', fn ($q) => $q->where('garage_id', $this->id))
            ->orderByRaw("case when key = 'main' then 0 else 1 end")->orderBy('id')->first();
        $placement = array_merge(Shot::DEFAULT_PLACEMENT, array_intersect_key($shot?->placement ?? [], Shot::DEFAULT_PLACEMENT));

        if ($choice && str_starts_with($choice, 'lib:')) {
            $b = Backdrop::find((int) substr($choice, 4));
            if ($b) {
                return ['path' => $b->image_path, 'placement' => Shot::DEFAULT_PLACEMENT, 'key' => $choice];
            }
        }
        if (($choice === 'own' || ! $choice) && $shot?->image_path) {
            return ['path' => $shot->image_path, 'placement' => $placement, 'key' => 'own'];
        }
        $d = Backdrop::default();

        return $d
            ? ['path' => $d->image_path, 'placement' => Shot::DEFAULT_PLACEMENT, 'key' => 'lib:'.$d->id]
            : ['path' => null, 'placement' => $placement, 'key' => 'none'];
    }

    public function ensureShowroom(): Showroom
    {
        $showroom = $this->showrooms()->first() ?? $this->showrooms()->create(['name' => 'Showroom']);
        foreach (Shot::DEFAULTS as $i => [$key, $label]) {
            $showroom->shots()->firstOrCreate(['key' => $key], [
                'label' => $label,
                'position' => $i,
                'placement' => Shot::DEFAULT_PLACEMENT,
            ]);
        }

        return $showroom;
    }

    public function initials(): string
    {
        $words = preg_split('/\s+/', trim(preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $this->name)));
        $letters = array_map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)), array_slice(array_filter($words), 0, 2));

        return implode('', $letters) ?: 'G';
    }

    public function color(): string
    {
        $palette = ['#1F4FD8', '#0F7A4A', '#A16207', '#7C3AED', '#B8222A', '#0E7490'];

        return $palette[$this->id % count($palette)];
    }

    public function siteUrl(): string
    {
        return $this->domain ? 'https://'.$this->domain : url('/s/'.$this->slug);
    }

    public function todoCount(): int
    {
        $v = $this->vehicles()->whereIn('status', ['to_review', 'ready'])->count();
        $sold = $this->vehicles()->where('status', 'sold')->whereNotNull('published_at')->count();

        return $v + $sold + $this->leads()->where('status', 'new')->count();
    }

    public function summary(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'status' => $this->status,
            'initials' => $this->initials(),
            'color' => $this->color(),
            'todo_count' => $this->todoCount(),
            'is_demo' => $this->isDemo(),
        ];
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'status' => $this->status,
            'city' => $this->city,
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,
            'hours' => $this->hours,
            'about' => $this->about,
            'brand_color' => $this->brand_color,
            'logo_url' => Media::url($this->logo_path),
            'domain' => $this->domain,
            'vehicle_quota' => $this->vehicle_quota,
            'site_url' => $this->siteUrl(),
            'settings' => array_merge(self::DEFAULT_SETTINGS, $this->settings ?? []),
            'ai_config_id' => $this->ai_config_id,
            'is_demo' => $this->isDemo(),
        ];
    }
}
