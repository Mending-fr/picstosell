<?php

namespace App\Services\Vehicle;

use App\Models\Inventory;
use App\Models\InventoryFrame;
use App\Models\InventoryPlate;
use Illuminate\Support\Facades\DB;

/** Range les plaques lues sur une image dans l'inventaire et les rapproche des voitures du parc. */
class InventoryMatcher
{
    /** @param list<array{plate:string, confidence:string}> $found */
    public function record(Inventory $inv, InventoryFrame $frame, array $found): void
    {
        if (! $found) {
            return;
        }
        $vehicles = $inv->expectedVehicles();
        foreach ($found as $f) {
            DB::transaction(function () use ($inv, $frame, $f, $vehicles) {
                $existing = InventoryPlate::where('inventory_id', $inv->id)->lockForUpdate()->get();
                // Même plaque, ou lecture d'un caractère différente d'une plaque déjà vue sur ce tour : on regroupe.
                $same = $existing->first(fn ($p) => $p->plate === $f['plate'])
                    ?? $existing->first(fn ($p) => PlateReader::similar($p->plate, $f['plate']));
                if ($same) {
                    $same->seen++;
                    if ($f['confidence'] === 'high' && ! $same->sure) {
                        $same->sure = true;
                        $same->frame_id = $frame->id;
                    }
                    $same->save();

                    return;
                }
                $vehicle = $vehicles->first(fn ($v) => $v->plate === $f['plate'])
                    ?? $vehicles->first(fn ($v) => PlateReader::similar($v->plate, $f['plate']));
                InventoryPlate::create([
                    'inventory_id' => $inv->id,
                    'plate' => $f['plate'],
                    'vehicle_id' => $vehicle?->id,
                    'frame_id' => $frame->id,
                    'sure' => $f['confidence'] === 'high',
                ]);
            });
        }
    }
}
