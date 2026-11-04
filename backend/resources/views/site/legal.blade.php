@extends('site.layout')
@section('title', 'Informations légales')
@section('content')
<div class="wrap" style="max-width:820px;padding-top:40px;display:flex;flex-direction:column;gap:12px;line-height:1.65">
<h1>Informations légales</h1>
<h2 id="mentions" style="margin-top:24px">Mentions légales</h2>
<p><strong>Éditeur :</strong> {{ $g->name }}@if($g->address), {{ $g->address }}@endif @if($g->city) {{ $g->city }}@endif.@if($g->phone) Téléphone : {{ $g->phone }}.@endif @if($g->email) E-mail : {{ $g->email }}.@endif</p>
@if(config('app.legal_host'))<p><strong>Hébergement :</strong> {{ config('app.legal_host') }}</p>@endif
<p><strong>Réalisation :</strong> Mending SAS.</p>
<p><strong>Photos et annonces :</strong> les photos sont celles des voitures en vente, présentées dans le décor de notre showroom. La carrosserie n'est pas retouchée. Les prix et caractéristiques sont indicatifs et confirmés au garage avant toute vente.</p>
<h2 id="confidentialite" style="margin-top:24px">Confidentialité</h2>
<p>Quand vous remplissez un formulaire, nous recueillons votre nom, votre téléphone, votre e-mail si vous le donnez, votre message et, pour une reprise, la plaque et le kilométrage de votre voiture. Ces informations servent uniquement à répondre à votre demande. Elles ne sont ni vendues ni transmises à des tiers à des fins commerciales.</p>
<p>Vous pouvez consulter, corriger ou faire supprimer vos données en nous contactant{{ $g->email ? ' à '.$g->email : '' }}. Vous pouvez aussi saisir la CNIL.</p>
<h2 id="cookies" style="margin-top:24px">Cookies</h2>
<p>Ce site ne dépose aucun cookie publicitaire et ne vous suit pas d'un site à l'autre. La fréquentation est mesurée sans cookie et sans permettre de vous identifier.</p>
</div>
@endsection
