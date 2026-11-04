@extends($theme->view('layout'))
@section('content')
@php
$fmt = fn ($n) => number_format($n, 0, ',', ' ');
$filtered = (bool) array_filter($f);
$recent = $g->vehicles()->published()->with(['photos', 'renders.photo'])->orderByDesc('published_at')->limit(3)->get();
$star = $recent->first();
$starImg = $star ? ($star->publicImages()[0]['url'] ?? null) : null;
$rating = $theme->opt('google_rating');
$reviews = $theme->opt('google_reviews');
$warranty = $theme->opt('warranty_months');
$since = $theme->opt('since_year');
$stats = array_values(array_filter([
    $rating ? [str_replace('.', ',', (string) round((float) $rating, 1)), $reviews ? 'sur Google, '.$fmt((int) $reviews).' avis' : 'sur Google'] : null,
    $warranty ? [(int) $warranty.' mois', 'de garantie'] : null,
    $since ? [(int) $since, 'année d\'ouverture'] : null,
    $total ? [$total, $total > 1 ? 'voitures en stock' : 'voiture en stock'] : null,
]));
$promises = array_values(array_filter(array_map(fn ($i) => $theme->opt("promise_{$i}_title") ? ['t' => $theme->opt("promise_{$i}_title"), 'x' => $theme->opt("promise_{$i}_text")] : null, [1, 2, 3])));
$heroText = $theme->opt('hero_text') ?: ($g->about ?: 'Toutes nos voitures sont à voir sur place'.($g->city ? ' à '.$g->city : '').'. Passez au garage ou appelez-nous pour un essai.');
$tel = $g->phone ? preg_replace('/[^0-9+]/', '', $g->phone) : null;
@endphp

@unless($filtered)
<section style="background:var(--night);color:#fff">
<div class="wrap hero">
<div style="display:flex;flex-direction:column;gap:24px">
<span class="eyebrow" style="color:var(--gold)">{{ $theme->opt('hero_kicker') ?: 'Voitures d\'occasion'.($g->city ? ' à '.$g->city : '') }}</span>
<h1 style="font-size:58px;font-weight:300;line-height:1.04">{{ $theme->opt('hero_title') }}</h1>
<p style="margin:0;font-size:18px;line-height:1.55;color:#C9CDD4;max-width:540px;white-space:pre-line">{{ \Illuminate\Support\Str::limit($heroText, 260) }}</p>
<div style="display:flex;gap:12px;flex-wrap:wrap">
<a class="btn lg" href="#stock">Voir {{ $total > 1 ? 'les '.$total.' voitures' : 'la voiture' }}</a>
<a class="btn line lg" href="{{ route('site.contact', [$g->slug, 'sujet' => 'reprise']) }}">Faire estimer ma voiture</a>
</div>
@if($stats)
<div style="display:flex;gap:36px;flex-wrap:wrap;padding-top:8px">
@foreach($stats as [$n, $l])<div style="display:flex;flex-direction:column;gap:2px"><span class="serif" style="font-size:30px">{{ $n }}</span><span style="font-size:13px;color:#A8ACB5">{{ $l }}</span></div>@endforeach
</div>
@endif
</div>
@if($star)
<a href="{{ route('site.show', [$g->slug, $star->id]) }}" class="hero-car" style="text-decoration:none;color:#fff">
@if($starImg)<img src="{{ $starImg }}" alt="{{ $star->label() }}">@endif
<span class="tag" style="background:rgba(21,23,28,.55);backdrop-filter:blur(6px)">Dernière arrivée</span>
<span class="hero-cap">
<span style="display:flex;flex-direction:column;gap:2px"><span style="font-size:20px;font-weight:600">{{ $star->label() }}{{ $star->version ? ' '.$star->version : '' }}</span><span style="font-size:14px;color:#D9DBE0">{{ collect([$star->first_registration?->year, $star->mileage !== null ? $fmt($star->mileage).' km' : null, \App\Models\Vehicle::FUEL_LABELS[$star->fuel] ?? null])->filter()->implode(' · ') }}</span></span>
@if($star->price)<span style="font-size:24px;font-weight:600;white-space:nowrap">{{ $fmt($star->price) }} €</span>@endif
</span>
</a>
@endif
</div>
</section>
@endunless

<section class="wrap" style="{{ $filtered ? 'padding-top:40px' : 'margin-top:-32px' }};position:relative;z-index:2">
<form method="get" action="{{ route('site.index', $g->slug) }}#stock" aria-label="Rechercher une voiture" class="search">
<label class="field">Marque<select class="input" name="marque"><option value="">Toutes les marques</option>@foreach($makes as $m)<option @selected(($f['marque'] ?? '') === $m)>{{ $m }}</option>@endforeach</select></label>
<label class="field">Budget<select class="input" name="budget"><option value="">Tous les budgets</option>@foreach([10000,15000,20000,25000,30000] as $b)<option value="{{ $b }}" @selected((int)($f['budget'] ?? 0) === $b)>Jusqu'à {{ $fmt($b) }} €</option>@endforeach</select></label>
<label class="field">Énergie<select class="input" name="carburant"><option value="">Toutes</option>@foreach(\App\Models\Vehicle::FUEL_LABELS as $k => $l)<option value="{{ $k }}" @selected(($f['carburant'] ?? '') === $k)>{{ $l }}</option>@endforeach</select></label>
<label class="field">Boîte<select class="input" name="boite"><option value="">Toutes</option><option value="manuelle" @selected(($f['boite'] ?? '') === 'manuelle')>Manuelle</option><option value="automatique" @selected(($f['boite'] ?? '') === 'automatique')>Automatique</option></select></label>
<label class="field">Trier par<select class="input" name="tri"><option value="recent">Arrivées récentes</option><option value="prix" @selected(($f['tri'] ?? '') === 'prix')>Prix le plus bas</option><option value="km" @selected(($f['tri'] ?? '') === 'km')>Moins de kilomètres</option></select></label>
<button class="btn dark" type="submit" style="min-height:50px">Rechercher</button>
</form>
</section>

@if(! $filtered && $recent->count() === 3 && $total > 6)
<section class="wrap" style="padding-top:72px;display:flex;flex-direction:column;gap:28px">
<div class="sec-head"><div style="display:flex;flex-direction:column;gap:8px"><span class="eyebrow">Cette semaine</span><h2 style="font-size:40px">Dernières arrivées</h2></div></div>
<div class="grid3">@foreach($recent as $v) @include($theme->view('_card')) @endforeach</div>
</section>
@endif

<section id="stock" class="wrap" style="padding-top:{{ $filtered ? '40px' : '80px' }};display:flex;flex-direction:column;gap:28px;scroll-margin-top:90px">
<div class="sec-head">
<div style="display:flex;flex-direction:column;gap:8px">
<span class="eyebrow">{{ $filtered ? 'Votre recherche' : 'Le stock' }}</span>
<h2 style="font-size:40px">@if($filtered){{ $vehicles->count() }} {{ $vehicles->count() > 1 ? 'voitures correspondent' : 'voiture correspond' }}@else{{ $total }} {{ $total > 1 ? 'voitures' : 'voiture' }} à voir{{ $g->city ? ' à '.$g->city : ' sur place' }}@endif</h2>
</div>
@if($filtered)<a href="{{ route('site.index', $g->slug) }}#stock" class="btn ghost">Effacer la recherche</a>@endif
</div>
@if($vehicles->isEmpty())
<div class="panel" style="padding:48px 24px;display:flex;flex-direction:column;gap:12px;align-items:center;text-align:center">
<h3 style="font-size:26px">Rien pour l'instant avec ces critères</h3>
<span class="muted" style="max-width:520px;line-height:1.5">Le stock change chaque semaine. Dites-nous ce que vous cherchez et on vous appelle quand une voiture correspond.</span>
<a class="btn" href="{{ route('site.contact', [$g->slug, 'sujet' => 'autre']) }}">Dire ce que je cherche</a>
</div>
@else
<div class="grid4">@foreach($vehicles as $v) @include($theme->view('_card')) @endforeach</div>
@endif
</section>

@if($promises)
<section style="background:var(--night);color:#fff;margin-top:96px">
<div class="wrap promises" style="grid-template-columns:repeat({{ count($promises) }},minmax(0,1fr))">
@foreach($promises as $i => $p)
<div style="display:flex;flex-direction:column;gap:14px"><span class="serif" style="font-size:44px;color:var(--gold)">0{{ $i + 1 }}</span><span style="font-size:20px;font-weight:600">{{ $p['t'] }}</span>@if($p['x'])<span style="font-size:15px;line-height:1.6;color:#C9CDD4">{{ $p['x'] }}</span>@endif</div>
@endforeach
</div>
</section>
@endif

<section class="wrap garage" style="padding-top:96px">
<div class="garage-card">
<span class="eyebrow" style="color:var(--gold)">Le garage</span>
<h2 style="font-size:40px;color:#fff">{{ $g->name }}</h2>
@if($g->address || $g->city)<span style="font-size:16px;color:#D9DBE0">{{ collect([$g->address, $g->city])->filter()->implode(', ') }}</span>@endif
@if($g->hours)<span style="font-size:14px;color:#A8ACB5;white-space:pre-line;line-height:1.6">{{ $g->hours }}</span>@endif
@if($g->address)<a class="btn line" style="align-self:flex-start" href="https://www.openstreetmap.org/search?query={{ urlencode($g->address.' '.$g->city) }}" rel="noopener">Itinéraire</a>@endif
</div>
<div style="display:flex;flex-direction:column;gap:20px">
<span class="eyebrow">Qui sommes-nous</span>
<h2 style="font-size:36px">@if($since)Au service des automobilistes{{ $g->city ? ' de '.$g->city : '' }} depuis {{ (int) $since }}.@else Des voitures à voir, à essayer, et des gens à qui parler.@endif</h2>
@if($g->about)<p style="margin:0;font-size:16px;line-height:1.65;color:var(--soft);white-space:pre-line">{{ $g->about }}</p>@endif
@if($rating)
<div class="panel" style="display:flex;gap:14px;align-items:center;padding:16px 18px;border-radius:14px">
<span style="color:var(--gold);font-size:18px;letter-spacing:2px" aria-hidden="true">★★★★★</span>
<span style="font-size:14px;color:var(--soft)"><strong style="color:var(--ink)">{{ str_replace('.', ',', (string) round((float) $rating, 1)) }} sur 5</strong>@if($reviews) · {{ $fmt((int) $reviews) }} avis Google @endif</span>
@if($theme->opt('google_url'))<a href="{{ $theme->opt('google_url') }}" rel="noopener" target="_blank" style="margin-left:auto;font-size:14px;font-weight:500">Lire les avis</a>@endif
</div>
@endif
<div style="display:flex;gap:12px;flex-wrap:wrap">@if($g->phone)<a class="btn dark" href="tel:{{ $tel }}">Appeler {{ $g->phone }}</a>@endif<a class="btn ghost" href="{{ route('site.about', $g->slug) }}">En savoir plus</a></div>
</div>
</section>

@if($theme->opt('alert_form', true))
<section class="wrap" style="padding-top:96px;padding-bottom:96px">
<form method="post" action="{{ route('site.lead', $g->slug) }}" class="alert-box">
@csrf
<input type="hidden" name="type" value="other"><input type="hidden" name="source" value="alerte">
<label class="sr">Site web<input name="site_web" tabindex="-1" autocomplete="off"></label>
<div style="display:flex;flex-direction:column;gap:12px">
<h2 style="font-size:36px;color:#fff">Vous cherchez un modèle précis ?</h2>
<span style="font-size:16px;line-height:1.5;opacity:.9">Dites-nous lequel. On vous appelle quand une voiture qui correspond arrive au garage.</span>
</div>
<div style="display:flex;flex-direction:column;gap:10px">
@if($errors->any())<div class="errors">{{ $errors->first() }}</div>@endif
<input class="input" name="message" placeholder="Ex. Clio automatique, moins de 50 000 km" value="{{ old('message') }}" aria-label="Le modèle que vous cherchez" required>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
<input class="input" name="name" placeholder="Votre nom" autocomplete="name" value="{{ old('name') }}" aria-label="Votre nom" required>
<input class="input" name="phone" type="tel" placeholder="Votre téléphone" autocomplete="tel" value="{{ old('phone') }}" aria-label="Votre téléphone" required>
</div>
<label style="display:flex;gap:10px;align-items:flex-start;font-size:13px;line-height:1.4"><input type="checkbox" name="consent" value="1" required style="width:18px;height:18px;margin:0;flex-shrink:0">J'accepte d'être rappelé au sujet de cette recherche.</label>
<button class="btn dark lg" type="submit">Me prévenir</button>
</div>
</form>
</section>
@else
<div style="height:96px"></div>
@endif

<style>
.hero{display:grid;grid-template-columns:1fr 1.1fr;gap:48px;align-items:center;padding-top:72px;padding-bottom:88px}
.hero-car{position:relative;display:block;aspect-ratio:5/4;border-radius:24px;overflow:hidden;background:radial-gradient(120% 90% at 50% 15%,#3A3E47 0%,#20232A 55%,#121418 100%)}
.hero-car img{width:100%;height:100%;object-fit:cover;transition:transform .4s ease}
.hero-car:hover img{transform:scale(1.03)}
.hero-cap{position:absolute;left:0;right:0;bottom:0;padding:60px 22px 22px;display:flex;justify-content:space-between;align-items:flex-end;gap:16px;background:linear-gradient(180deg,rgba(0,0,0,0),rgba(0,0,0,.6))}
.search{background:#fff;border-radius:20px;box-shadow:0 24px 60px rgba(21,23,28,.14);padding:18px 20px;display:grid;grid-template-columns:repeat(5,minmax(0,1fr)) auto;gap:12px;align-items:end}
.sec-head{display:flex;justify-content:space-between;align-items:flex-end;gap:20px;flex-wrap:wrap}
.grid3{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:22px}
.grid4{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:22px}
.promises{display:grid;gap:40px;padding-top:80px;padding-bottom:80px}
.garage{display:grid;grid-template-columns:1fr 1fr;gap:56px;align-items:center}
.garage-card{background:radial-gradient(120% 120% at 20% 0%,#2A2E36 0%,var(--night) 70%);border-radius:24px;padding:44px;min-height:360px;display:flex;flex-direction:column;justify-content:flex-end;gap:14px}
.alert-box{background:var(--brand);color:#fff;border-radius:24px;padding:48px 56px;display:grid;grid-template-columns:1.1fr 1fr;gap:40px;align-items:center}
@media (max-width:1200px){.grid4{grid-template-columns:repeat(3,minmax(0,1fr))}.search{grid-template-columns:repeat(3,minmax(0,1fr))}}
@media (max-width:960px){
  .hero,.garage,.alert-box{grid-template-columns:1fr}
  .hero{padding-top:36px;padding-bottom:64px;gap:28px}
  .hero h1{font-size:36px !important}
  .grid3,.grid4{grid-template-columns:repeat(2,minmax(0,1fr))}
  .search{grid-template-columns:1fr 1fr}
  .search button{grid-column:1/-1}
  .promises{grid-template-columns:1fr !important;padding-top:56px;padding-bottom:56px}
  .alert-box{padding:28px 22px}
  .garage-card{padding:28px;min-height:0}
}
@media (max-width:640px){.grid3,.grid4{grid-template-columns:1fr}.sec-head h2{font-size:30px !important}}
</style>
<script>document.querySelectorAll('.search select').forEach(s=>s.addEventListener('change',()=>s.form.submit()));</script>
@endsection
