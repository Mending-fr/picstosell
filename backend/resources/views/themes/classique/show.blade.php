@extends($theme->view('layout'))
@section('title', $v->label().($v->version ? ' '.$v->version : ''))
@section('description', \Illuminate\Support\Str::limit($v->description ?: $v->label().' d\'occasion chez '.$g->name, 155))
@if($images)@section('og_image', $images[0]['url'])@endif
@section('content')
@php($fmt = fn($n) => number_format($n, 0, ',', ' '))
<div class="wrap" style="padding-top:20px;display:flex;flex-direction:column;gap:28px">
<nav aria-label="Fil d'Ariane" class="muted" style="font-size:14px"><a href="{{ route('site.index', $g->slug) }}">Nos occasions</a> › {{ $v->label() }}</nav>
<div style="display:flex;flex-wrap:wrap;gap:28px;align-items:flex-start">
<section aria-label="Photos" style="flex:999 1 560px;min-width:0;display:flex;flex-direction:column;gap:12px">
<div style="aspect-ratio:4/3;border-radius:14px;background:#E8ECF4;position:relative;overflow:hidden">
@if($images)
<img id="main-img" src="{{ $images[0]['url'] }}" alt="{{ $images[0]['label'] }}" style="width:100%;height:100%;object-fit:cover">
<span id="counter" style="position:absolute;bottom:12px;right:12px;background:rgba(21,23,28,.75);color:#fff;font-size:13px;font-weight:600;border-radius:999px;padding:5px 12px">1 / {{ count($images) }}</span>
@if(count($images) > 1)
<button type="button" aria-label="Photo précédente" data-step="-1" class="nav-btn" style="left:12px">‹</button>
<button type="button" aria-label="Photo suivante" data-step="1" class="nav-btn" style="right:12px">›</button>
@endif
@else<div style="display:flex;align-items:center;justify-content:center;height:100%" class="muted">Photos à venir</div>@endif
</div>
@if(count($images) > 1)
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(88px,1fr));gap:8px">
@foreach($images as $i => $im)<button type="button" class="thumb" data-i="{{ $i }}" aria-label="{{ $im['label'] }}" style="aspect-ratio:4/3;border-radius:8px;border:2px solid {{ $i === 0 ? '#15171C' : 'transparent' }};padding:0;overflow:hidden;cursor:pointer;background:#eee"><img src="{{ $im['url'] }}" alt="" loading="lazy" style="width:100%;height:100%;object-fit:cover"></button>@endforeach
</div>
@endif
</section>
<aside style="flex:1 1 360px;min-width:0;display:flex;flex-direction:column;gap:16px">
<div><h1 style="font-size:30px;line-height:1.15">{{ $v->label() }}</h1>@if($v->version)<div style="font-size:17px;color:#3D414A;margin-top:6px">{{ $v->version }}</div>@endif
<div class="muted" style="margin-top:6px">{{ collect([$v->first_registration?->year, $v->mileage !== null ? $fmt($v->mileage).' km' : null, \App\Models\Vehicle::FUEL_LABELS[$v->fuel] ?? null, $v->gearbox ? ucfirst($v->gearbox) : null])->filter()->implode(' · ') }}</div></div>
@if($v->price)<span class="price" style="font-size:36px">{{ $fmt($v->price) }} €</span>@endif
@if($g->phone)<a class="btn" style="min-height:52px" href="tel:{{ preg_replace('/[^0-9+]/', '', $g->phone) }}">Appeler le garage : {{ $g->phone }}</a>@endif
<form method="post" action="{{ route('site.lead', $g->slug) }}" style="border:1px solid var(--line);border-radius:14px;padding:18px;display:flex;flex-direction:column;gap:12px">
@csrf
<input type="hidden" name="type" value="vehicle"><input type="hidden" name="vehicle_id" value="{{ $v->id }}"><input type="hidden" name="source" value="fiche">
<label class="sr">Site web<input name="site_web" tabindex="-1" autocomplete="off"></label>
<strong style="font-size:17px">Elle vous intéresse ?</strong>
<span class="muted" style="font-size:14px;margin-top:-6px">Laissez votre numéro, on vous rappelle.</span>
@if($errors->any())<div class="errors">{{ $errors->first() }}</div>@endif
<label class="field">Votre nom<input class="input" name="name" autocomplete="name" value="{{ old('name') }}" required></label>
<label class="field">Votre téléphone<input class="input" type="tel" name="phone" autocomplete="tel" value="{{ old('phone') }}" required></label>
<label class="field">Votre message<textarea class="input" name="message" rows="3">{{ old('message', 'Bonjour, la '.$v->label().($v->price ? ' à '.$fmt($v->price).' €' : '').' est-elle toujours disponible ? J\'aimerais venir la voir.') }}</textarea></label>
<label style="display:flex;gap:10px;align-items:flex-start;font-size:14px"><input type="checkbox" name="wants_trade_in" value="1" style="width:20px;height:20px;margin:0">J'ai une voiture à faire reprendre</label>
<label style="display:flex;gap:10px;align-items:flex-start;font-size:14px"><input type="checkbox" name="consent" value="1" required style="width:20px;height:20px;margin:0;flex-shrink:0">J'accepte d'être recontacté au sujet de cette voiture.</label>
<button class="btn dark" type="submit">Envoyer</button>
<span class="muted" style="font-size:12px">Vos coordonnées servent seulement à vous recontacter. <a href="{{ route('site.legal', $g->slug) }}#confidentialite">Confidentialité</a></span>
</form>
</aside>
</div>
@if($v->description)
<section style="max-width:760px"><h2 style="font-size:22px">Ce qu'il faut savoir</h2><p style="font-size:16px;line-height:1.6;color:#2A2D35;white-space:pre-line">{{ $v->description }}</p></section>
@endif
@php($specs = array_filter(['Mise en circulation' => $v->first_registration?->translatedFormat('F Y'), 'Kilométrage' => $v->mileage !== null ? $fmt($v->mileage).' km' : null, 'Carburant' => \App\Models\Vehicle::FUEL_LABELS[$v->fuel] ?? null, 'Boîte' => $v->gearbox ? ucfirst($v->gearbox) : null, 'Puissance' => $v->power_hp ? $v->power_hp.' ch'.($v->fiscal_hp ? ' ('.$v->fiscal_hp.' CV fiscaux)' : '') : null, 'Portes' => $v->doors, 'Places' => $v->seats, 'Couleur' => $v->color]))
@if($specs)
<section><h2 style="font-size:22px">Caractéristiques</h2>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:0 32px;border-top:1px solid var(--line)">
@foreach($specs as $k => $val)<div style="display:flex;justify-content:space-between;gap:12px;padding:12px 0;border-bottom:1px solid #EFEFEA"><span class="muted">{{ $k }}</span><strong style="text-align:right">{{ $val }}</strong></div>@endforeach
</div></section>
@endif
@if(! empty($v->equipment))
<section><h2 style="font-size:22px">Équipements</h2>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:20px">
@foreach($v->equipment as $grp)<div style="background:var(--ground);border-radius:14px;padding:18px"><strong>{{ $grp['group'] ?? '' }}</strong><ul style="margin:10px 0 0;padding-left:18px;line-height:1.8">@foreach($grp['items'] ?? [] as $it)<li>{{ $it }}</li>@endforeach</ul></div>@endforeach
</div></section>
@endif
@if($similar->isNotEmpty())
<section><h2 style="font-size:22px">Dans le même budget</h2><div class="grid-cars">@foreach($similar as $v) @include($theme->view('_card')) @endforeach</div></section>
@endif
</div>
<style>.nav-btn{position:absolute;top:50%;transform:translateY(-50%);width:44px;height:44px;border-radius:22px;border:0;background:#fff;font-size:24px;cursor:pointer}</style>
<script>
(function(){const imgs=@json($images);let i=0;const m=document.getElementById('main-img'),c=document.getElementById('counter'),t=document.querySelectorAll('.thumb');
function go(n){if(!imgs.length)return;i=(n+imgs.length)%imgs.length;m.src=imgs[i].url;m.alt=imgs[i].label;c.textContent=(i+1)+' / '+imgs.length;t.forEach((b,k)=>b.style.borderColor=k===i?'#15171C':'transparent');}
document.querySelectorAll('.nav-btn').forEach(b=>b.onclick=()=>go(i+ +b.dataset.step));t.forEach(b=>b.onclick=()=>go(+b.dataset.i));})();
</script>
@endsection
