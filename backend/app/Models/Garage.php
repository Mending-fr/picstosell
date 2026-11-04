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

    public function setting(string $key): mixed
    {
        return ($this->settings ?? [])[$key] ?? self::DEFAULT_SETTINGS[$key] ?? null;
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
        ];
    }
}
