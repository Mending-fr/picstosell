<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Invitation à rejoindre picstosell : un lien à usage unique, envoyé par e-mail ou copié à la main.
 */
class Invitation extends Model
{
    public const DAYS = 7;

    public const ROLE_LABELS = [
        'super_admin' => 'Admin Mending',
        'manager' => 'Gérant',
        'photographer' => 'Photographe',
    ];

    protected $fillable = ['token', 'email', 'name', 'role', 'garage_id', 'invited_by', 'emailed_at', 'expires_at', 'accepted_at'];

    protected function casts(): array
    {
        return [
            'emailed_at' => 'datetime',
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    public function garage(): BelongsTo
    {
        return $this->belongsTo(Garage::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function scopePending(Builder $q): void
    {
        $q->whereNull('accepted_at');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function url(): string
    {
        return rtrim(config('app.url'), '/').'/app/invitation/'.$this->token;
    }

    public function roleLabel(): string
    {
        return self::ROLE_LABELS[$this->role] ?? $this->role;
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'name' => $this->name,
            'role' => $this->role,
            'role_label' => $this->roleLabel(),
            'garage_id' => $this->garage_id,
            'garage_name' => $this->garage?->name,
            'invited_by' => $this->inviter?->name,
            'link' => $this->url(),
            'emailed_at' => $this->emailed_at?->toIso8601String(),
            'expires_at' => $this->expires_at->toIso8601String(),
            'expired' => $this->isExpired(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
