<?php

namespace App\Models;

use App\Support\Media;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Inventaire du parc : les plaques lues pendant un tour, comparées aux voitures du garage. */
class Inventory extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['finished_at' => 'datetime'];
    }

    public function garage(): BelongsTo
    {
        return $this->belongsTo(Garage::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function frames(): HasMany
    {
        return $this->hasMany(InventoryFrame::class);
    }

    public function plates(): HasMany
    {
        return $this->hasMany(InventoryPlate::class);
    }

    /** Voitures du parc à retrouver sur le terrain : ni vendues ni archivées. */
    public function expectedVehicles()
    {
        return $this->garage->vehicles()->whereNotIn('status', ['sold', 'archived'])->get();
    }

    public function summary(): array
    {
        $plates = $this->plates()->where('dismissed', false)->get();
        $expected = $this->expectedVehicles();
        $seenIds = $plates->pluck('vehicle_id')->filter()->unique();

        return [
            'id' => $this->id,
            'status' => $this->status,
            'user' => $this->user?->name,
            'created_at' => $this->created_at?->toIso8601String(),
            'finished_at' => $this->finished_at?->toIso8601String(),
            'frames' => $this->frames()->count(),
            'pending_frames' => $this->frames()->where('status', 'pending')->count(),
            'found' => $expected->whereIn('id', $seenIds)->count(),
            'unknown' => $plates->whereNull('vehicle_id')->count(),
            'missing' => $expected->whereNotIn('id', $seenIds)->count(),
        ];
    }

    /** Détail : les trois listes. */
    public function toApi(): array
    {
        $plates = $this->plates()->with(['vehicle', 'frame'])->orderBy('plate')->get();
        $expected = $this->expectedVehicles()->sortBy('plate');
        $kept = $plates->where('dismissed', false);
        $seenIds = $kept->pluck('vehicle_id')->filter()->unique()->all();
        $car = fn (Vehicle $v) => ['id' => $v->id, 'plate' => $v->plate, 'label' => trim($v->label().' '.($v->version ?? '')), 'status' => $v->status, 'cover_url' => Media::url($v->coverPath())];
        $plate = fn (InventoryPlate $p) => [
            'id' => $p->id,
            'plate' => $p->plate,
            'seen' => $p->seen,
            'sure' => $p->sure,
            'dismissed' => $p->dismissed,
            'image_url' => $p->frame ? Media::url($p->frame->path) : null,
            'vehicle' => $p->vehicle ? $car($p->vehicle) : null,
            // Lu un peu différemment de la plaque enregistrée (0 pour O, un caractère d'écart…).
            'corrected' => $p->vehicle && $p->vehicle->plate !== $p->plate,
        ];

        return $this->summary() + [
            'found_list' => $kept->whereNotNull('vehicle_id')->map($plate)->values(),
            'unknown_list' => $kept->whereNull('vehicle_id')->map($plate)->values(),
            'missing_list' => $expected->filter(fn ($v) => ! in_array($v->id, $seenIds, true))->map($car)->values(),
            'dismissed_list' => $plates->where('dismissed', true)->map($plate)->values(),
        ];
    }
}
