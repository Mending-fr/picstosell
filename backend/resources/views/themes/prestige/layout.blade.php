@php
$brand = $g->brand_color ?: '#1E7A50';
$tel = $g->phone ? preg_replace('/[^0-9+]/', '', $g->phone) : null;
$hoursFirst = $g->hours ? trim(strtok($g->hours, "\n")) : null;
$finance = \App\Support\Themes\Finance::enabled($theme);
@endphp
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
@if($g->isDemo())<meta name="robots" content="noindex, nofollow">@endif
<title>@yield('title', 'Nos occasions') · {{ $g->name }}</title>
<meta name="description" content="@yield('description', 'Voitures d\'occasion chez '.$g->name.($g->city ? ' à '.$g->city : '').'.')">
@hasSection('og_image')<meta property="og:image" content="@yield('og_image')">@endif
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,300;9..144,400;9..144,500&family=Inter+Tight:wght@400;500;600&display=swap" rel="stylesheet">
<style>
:root{--brand:{{ $brand }};--ink:#15171C;--soft:#4B4F58;--muted:#6B6F78;--line:#ECE9E2;--ground:#F7F5F0;--night:#15171C;--night-2:#0F1114;--gold:#E3B23C}
*{box-sizing:border-box}
html{scroll-behavior:smooth}
body{margin:0;background:var(--ground);color:var(--ink);font-family:'Inter Tight',system-ui,sans-serif;-webkit-font-smoothing:antialiased;display:flex;flex-direction:column;min-height:100vh}
a{color:inherit}
img{display:block;max-width:100%}
.wrap{max-width:1280px;margin:0 auto;padding:0 40px;width:100%}
.serif{font-family:'Fraunces',Georgia,serif;font-weight:400;letter-spacing:-.02em}
h1,h2,h3{margin:0;font-family:'Fraunces',Georgia,serif;font-weight:400;letter-spacing:-.02em;line-height:1.08}
h1{font-size:46px}
.eyebrow{font-size:12px;font-weight:600;letter-spacing:.14em;text-transform:uppercase;color:var(--brand)}
.muted{color:var(--muted)}
.btn{font:inherit;font-size:14.5px;font-weight:600;min-height:48px;padding:0 22px;border-radius:999px;border:1px solid transparent;display:inline-flex;align-items:center;justify-content:center;gap:8px;text-decoration:none;cursor:pointer;white-space:nowrap;background:var(--brand);color:#fff;transition:filter .15s ease,border-color .15s ease}
.btn:hover{filter:brightness(1.08);color:#fff}
.btn.dark{background:var(--night);color:#fff}
.btn.ghost{background:#fff;color:var(--ink);border-color:#DDD9D0}
.btn.ghost:hover{border-color:var(--ink);filter:none;color:var(--ink)}
.btn.line{background:transparent;color:#fff;border-color:rgba(255,255,255,.35)}
.btn.line:hover{border-color:#fff;filter:none}
.btn.lg{min-height:54px;padding:0 26px;font-size:15px}
.field{display:flex;flex-direction:column;gap:6px;font-size:13px;color:var(--soft);font-weight:500}
.input{font:inherit;font-size:15px;min-height:50px;border:1px solid #DDD9D0;border-radius:12px;padding:0 14px;background:#fff;color:var(--ink);width:100%}
textarea.input{padding:12px 14px;line-height:1.5;min-height:auto}
.input:focus{outline:2px solid var(--brand);outline-offset:1px;border-color:transparent}
.errors{background:#FBECEB;color:#9A1C1C;border-radius:12px;padding:12px 14px;font-size:14px}
.sr{position:absolute;left:-9999px}
.price{font-weight:600;color:var(--ink)}
.grid-cars{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:22px}
.card{background:#fff;border-radius:18px;overflow:hidden;text-decoration:none;color:var(--ink);display:flex;flex-direction:column;border:1px solid var(--line);transition:transform .15s ease,box-shadow .15s ease}
.card:hover{transform:translateY(-3px);box-shadow:0 18px 40px rgba(21,23,28,.10)}
.card .ph{aspect-ratio:4/3;position:relative;overflow:hidden;background:radial-gradient(120% 90% at 50% 10%,#F2F0EA 0%,#DFDCD4 60%,#CFCBC2 100%);display:flex;align-items:center;justify-content:center;color:var(--muted);font-size:13px}
.card .ph img{width:100%;height:100%;object-fit:cover}
.card .body{padding:18px 20px 20px;display:flex;flex-direction:column;gap:6px}
.chip{font-size:12px;font-weight:500;color:var(--soft);background:#F1EFE9;border-radius:999px;padding:5px 10px;white-space:nowrap}
.tag{position:absolute;top:14px;left:14px;font-size:12px;font-weight:600;background:var(--night);color:#fff;border-radius:999px;padding:6px 11px;z-index:1}
.panel{background:#fff;border:1px solid var(--line);border-radius:20px}
/* en-tête */
.info{background:var(--night-2);color:#A8ACB5;font-size:12.5px}
.info .wrap{min-height:36px;display:flex;align-items:center;justify-content:space-between;gap:24px}
.info strong{color:#fff;font-weight:500}
header.top{background:var(--night);position:sticky;top:0;z-index:20}
header.top .wrap{min-height:76px;display:flex;align-items:center;gap:40px}
.logo{text-decoration:none;color:#fff;font-size:23px;display:flex;align-items:center;gap:12px;flex-shrink:0}
.logo .mark{width:34px;height:34px;border-radius:9px;background:var(--brand);display:inline-flex;align-items:center;justify-content:center;font-family:'Inter Tight',sans-serif;font-size:12px;font-weight:600;letter-spacing:0}
.logo img{height:38px;width:auto;border-radius:6px}
nav.menu{display:flex;gap:28px;flex:1}
nav.menu a{text-decoration:none;color:#D9DBE0;font-size:14px;font-weight:500;padding:10px 0;border-bottom:1px solid transparent}
nav.menu a:hover,nav.menu a[aria-current=page]{color:#fff;border-bottom-color:var(--brand)}
.head-cta{display:flex;gap:10px}
/* pied */
footer.bottom{background:var(--night-2);color:#A8ACB5;margin-top:auto}
footer.bottom .cols{padding-top:56px;padding-bottom:32px;display:grid;grid-template-columns:1.4fr 1fr 1fr 1fr;gap:40px;font-size:14px;line-height:1.7}
footer.bottom .cols strong{color:#fff;font-weight:600;display:block}
footer.bottom a{text-decoration:none}
footer.bottom a:hover{color:#fff}
footer.bottom .legal{padding-top:18px;padding-bottom:28px;border-top:1px solid #23262D;font-size:12.5px;display:flex;justify-content:space-between;gap:20px;flex-wrap:wrap}
.mbar{display:none}
@media (max-width:960px){
  .wrap{padding:0 20px}
  h1{font-size:34px}
  .info{display:none}
  header.top .wrap{min-height:64px;gap:16px}
  nav.menu,.head-cta .btn.line{display:none}
  .logo{font-size:19px}
  footer.bottom .cols{grid-template-columns:1fr;gap:24px}
  .mbar{display:flex;gap:10px;position:sticky;bottom:0;z-index:20;padding:12px 16px 16px;background:linear-gradient(180deg,rgba(247,245,240,0),var(--ground) 40%)}
  .mbar .btn{flex:1}
}
</style>
@if(config('services.vigie.script'))
{!! str_replace('{domain}', request()->getHost(), config('services.vigie.script')) !!}
@endif
</head>
<body>
@include('themes._preview')
@if($g->isDemo())<div style="background:#FCF1E3;color:#A55A0B;text-align:center;font-size:14px;padding:8px 16px">Site de démonstration : les voitures, les prix et les coordonnées sont fictifs.</div>@endif
<div class="info"><div class="wrap">
<span>{{ collect([$g->address ? $g->address.($g->city ? ', '.$g->city : '') : $g->city, $hoursFirst])->filter()->implode(' · ') }}</span>
@if($g->phone)<a href="tel:{{ $tel }}" style="text-decoration:none"><strong>{{ $g->phone }}</strong></a>@endif
</div></div>
<header class="top"><div class="wrap">
<a class="logo serif" href="{{ route('site.index', $g->slug) }}">@if($g->logo_path)<img src="{{ \App\Support\Media::url($g->logo_path) }}" alt="">@else<span class="mark">{{ $g->initials() }}</span>@endif{{ $g->name }}</a>
<nav class="menu" aria-label="Menu">
<a href="{{ route('site.index', $g->slug) }}" @if(request()->routeIs('site.index','site.show')) aria-current="page" @endif>Nos occasions</a>
<a href="{{ route('site.about', $g->slug) }}" @if(request()->routeIs('site.about')) aria-current="page" @endif>Le garage</a>
<a href="{{ route('site.contact', [$g->slug, 'sujet' => 'reprise']) }}">Faire reprendre ma voiture</a>
<a href="{{ route('site.contact', $g->slug) }}" @if(request()->routeIs('site.contact')) aria-current="page" @endif>Contact</a>
</nav>
<div class="head-cta">
@if($g->phone)<a class="btn line" href="tel:{{ $tel }}">Appeler</a>@endif
<a class="btn" href="{{ route('site.contact', $g->slug) }}">Nous écrire</a>
</div>
</div></header>

<main style="flex:1">@yield('content')</main>

<footer class="bottom">
<div class="wrap cols">
<div><span class="serif" style="color:#fff;font-size:22px;display:block;margin-bottom:8px">{{ $g->name }}</span>
@if($g->address){{ $g->address }}<br>@endif @if($g->city){{ $g->city }}<br>@endif
@if($g->phone)<a href="tel:{{ $tel }}" style="color:#fff;font-weight:500;font-size:16px">{{ $g->phone }}</a>@endif</div>
<div><strong>Horaires</strong><span style="white-space:pre-line">{{ $g->hours ?: 'Sur rendez-vous' }}</span></div>
<div><strong>Le site</strong><a href="{{ route('site.index', $g->slug) }}">Nos occasions</a><br><a href="{{ route('site.contact', [$g->slug, 'sujet' => 'reprise']) }}">Faire reprendre ma voiture</a><br><a href="{{ route('site.about', $g->slug) }}">Le garage</a><br><a href="{{ route('site.contact', $g->slug) }}">Contact</a></div>
<div><strong>Informations</strong><a href="{{ route('site.legal', $g->slug) }}#mentions">Mentions légales</a><br><a href="{{ route('site.legal', $g->slug) }}#confidentialite">Confidentialité</a><br><a href="{{ route('site.legal', $g->slug) }}#cookies">Cookies</a></div>
</div>
<div class="wrap legal">
<span>Prix TTC, hors frais d'immatriculation.@if($finance) Mensualités données à titre d'exemple : sur {{ (int) $theme->opt('finance_months', 60) }} mois, apport de {{ number_format((int) $theme->opt('finance_deposit', 0), 0, ',', ' ') }} €, TAEG fixe de {{ str_replace('.', ',', (string) $theme->opt('finance_rate')) }} %, sous réserve d'acceptation du dossier. Un crédit vous engage et doit être remboursé. Vérifiez vos capacités de remboursement avant de vous engager.@endif</span>
<span>© {{ date('Y') }} {{ $g->name }}</span>
</div>
</footer>
<div class="mbar">
@if($g->phone)<a class="btn dark" href="tel:{{ $tel }}">Appeler</a>@endif
<a class="btn" href="{{ route('site.contact', $g->slug) }}">Nous écrire</a>
</div>
</body>
</html>
