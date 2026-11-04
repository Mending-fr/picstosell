<?php

namespace App\Http\Controllers;

use App\Models\Garage;
use App\Models\Lead;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Site public d'un garage : occasions, fiche, garage, contact, informations légales. */
class SiteController
{
    private function garage(string $slug): Garage
    {
        return Garage::where('slug', $slug)->firstOrFail();
    }

    public function index(Request $request, string $slug)
    {
        $g = $this->garage($slug);
        $q = $g->vehicles()->published()->with(['photos', 'renders.photo']);
        $f = $request->validate([
            'marque' => 'nullable|string|max:40',
            'carburant' => ['nullable', Rule::in(Vehicle::FUELS)],
            'boite' => ['nullable', Rule::in(Vehicle::GEARBOXES)],
            'budget' => 'nullable|integer|min:0',
            'km' => 'nullable|integer|min:0',
            'tri' => 'nullable|in:recent,prix,km',
        ]);
        if (! empty($f['marque'])) {
            $q->where('make', $f['marque']);
        }
        if (! empty($f['carburant'])) {
            $q->where('fuel', $f['carburant']);
        }
        if (! empty($f['boite'])) {
            $q->where('gearbox', $f['boite']);
        }
        if (! empty($f['budget'])) {
            $q->where('price', '<=', $f['budget']);
        }
        if (! empty($f['km'])) {
            $q->where('mileage', '<=', $f['km']);
        }
        match ($f['tri'] ?? 'recent') {
            'prix' => $q->orderBy('price'),
            'km' => $q->orderBy('mileage'),
            default => $q->orderByDesc('published_at'),
        };
        $vehicles = $q->get();
        $makes = $g->vehicles()->published()->whereNotNull('make')->distinct()->orderBy('make')->pluck('make');
        $total = $g->vehicles()->published()->count();

        return view('site.index', compact('g', 'vehicles', 'makes', 'f', 'total'));
    }

    public function show(string $slug, int $id)
    {
        $g = $this->garage($slug);
        $v = $g->vehicles()->published()->findOrFail($id);
        $images = $v->publicImages();
        $similar = $g->vehicles()->published()->where('id', '!=', $v->id)
            ->when($v->price, fn ($q) => $q->orderByRaw('abs(coalesce(price, 0) - ?)', [$v->price]))
            ->with(['photos', 'renders.photo'])->limit(3)->get();

        return view('site.show', compact('g', 'v', 'images', 'similar'));
    }

    public function about(string $slug)
    {
        return view('site.about', ['g' => $this->garage($slug)]);
    }

    public function contact(Request $request, string $slug)
    {
        $g = $this->garage($slug);
        $vehicles = $g->vehicles()->published()->orderBy('make')->get();

        return view('site.contact', ['g' => $g, 'vehicles' => $vehicles, 'sujet' => $request->query('sujet', 'voiture'), 'vehicleId' => $request->query('voiture')]);
    }

    public function sendLead(Request $request, string $slug)
    {
        $g = $this->garage($slug);
        if ($request->filled('site_web')) { // champ piège anti-robots
            return redirect()->route('site.thanks', $g->slug);
        }
        $data = $request->validate([
            'type' => ['required', Rule::in(Lead::TYPES)],
            'name' => 'required|string|max:120',
            'phone' => 'required|string|max:30',
            'email' => 'nullable|email|max:160',
            'message' => 'nullable|string|max:3000',
            'vehicle_id' => ['nullable', 'integer', Rule::exists('vehicles', 'id')->where('garage_id', $g->id)],
            'trade_in_plate' => 'nullable|string|max:20',
            'trade_in_mileage' => 'nullable|integer|min:0|max:2000000',
            'wants_trade_in' => 'nullable|boolean',
            'consent' => 'accepted',
            'source' => 'nullable|string|max:40',
        ], [
            'consent.accepted' => 'Merci de cocher la case pour que nous puissions vous recontacter.',
            'name.required' => 'Indiquez votre nom.',
            'phone.required' => 'Indiquez votre numéro de téléphone.',
        ]);
        if (! empty($data['wants_trade_in']) && $data['type'] === 'vehicle') {
            $data['message'] = trim(($data['message'] ?? '')."\n\nLe client a une voiture à faire reprendre.");
        }
        unset($data['wants_trade_in'], $data['consent']);
        $g->leads()->create($data + ['status' => 'new']);

        return redirect()->route('site.thanks', $g->slug);
    }

    public function thanks(string $slug)
    {
        return view('site.thanks', ['g' => $this->garage($slug)]);
    }

    public function legal(string $slug)
    {
        return view('site.legal', ['g' => $this->garage($slug)]);
    }
}
