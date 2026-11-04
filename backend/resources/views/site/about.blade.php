@extends('site.layout')
@section('title', 'Le garage')
@section('content')
<section style="background:var(--ground)"><div class="wrap" style="padding-top:48px;padding-bottom:48px;display:flex;flex-direction:column;gap:16px;max-width:900px">
<h1 style="font-size:38px;line-height:1.1">{{ $g->name }}</h1>
@if($g->about)<p style="font-size:17px;line-height:1.6;color:#2A2D35;margin:0;white-space:pre-line">{{ $g->about }}</p>@endif
<div style="display:flex;gap:12px;flex-wrap:wrap"><a class="btn" href="{{ route('site.index', $g->slug) }}">Voir nos occasions</a><a class="btn ghost" href="{{ route('site.contact', $g->slug) }}">Nous écrire</a></div>
</div></section>
<div class="wrap" style="padding-top:40px;display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:20px">
<div style="border:1px solid var(--line);border-radius:14px;padding:22px"><strong style="font-size:18px">Vente d'occasions</strong><p class="muted">Des voitures contrôlées avant la vente, visibles sur place.</p><a href="{{ route('site.index', $g->slug) }}">Voir le stock</a></div>
<div style="border:1px solid var(--line);border-radius:14px;padding:22px"><strong style="font-size:18px">Reprise de votre voiture</strong><p class="muted">On estime votre voiture sur place et on la déduit du prix de la suivante.</p><a href="{{ route('site.contact', [$g->slug, 'sujet' => 'reprise']) }}">Demander une estimation</a></div>
<div style="border:1px solid var(--line);border-radius:14px;padding:22px"><strong style="font-size:18px">Venir au garage</strong><p class="muted" style="white-space:pre-line">{{ collect([$g->address, $g->city])->filter()->implode("\n") ?: 'Adresse à venir' }}</p>@if($g->hours)<p class="muted" style="white-space:pre-line">{{ $g->hours }}</p>@endif
@if($g->address)<a href="https://www.openstreetmap.org/search?query={{ urlencode($g->address.' '.$g->city) }}" rel="noopener">Ouvrir l'itinéraire</a>@endif</div>
</div>
@endsection
