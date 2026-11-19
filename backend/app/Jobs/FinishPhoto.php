<?php

namespace App\Jobs;

use App\Models\Photo;
use App\Services\Imaging;
use App\Services\PhotoFinisher;
use App\Support\Media;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Prépare la version publiée d'une photo gardée telle quelle : plaque cachée et logo, selon les réglages du garage. */
class FinishPhoto implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(public int $photoId) {}

    public function handle(PhotoFinisher $finisher): void
    {
        $photo = Photo::with('vehicle.garage')->find($this->photoId);
        $garage = $photo?->vehicle?->garage;
        if (! $photo || ! $garage) {
            return;
        }
        if (! $garage->setting('hide_plate') && ! $garage->setting('add_logo')) {
            $photo->update(['published_path' => null]);

            return;
        }
        $img = $finisher->finish(Imaging::fit(Imaging::load(Media::get($photo->path)), 2048), $garage);
        $path = "garages/{$garage->id}/vehicles/{$photo->vehicle_id}/published/{$photo->id}.jpg";
        Media::put($path, Imaging::jpeg($img, 88));
        $photo->update(['published_path' => $path]);
    }
}
