<?php

namespace App\Jobs;

use App\Models\InventoryFrame;
use App\Services\Vehicle\InventoryMatcher;
use App\Services\Vehicle\PlateReader;
use App\Support\Media;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/** Lit les plaques d'une image d'inventaire. */
class ReadInventoryFrame implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 120;

    public function __construct(public int $frameId) {}

    public function handle(PlateReader $reader, InventoryMatcher $matcher): void
    {
        $frame = InventoryFrame::with('inventory.garage')->find($this->frameId);
        if (! $frame || $frame->status !== 'pending') {
            return;
        }
        try {
            $found = $reader->read(Media::get($frame->path));
            $matcher->record($frame->inventory, $frame, $found);
            $frame->update(['status' => 'done']);
        } catch (Throwable $e) {
            report($e);
            $frame->update(['status' => 'failed']);
        }
    }
}
