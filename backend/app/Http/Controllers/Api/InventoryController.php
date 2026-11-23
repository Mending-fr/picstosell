<?php

namespace App\Http\Controllers\Api;

use App\Jobs\ReadInventoryFrame;
use App\Models\Inventory;
use App\Services\Imaging;
use App\Services\Vehicle\InventoryMatcher;
use App\Services\Vehicle\PlateReader;
use App\Support\Media;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Inventaire du parc depuis l'appli : on envoie des images au fil du tour, le serveur y lit les plaques. */
class InventoryController extends Controller
{
    private const MAX_FRAMES = 900;

    public function index()
    {
        return ['data' => Inventory::where('garage_id', $this->garage()->id)->with('user', 'garage')->latest()->limit(30)->get()->map->summary()->values()];
    }

    public function store(Request $request)
    {
        $inv = Inventory::create(['garage_id' => $this->garage()->id, 'user_id' => $request->user()->id, 'status' => 'running']);

        return response()->json($inv->load('garage')->toApi(), 201);
    }

    public function show(int $id)
    {
        return $this->find($id)->toApi();
    }

    public function frame(Request $request, int $id)
    {
        $inv = $this->find($id);
        abort_if($inv->status !== 'running', 422, 'Cet inventaire est terminé.');
        abort_if($inv->frames()->count() >= self::MAX_FRAMES, 422, "Nombre maximum d'images atteint pour cet inventaire.");
        $request->validate(['file' => 'required|file|image|max:15360']);
        $img = Imaging::fit(Imaging::load($request->file('file')->get()), 1600);
        $path = "garages/{$inv->garage_id}/inventories/{$inv->id}/".Str::uuid().'.jpg';
        Media::put($path, Imaging::jpeg($img, 80));
        $frame = $inv->frames()->create(['path' => $path, 'status' => 'pending']);
        ReadInventoryFrame::dispatch($frame->id);

        return response()->json(['frame_id' => $frame->id] + $inv->summary(), 201);
    }

    /** Plaques lues par le téléphone lui-même (lecture sur l'appareil) : seulement les numéros, sans image. */
    public function reads(Request $request, int $id, InventoryMatcher $matcher)
    {
        $inv = $this->find($id);
        abort_if($inv->status !== 'running', 422, 'Cet inventaire est terminé.');
        $data = $request->validate([
            'plates' => 'required|array|min:1|max:200',
            'plates.*.plate' => 'required|string|max:20',
            'plates.*.seen' => 'nullable|integer|min:1|max:10000',
        ]);
        $found = [];
        foreach ($data['plates'] as $p) {
            $plate = PlateReader::normalize($p['plate']);
            if ($plate) {
                $seen = (int) ($p['seen'] ?? 1);
                // Lue au moins deux fois sur le téléphone : on la considère comme nette.
                $found[] = ['plate' => $plate, 'confidence' => $seen >= 2 ? 'high' : 'low', 'seen' => $seen];
            }
        }
        $matcher->record($inv, null, $found);

        return $inv->summary();
    }

    public function finish(int $id)
    {
        $inv = $this->find($id);
        if ($inv->status === 'running') {
            $inv->update(['status' => 'done', 'finished_at' => now()]);
        }

        return $inv->toApi();
    }

    /** Écarter une plaque mal lue, ou la rattacher à la main à une voiture du parc. */
    public function plate(Request $request, int $id, int $plateId)
    {
        $inv = $this->find($id);
        $p = $inv->plates()->findOrFail($plateId);
        $data = $request->validate([
            'dismissed' => 'sometimes|boolean',
            'vehicle_id' => ['sometimes', 'nullable', 'integer', fn ($a, $v, $fail) => $v && ! $this->garage()->vehicles()->whereKey($v)->exists() ? $fail('Voiture inconnue.') : null],
        ]);
        $p->update($data);

        return $inv->toApi();
    }

    private function find(int $id): Inventory
    {
        return Inventory::where('garage_id', $this->garage()->id)->with('garage', 'user')->findOrFail($id);
    }
}
