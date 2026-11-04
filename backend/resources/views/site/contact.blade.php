@extends('site.layout')
@section('title', 'Contact')
@section('content')
<div class="wrap" style="padding-top:40px;display:flex;flex-wrap:wrap;gap:28px;align-items:flex-start">
<div style="flex:999 1 520px;min-width:0;display:flex;flex-direction:column;gap:16px">
<h1>Nous contacter</h1>
<p class="muted" style="margin:0">Le plus rapide reste le téléphone. Sinon, écrivez-nous, on vous répond vite.</p>
<form method="post" action="{{ route('site.lead', $g->slug) }}" style="border:1px solid var(--line);border-radius:16px;padding:24px;display:flex;flex-direction:column;gap:16px" id="cf">
@csrf<input type="hidden" name="source" value="contact">
<label class="sr">Site web<input name="site_web" tabindex="-1" autocomplete="off"></label>
@if($errors->any())<div class="errors">{{ $errors->first() }}</div>@endif
<fieldset style="border:0;padding:0;margin:0;display:flex;flex-direction:column;gap:8px"><legend class="field" style="margin-bottom:8px">C'est à propos de</legend>
<div style="display:flex;flex-wrap:wrap;gap:8px">
@foreach(['vehicle' => 'Une voiture du stock', 'trade_in' => 'Faire reprendre ma voiture', 'other' => 'Autre chose'] as $k => $l)
@php($checked = old('type', ['voiture' => 'vehicle', 'reprise' => 'trade_in', 'autre' => 'other'][$sujet] ?? 'vehicle') === $k)
<label style="display:flex;gap:8px;align-items:center;border:1px solid #CFCFC8;border-radius:999px;padding:0 16px;min-height:44px;font-weight:600;cursor:pointer"><input type="radio" name="type" value="{{ $k }}" @checked($checked)>{{ $l }}</label>
@endforeach
</div></fieldset>
<div data-for="vehicle"><label class="field">Quelle voiture ?<select class="input" name="vehicle_id"><option value="">Je ne sais pas encore</option>@foreach($vehicles as $v)<option value="{{ $v->id }}" @selected((string) old('vehicle_id', $vehicleId) === (string) $v->id)>{{ $v->label() }}{{ $v->price ? ', '.number_format($v->price, 0, ',', ' ').' €' : '' }}</option>@endforeach</select></label></div>
<div data-for="trade_in" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;background:var(--ground);border-radius:12px;padding:14px">
<label class="field">Plaque de votre voiture<input class="input" name="trade_in_plate" placeholder="AB-123-CD" value="{{ old('trade_in_plate') }}"></label>
<label class="field">Kilométrage<input class="input" name="trade_in_mileage" inputmode="numeric" value="{{ old('trade_in_mileage') }}"></label></div>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px">
<label class="field">Votre nom<input class="input" name="name" autocomplete="name" value="{{ old('name') }}" required></label>
<label class="field">Votre téléphone<input class="input" type="tel" name="phone" autocomplete="tel" value="{{ old('phone') }}" required></label></div>
<label class="field">Votre e-mail (facultatif)<input class="input" type="email" name="email" autocomplete="email" value="{{ old('email') }}"></label>
<label class="field">Votre message<textarea class="input" name="message" rows="4">{{ old('message') }}</textarea></label>
<label style="display:flex;gap:10px;align-items:flex-start;font-size:14px"><input type="checkbox" name="consent" value="1" required style="width:20px;height:20px;margin:0;flex-shrink:0"><span>J'accepte d'être recontacté au sujet de ma demande. <a href="{{ route('site.legal', $g->slug) }}#confidentialite">Ce que vous faites de mes données</a></span></label>
<button class="btn" type="submit" style="min-height:52px">Envoyer ma demande</button>
</form>
</div>
<aside style="flex:1 1 300px;min-width:0;background:var(--ground);border-radius:16px;padding:22px;display:flex;flex-direction:column;gap:16px;margin-top:64px">
@if($g->phone)<div><span class="muted" style="font-size:13px;font-weight:600">Téléphone</span><br><a href="tel:{{ preg_replace('/[^0-9+]/', '', $g->phone) }}" style="font-size:22px;font-weight:700;text-decoration:none">{{ $g->phone }}</a></div>@endif
@if($g->address || $g->city)<div><span class="muted" style="font-size:13px;font-weight:600">Adresse</span><br>{{ $g->address }}<br>{{ $g->city }}</div>@endif
@if($g->hours)<div><span class="muted" style="font-size:13px;font-weight:600">Horaires</span><br><span style="white-space:pre-line">{{ $g->hours }}</span></div>@endif
</aside>
</div>
<script>
(function(){const f=document.getElementById('cf');function sync(){const t=f.querySelector('input[name=type]:checked').value;f.querySelectorAll('[data-for]').forEach(d=>d.style.display=d.dataset.for===t?'':'none');f.querySelectorAll('input[name=type]').forEach(r=>{const l=r.parentElement;l.style.background=r.checked?'#15171C':'#fff';l.style.color=r.checked?'#fff':'#15171C';});}
f.querySelectorAll('input[name=type]').forEach(r=>r.addEventListener('change',sync));sync();})();
</script>
@endsection
