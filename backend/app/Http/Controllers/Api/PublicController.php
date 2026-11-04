<?php

namespace App\Http\Controllers\Api;

use App\Models\Garage;

/** Flux public des annonces d'un garage (pour un site tiers ou une future multidiffusion). */
class PublicController
{
    public function listings(string $slug)
    {
        $g = Garage::where('slug', $slug)->firstOrFail();
        $vehicles = $g->vehicles()->published()->with(['photos', 'renders.photo'])->latest('published_at')->get();

        return [
            'garage' => ['name' => $g->name, 'phone' => $g->phone, 'email' => $g->email, 'address' => $g->address, 'city' => $g->city, 'site_url' => $g->siteUrl()],
            'data' => $vehicles->map(fn ($v) => collect($v->toApi())->except(['photographer_id', 'leads_count', 'photos_count', 'days_in_stock'])->all() + ['images' => $v->publicImages(), 'url' => route('site.show', [$g->slug, $v->id])])->values(),
        ];
    }
}
