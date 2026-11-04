@php
$img = $v->publicImages()[0]['url'] ?? null;
$monthly = \App\Support\Themes\Finance::monthly($theme, $v->price);
$isNew = $v->published_at && $v->published_at->gt(now()->subDays(7));
@endphp
<a class="card" href="{{ route('site.show', [$g->slug, $v->id]) }}">
<div class="ph">@if($isNew)<span class="tag">Nouvelle arrivée</span>@endif @if($img)<img src="{{ $img }}" alt="{{ $v->label() }}" loading="lazy">@else Photos à venir @endif</div>
<div class="body">
<span style="display:flex;justify-content:space-between;align-items:baseline;gap:10px"><span style="font-size:17px;font-weight:600">{{ $v->label() }}</span>@if($v->price)<span style="font-size:17px;font-weight:600;white-space:nowrap">{{ number_format($v->price, 0, ',', ' ') }} €</span>@endif</span>
<span style="display:flex;justify-content:space-between;gap:10px"><span style="font-size:14px;color:var(--soft)">{{ $v->version }}</span>@if($monthly)<span style="font-size:13px;color:var(--muted);white-space:nowrap">ou {{ number_format($monthly, 0, ',', ' ') }} €/mois</span>@endif</span>
<span style="display:flex;gap:6px;flex-wrap:wrap;margin-top:8px">
@foreach(array_filter([$v->first_registration?->year, $v->mileage !== null ? number_format($v->mileage, 0, ',', ' ').' km' : null, \App\Models\Vehicle::FUEL_LABELS[$v->fuel] ?? null, $v->gearbox ? ucfirst($v->gearbox) : null]) as $c)<span class="chip">{{ $c }}</span>@endforeach
</span>
</div>
</a>
