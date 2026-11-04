@php($img = $v->publicImages()[0]['url'] ?? null)
<a class="card" href="{{ route('site.show', [$g->slug, $v->id]) }}">
<div class="ph">@if($img)<img src="{{ $img }}" alt="{{ $v->label() }}" loading="lazy">@else Photo à venir @endif</div>
<div class="body">
<span style="font-size:17px;font-weight:700">{{ $v->label() }}</span>
@if($v->version)<span style="font-size:14px;color:#3D414A">{{ $v->version }}</span>@endif
<span class="muted" style="font-size:14px">{{ collect([$v->first_registration?->year, $v->mileage !== null ? number_format($v->mileage, 0, ',', ' ').' km' : null, \App\Models\Vehicle::FUEL_LABELS[$v->fuel] ?? null, $v->gearbox ? ucfirst($v->gearbox) : null])->filter()->implode(' · ') }}</span>
@if($v->price)<span class="price" style="font-size:22px;margin-top:4px">{{ number_format($v->price, 0, ',', ' ') }} €</span>@endif
</div>
</a>
