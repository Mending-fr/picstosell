@extends($theme->view('layout'))
@section('content')
<div class="wrap" style="padding-top:32px;padding-bottom:24px;display:flex;flex-direction:column;gap:24px">
<div style="display:flex;flex-direction:column;gap:6px">
<h1>Nos occasions</h1>
<p class="muted" style="margin:0;font-size:16px">{{ $total }} {{ $total > 1 ? 'voitures disponibles' : 'voiture disponible' }}@if($g->city) à {{ $g->city }}@endif.</p>
</div>
<form method="get" aria-label="Filtrer les voitures" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;background:var(--ground);border-radius:14px;padding:16px;align-items:end">
<label class="field">Marque<select class="input" name="marque"><option value="">Toutes</option>@foreach($makes as $m)<option @selected(($f['marque'] ?? '') === $m)>{{ $m }}</option>@endforeach</select></label>
<label class="field">Carburant<select class="input" name="carburant"><option value="">Tous</option>@foreach(\App\Models\Vehicle::FUEL_LABELS as $k => $l)<option value="{{ $k }}" @selected(($f['carburant'] ?? '') === $k)>{{ $l }}</option>@endforeach</select></label>
<label class="field">Boîte<select class="input" name="boite"><option value="">Toutes</option><option value="manuelle" @selected(($f['boite'] ?? '') === 'manuelle')>Manuelle</option><option value="automatique" @selected(($f['boite'] ?? '') === 'automatique')>Automatique</option></select></label>
<label class="field">Budget<select class="input" name="budget"><option value="">Pas de limite</option>@foreach([10000,15000,20000,25000,30000] as $b)<option value="{{ $b }}" @selected((int)($f['budget'] ?? 0) === $b)>Jusqu'à {{ number_format($b, 0, ',', ' ') }} €</option>@endforeach</select></label>
<label class="field">Trier par<select class="input" name="tri"><option value="recent">Arrivées récentes</option><option value="prix" @selected(($f['tri'] ?? '') === 'prix')>Prix le plus bas</option><option value="km" @selected(($f['tri'] ?? '') === 'km')>Moins de kilomètres</option></select></label>
<button class="btn dark" type="submit">Voir les voitures</button>
</form>
@if(array_filter($f))
<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px"><strong>{{ $vehicles->count() }} {{ $vehicles->count() > 1 ? 'voitures' : 'voiture' }}</strong><a href="{{ route('site.index', $g->slug) }}" style="color:#1F4FD8;font-weight:600">Effacer les filtres</a></div>
@endif
@if($vehicles->isEmpty())
<div style="border:1px dashed #CFCFC8;border-radius:14px;padding:40px 24px;display:flex;flex-direction:column;gap:12px;align-items:center;text-align:center">
<strong style="font-size:18px">Aucune voiture ne correspond pour l'instant</strong>
<span class="muted">Le stock bouge souvent. Dites-nous ce que vous cherchez, on vous prévient quand une voiture correspond.</span>
<a class="btn" href="{{ route('site.contact', [$g->slug, 'sujet' => 'autre']) }}">Nous dire ce que je cherche</a>
</div>
@else
<div class="grid-cars">@foreach($vehicles as $v) @include($theme->view('_card')) @endforeach</div>
@endif
</div>
<script>document.querySelectorAll('form select').forEach(s=>s.addEventListener('change',()=>s.form.submit()));</script>
@endsection
