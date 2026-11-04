<?php

namespace App\Http\Controllers\Api;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $g = $this->garage();
        $v = $g->vehicles();
        $count = fn (string $s) => (clone $v)->where('status', $s)->count();

        $counts = [
            'to_photograph' => $count('to_photograph'),
            'processing' => $count('processing'),
            'to_review' => $count('to_review'),
            'ready' => $count('ready'),
            'online' => $count('online'),
            'sold_month' => (clone $v)->where('status', 'sold')->where('sold_at', '>=', now()->startOfMonth())->count(),
            'online_without_leads' => (clone $v)->where('status', 'online')->doesntHave('leads')->count(),
            'sold_still_online' => (clone $v)->where('status', 'sold')->whereNotNull('published_at')->count(),
        ];

        $todo = [];
        foreach ((clone $v)->where('status', 'to_review')->oldest('updated_at')->limit(10)->get() as $x) {
            $todo[] = $this->item($x, 'Photos prêtes, à vérifier avant la mise en ligne', 'review');
        }
        foreach ($g->leads()->where('status', 'new')->with('vehicle')->latest()->limit(10)->get() as $l) {
            $todo[] = [
                'type' => 'lead', 'id' => $l->id, 'plate' => $l->vehicle?->plate,
                'title' => $l->vehicle ? 'Demande pour '.$l->vehicle->label() : ($l->type === 'trade_in' ? 'Demande de reprise' : 'Nouvelle demande'),
                'detail' => trim($l->name.' : '.mb_strimwidth((string) $l->message, 0, 90, '…')),
                'status' => 'lead', 'action' => 'open_lead',
            ];
        }
        foreach ((clone $v)->where('status', 'ready')->oldest('updated_at')->limit(10)->get() as $x) {
            $todo[] = $this->item($x, 'Photos vérifiées, prête à mettre en ligne', 'publish');
        }
        foreach ((clone $v)->where('status', 'to_photograph')->oldest()->limit(10)->get() as $x) {
            $todo[] = $this->item($x, 'Arrivée le '.$x->created_at->format('d/m').', pas encore photographiée', 'request_photos');
        }
        foreach ((clone $v)->where('status', 'sold')->whereNotNull('published_at')->limit(10)->get() as $x) {
            $todo[] = $this->item($x, "Vendue, l'annonce est toujours en ligne", 'unpublish');
        }

        return [
            'counts' => $counts,
            'todo' => $todo,
            'usage' => [
                'vehicles_month' => (clone $v)->withTrashed()->where('created_at', '>=', now()->startOfMonth())->count(),
                'quota' => $g->vehicle_quota,
            ],
        ];
    }

    private function item($x, string $detail, string $action): array
    {
        return ['type' => 'vehicle', 'id' => $x->id, 'plate' => $x->plate, 'title' => trim($x->label().' '.($x->version ?? '')), 'detail' => $detail, 'status' => $x->status, 'action' => $action];
    }
}
