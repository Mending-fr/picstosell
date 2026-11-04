@extends($theme->view('layout'))
@section('title', $v->label().($v->version ? ' '.$v->version : ''))
@section('description', \Illuminate\Support\Str::limit($v->description ?: $v->label().' d\'occasion chez '.$g->name, 155))
@if($images)@section('og_image', $images[0]['url'])@endif
@section('content')
@php
$fmt = fn ($n) => number_format($n, 0, ',', ' ');
$tel = $g->phone ? preg_replace('/[^0-9+]/', '', $g->phone) : null;
$monthly = \App\Support\Themes\Finance::monthly($theme, $v->price);
$warranty = $theme->opt('warranty_months');
$fuel = \App\Models\Vehicle::FUEL_LABELS[$v->fuel] ?? null;
$facts = array_filter([
    'Année' => $v->first_registration?->translatedFormat('F Y'),
    'Kilométrage' => $v->mileage !== null ? $fmt($v->mileage).' km' : null,
    'Énergie' => $fuel,
    'Boîte' => $v->gearbox ? ucfirst($v->gearbox) : null,
    'Puissance' => $v->power_hp ? $v->power_hp.' ch' : null,
    'Couleur' => $v->color,
]);
$specs = array_filter([
    'Mise en circulation' => $v->first_registration?->translatedFormat('F Y'),
    'Kilométrage' => $v->mileage !== null ? $fmt($v->mileage).' km' : null,
    'Carburant' => $fuel,
    'Boîte' => $v->gearbox ? ucfirst($v->gearbox) : null,
    'Puissance' => $v->power_hp ? $v->power_hp.' ch'.($v->fiscal_hp ? ', '.$v->fiscal_hp.' CV fiscaux' : '') : null,
    'Portes' => $v->doors,
    'Places' => $v->seats,
    'Couleur' => $v->color,
]);
$promises = array_values(array_filter(array_map(fn ($i) => $theme->opt("promise_{$i}_title") ? ['t' => $theme->opt("promise_{$i}_title"), 'x' => $theme->opt("promise_{$i}_text")] : null, [1, 2, 3])));
$side = array_slice($images, 1, 2);
$car = $v;
@endphp

<nav class="wrap" aria-label="Fil d'Ariane" style="padding-top:22px;font-size:14px;color:var(--muted);display:flex;gap:8px;flex-wrap:wrap">
<a href="{{ route('site.index', $g->slug) }}" style="text-decoration:none">Nos occasions</a><span aria-hidden="true">›</span>
@if($v->make)<a href="{{ route('site.index', [$g->slug, 'marque' => $v->make]) }}#stock" style="text-decoration:none">{{ $v->make }}</a><span aria-hidden="true">›</span>@endif
<span style="color:var(--ink)">{{ $v->model }}{{ $v->version ? ' '.$v->version : '' }}</span>
</nav>

<section class="wrap mosaic {{ count($side) ? '' : 'solo' }}" aria-label="Photos">
@if($images)
<button type="button" class="m-main" data-open="0" aria-label="Agrandir la photo"><img src="{{ $images[0]['url'] }}" alt="{{ $images[0]['label'] }}"></button>
@foreach($side as $i => $im)<button type="button" class="m-side" data-open="{{ $i + 1 }}" aria-label="Agrandir : {{ $im['label'] }}"><img src="{{ $im['url'] }}" alt="{{ $im['label'] }}" loading="lazy"></button>@endforeach
@if(count($images) > 1)<button type="button" class="btn ghost m-all" data-open="0">Voir les {{ count($images) }} photos</button>@endif
@else
<div class="m-main" style="display:flex;align-items:center;justify-content:center;color:var(--muted)">Photos à venir</div>
@endif
</section>

<section class="wrap detail">
<div style="display:flex;flex-direction:column;gap:44px;min-width:0">

<div style="display:flex;flex-direction:column;gap:14px">
<span class="eyebrow">{{ collect([$v->make, $v->first_registration?->year])->filter()->implode(' · ') }}</span>
<h1>{{ $v->model ?: $v->label() }} @if($v->version)<span style="color:var(--muted)">{{ $v->version }}</span>@endif</h1>
@if($facts)
<div style="display:flex;gap:10px;flex-wrap:wrap;padding-top:6px">
@foreach($facts as $k => $val)<span class="panel" style="display:flex;flex-direction:column;gap:2px;border-radius:12px;padding:10px 14px;min-width:110px"><span style="font-size:12px;color:var(--muted)">{{ $k }}</span><span style="font-size:15px;font-weight:600">{{ $val }}</span></span>@endforeach
</div>
@endif
</div>

@if($promises)
<div class="pr-grid" style="grid-template-columns:repeat({{ count($promises) }},minmax(0,1fr))">
@foreach($promises as $p)<div style="background:var(--night);color:#fff;border-radius:16px;padding:20px;display:flex;flex-direction:column;gap:8px"><span style="color:var(--gold);font-size:12px;font-weight:600;letter-spacing:.12em;text-transform:uppercase">{{ $p['t'] }}</span>@if($p['x'])<span style="font-size:14.5px;line-height:1.55;color:#C9CDD4">{{ $p['x'] }}</span>@endif</div>@endforeach
</div>
@endif

@if($v->description)
<div style="display:flex;flex-direction:column;gap:14px;max-width:720px">
<h2 style="font-size:30px">Ce qu'il faut savoir</h2>
<p style="margin:0;font-size:16px;line-height:1.7;color:#2A2D35;white-space:pre-line">{{ $v->description }}</p>
</div>
@endif

@if($specs)
<div style="display:flex;flex-direction:column;gap:16px">
<h2 style="font-size:30px">Caractéristiques</h2>
<div class="specs">@foreach($specs as $k => $val)<div><span class="muted">{{ $k }}</span><span style="font-weight:500;text-align:right">{{ $val }}</span></div>@endforeach</div>
</div>
@endif

@if(! empty($v->equipment))
<div style="display:flex;flex-direction:column;gap:16px">
<h2 style="font-size:30px">Équipements</h2>
<div class="eq-grid">
@foreach($v->equipment as $grp)
<div class="panel" style="border-radius:16px;padding:20px;display:flex;flex-direction:column;gap:10px">
<span style="font-size:15px;font-weight:600">{{ $grp['group'] ?? '' }}</span>
@foreach($grp['items'] ?? [] as $it)<span style="font-size:14.5px;color:#2A2D35;display:flex;gap:10px;align-items:flex-start"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="var(--brand)" stroke-width="2.5" aria-hidden="true" style="flex-shrink:0;margin-top:3px"><path d="M5 12l5 5 9-10"/></svg>{{ $it }}</span>@endforeach
</div>
@endforeach
</div>
</div>
@endif
</div>

<aside class="buy">
<div class="panel" style="padding:26px;display:flex;flex-direction:column;gap:18px;box-shadow:0 18px 40px rgba(21,23,28,.06)">
@if($v->price)
<div style="display:flex;flex-direction:column;gap:4px">
<span style="display:flex;align-items:baseline;gap:10px"><span class="serif" style="font-size:42px">{{ $fmt($v->price) }} €</span><span style="font-size:13px;color:var(--muted)">TTC</span></span>
@if($monthly)<span style="font-size:14px;color:var(--soft)">ou <strong style="color:var(--ink)">{{ $fmt($monthly) }} €/mois</strong> sur {{ (int) $theme->opt('finance_months', 60) }} mois, exemple détaillé en bas de page</span>@endif
</div>
@endif
@if($g->phone)<a class="btn dark lg" href="tel:{{ $tel }}">Appeler · {{ $g->phone }}</a>@endif

<form method="post" action="{{ route('site.lead', $g->slug) }}" id="lead" style="display:flex;flex-direction:column;gap:12px;border-top:1px solid var(--line);padding-top:18px">
@csrf
<input type="hidden" name="type" value="vehicle"><input type="hidden" name="vehicle_id" value="{{ $v->id }}"><input type="hidden" name="source" value="fiche">
<label class="sr">Site web<input name="site_web" tabindex="-1" autocomplete="off"></label>
<div role="radiogroup" aria-label="Votre demande" class="seg">
<label><input type="radio" name="_want" value="essai" checked> Réserver un essai</label>
<label><input type="radio" name="_want" value="rappel"> Être rappelé</label>
</div>
@if($errors->any())<div class="errors">{{ $errors->first() }}</div>@endif
<input class="input" name="name" placeholder="Votre nom" autocomplete="name" value="{{ old('name') }}" aria-label="Votre nom" required>
<input class="input" type="tel" name="phone" placeholder="Votre téléphone" autocomplete="tel" value="{{ old('phone') }}" aria-label="Votre téléphone" required>
<textarea class="input" name="message" rows="2" aria-label="Votre message" id="lead-msg">{{ old('message', 'Bonjour, je voudrais essayer la '.$v->label().'. Quand puis-je passer ?') }}</textarea>
<label style="display:flex;gap:10px;align-items:flex-start;font-size:13px;color:var(--soft)"><input type="checkbox" name="wants_trade_in" value="1" style="width:18px;height:18px;margin:0">J'ai une voiture à faire reprendre</label>
<label style="display:flex;gap:10px;align-items:flex-start;font-size:13px;color:var(--soft)"><input type="checkbox" name="consent" value="1" required style="width:18px;height:18px;margin:0;flex-shrink:0">J'accepte d'être recontacté au sujet de cette voiture. <a href="{{ route('site.legal', $g->slug) }}#confidentialite">Confidentialité</a></label>
<button class="btn lg" type="submit" id="lead-btn">Réserver un essai</button>
</form>

@if($warranty)
<span style="display:flex;gap:10px;align-items:center;font-size:14px;color:var(--soft);border-top:1px solid var(--line);padding-top:16px"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--brand)" stroke-width="2.2" aria-hidden="true"><path d="M5 12l5 5 9-10"/></svg>Garantie {{ (int) $warranty }} mois</span>
@endif
</div>

<form method="get" action="{{ route('site.contact', $g->slug) }}" style="background:var(--night);color:#fff;border-radius:20px;padding:22px;display:flex;flex-direction:column;gap:10px">
<input type="hidden" name="sujet" value="reprise">
<span style="font-size:16px;font-weight:600">Une voiture à faire reprendre ?</span>
<span style="font-size:14px;color:#C9CDD4;line-height:1.5">Donnez la plaque, on vous fait une offre. Le montant se déduit du prix.</span>
<div style="display:flex;gap:8px"><input class="input" name="plaque" placeholder="AB-123-CD" aria-label="Plaque de votre voiture" style="min-height:46px;font-family:ui-monospace,monospace;letter-spacing:.06em;text-transform:uppercase;border:0"><button type="submit" class="btn" style="min-height:46px">Estimer</button></div>
</form>
</aside>
</section>

@if($similar->isNotEmpty())
<section style="background:#fff;border-top:1px solid var(--line);margin-top:24px">
<div class="wrap" style="padding-top:64px;padding-bottom:72px;display:flex;flex-direction:column;gap:24px">
<div style="display:flex;justify-content:space-between;align-items:flex-end;gap:16px;flex-wrap:wrap"><h2 style="font-size:34px">Dans le même budget</h2><a href="{{ route('site.index', $g->slug) }}#stock" style="font-size:15px;font-weight:500;text-decoration:none;border-bottom:1px solid var(--ink)">Toutes les occasions</a></div>
<div class="grid-cars">@foreach($similar as $v) @include($theme->view('_card')) @endforeach</div>
</div>
</section>
@endif

@if($images)
<div id="lb" class="lb" hidden role="dialog" aria-modal="true" aria-label="Photos de la voiture">
<button type="button" class="lb-x" aria-label="Fermer">×</button>
<button type="button" class="lb-n" data-step="-1" aria-label="Photo précédente">‹</button>
<figure><img id="lb-img" alt=""><figcaption id="lb-cap"></figcaption></figure>
<button type="button" class="lb-n" data-step="1" aria-label="Photo suivante" style="right:16px;left:auto">›</button>
</div>
@endif

<style>
.mosaic{padding-top:18px;display:grid;grid-template-columns:2fr 1fr;grid-template-rows:1fr 1fr;gap:12px;height:min(600px,62vh);position:relative}
.mosaic.solo{grid-template-columns:1fr}
.mosaic button{padding:0;border:0;cursor:zoom-in;overflow:hidden;background:#DFDCD4}
.mosaic img{width:100%;height:100%;object-fit:cover;transition:transform .4s ease}
.mosaic button:hover img{transform:scale(1.03)}
.m-main{grid-row:1/3;border-radius:20px;overflow:hidden;background:#DFDCD4}
.mosaic.solo .m-main{grid-row:auto}
.m-side{border-radius:16px}
.mosaic .m-all{position:absolute;right:56px;bottom:16px;cursor:pointer;min-height:40px;padding:0 18px;background:#fff}
.detail{padding-top:36px;padding-bottom:72px;display:grid;grid-template-columns:minmax(0,1fr) 400px;gap:48px;align-items:start}
.buy{position:sticky;top:96px;display:flex;flex-direction:column;gap:16px}
.pr-grid{display:grid;gap:14px}
.specs{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:0 40px;border-top:1px solid #DDD9D0}
.specs>div{display:flex;justify-content:space-between;gap:12px;padding:13px 0;border-bottom:1px solid #E8E5DD;font-size:15px}
.eq-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:18px}
.seg{display:grid;grid-template-columns:1fr 1fr;background:#F1EFE9;border-radius:999px;padding:4px}
.seg label{display:flex;align-items:center;justify-content:center;min-height:38px;border-radius:999px;font-size:13.5px;font-weight:500;cursor:pointer;color:var(--soft)}
.seg input{position:absolute;opacity:0}
.seg label:has(input:checked){background:#fff;color:var(--ink);box-shadow:0 1px 3px rgba(21,23,28,.12)}
.seg label:has(input:focus-visible){outline:2px solid var(--brand)}
.lb{position:fixed;inset:0;z-index:100;background:rgba(10,11,13,.94);display:flex;align-items:center;justify-content:center}
.lb[hidden]{display:none}
.lb figure{margin:0;max-width:min(1200px,92vw);display:flex;flex-direction:column;gap:12px;align-items:center}
.lb img{max-height:82vh;width:auto;border-radius:12px}
.lb figcaption{color:#C9CDD4;font-size:14px}
.lb button{position:absolute;border:0;background:rgba(255,255,255,.12);color:#fff;width:52px;height:52px;border-radius:26px;font-size:28px;cursor:pointer}
.lb-x{top:16px;right:16px}
.lb-n{top:50%;left:16px;transform:translateY(-50%)}
@media (max-width:960px){
  .mosaic{grid-template-columns:1fr;grid-template-rows:auto;height:auto}
  .m-main{grid-row:auto;aspect-ratio:4/3}
  .m-side{display:none}
  .m-all{right:28px}
  .detail{grid-template-columns:1fr;gap:32px}
  .buy{position:static}
  .pr-grid,.specs{grid-template-columns:1fr !important}
}
</style>
<script>
(function(){
const imgs=@json($images);
const lb=document.getElementById('lb');
if(lb){let i=0;const im=document.getElementById('lb-img'),cap=document.getElementById('lb-cap');
const show=n=>{i=(n+imgs.length)%imgs.length;im.src=imgs[i].url;im.alt=imgs[i].label;cap.textContent=imgs[i].label+' · '+(i+1)+' / '+imgs.length;};
const open=n=>{show(n);lb.hidden=false;document.body.style.overflow='hidden';lb.querySelector('.lb-x').focus();};
const close=()=>{lb.hidden=true;document.body.style.overflow='';};
document.querySelectorAll('[data-open]').forEach(b=>b.addEventListener('click',()=>open(+b.dataset.open)));
lb.querySelector('.lb-x').onclick=close;lb.querySelectorAll('.lb-n').forEach(b=>b.onclick=()=>show(i+ +b.dataset.step));
lb.addEventListener('click',e=>{if(e.target===lb)close();});
document.addEventListener('keydown',e=>{if(lb.hidden)return;if(e.key==='Escape')close();if(e.key==='ArrowRight')show(i+1);if(e.key==='ArrowLeft')show(i-1);});}
const msg=document.getElementById('lead-msg'),btn=document.getElementById('lead-btn');
const texts={essai:@json('Bonjour, je voudrais essayer la '.$car->label().'. Quand puis-je passer ?'),rappel:@json('Bonjour, pouvez-vous me rappeler au sujet de la '.$car->label().' ?')};
document.querySelectorAll('input[name=_want]').forEach(r=>r.addEventListener('change',()=>{if(Object.values(texts).includes(msg.value.trim()))msg.value=texts[r.value];btn.textContent=r.value==='essai'?'Réserver un essai':'Être rappelé';}));
})();
</script>
@endsection
