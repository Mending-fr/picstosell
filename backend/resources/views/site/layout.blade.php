@php($brand = $g->brand_color ?: '#B8222A')
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
<link href="https://fonts.googleapis.com/css2?family=Inter+Tight:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
:root{--brand:{{ $brand }};--ink:#15171C;--muted:#5B606B;--soft:#3D414A;--line:#E4E4DF;--ground:#F5F5F2}
*{box-sizing:border-box}
body{margin:0;background:#fff;color:var(--ink);font-family:'Inter Tight',system-ui,sans-serif;display:flex;flex-direction:column;min-height:100vh}
a{color:var(--ink)}a:hover{color:var(--soft)}
.wrap{max-width:1200px;margin:0 auto;padding:0 24px;width:100%}
header.top{border-bottom:1px solid var(--line);background:#fff}
header.top .wrap{padding-top:14px;padding-bottom:14px;display:flex;flex-wrap:wrap;gap:12px 24px;align-items:center;justify-content:space-between}
.logo{text-decoration:none;font-size:20px;font-weight:700;color:var(--brand);display:flex;align-items:center;gap:10px}
.logo img{height:40px;width:auto}
nav.menu{display:flex;gap:24px;flex-wrap:wrap;font-size:15px}
nav.menu a{text-decoration:none;padding:12px 0;border-bottom:2px solid transparent}
nav.menu a[aria-current=page]{font-weight:700;border-color:var(--brand)}
.btn{text-decoration:none;font:inherit;font-weight:700;color:#fff;background:var(--brand);border:0;border-radius:10px;padding:0 18px;min-height:46px;display:inline-flex;align-items:center;justify-content:center;cursor:pointer}
.btn:hover{color:#fff;filter:brightness(.95)}
.btn.dark{background:var(--ink)}
.btn.ghost{background:#fff;color:var(--ink);border:1px solid #CFCFC8}
main{flex:1}
h1{font-size:34px;margin:0}
.muted{color:var(--muted)}
.field{display:flex;flex-direction:column;gap:6px;font-size:13px;color:var(--soft);font-weight:600}
.input{font:inherit;font-size:15px;min-height:46px;border:1px solid #CFCFC8;border-radius:10px;padding:0 12px;background:#fff;color:var(--ink);width:100%}
textarea.input{padding:10px 12px;line-height:1.45;min-height:auto}
.grid-cars{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:20px}
.card{text-decoration:none;color:var(--ink);border:1px solid var(--line);border-radius:14px;overflow:hidden;display:flex;flex-direction:column;background:#fff}
.card .ph{aspect-ratio:4/3;background:#E8ECF4;display:flex;align-items:center;justify-content:center;color:#5B606B;font-size:13px;position:relative;overflow:hidden}
.card .ph img{width:100%;height:100%;object-fit:cover}
.card .body{padding:14px 16px 16px;display:flex;flex-direction:column;gap:6px}
.price{font-weight:700;color:var(--brand)}
footer.bottom{margin-top:48px;background:#15171C;color:#C9CDD4}
footer.bottom .wrap{padding-top:36px;padding-bottom:36px;display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:28px;font-size:14px;line-height:1.6}
footer.bottom a{color:#fff}
footer.bottom strong{color:#fff}
.errors{background:#FDECEC;color:#9A1C1C;border-radius:10px;padding:12px 14px;font-size:14px}
.sr{position:absolute;left:-9999px}
</style>
@if(config('services.vigie.script'))
{!! str_replace('{domain}', request()->getHost(), config('services.vigie.script')) !!}
@endif
</head>
<body>
@if($g->isDemo())<div style="background:#FCF1E3;color:#A55A0B;text-align:center;font-size:14px;padding:8px 16px">Site de démonstration : les voitures, les prix et les coordonnées sont fictifs.</div>@endif
<header class="top"><div class="wrap">
<a class="logo" href="{{ route('site.index', $g->slug) }}">@if($g->logo_path)<img src="{{ \App\Support\Media::url($g->logo_path) }}" alt="">@endif{{ $g->name }}</a>
<nav class="menu" aria-label="Menu">
<a href="{{ route('site.index', $g->slug) }}" @if(request()->routeIs('site.index','site.show')) aria-current="page" @endif>Nos occasions</a>
<a href="{{ route('site.about', $g->slug) }}" @if(request()->routeIs('site.about')) aria-current="page" @endif>Le garage</a>
<a href="{{ route('site.contact', $g->slug) }}" @if(request()->routeIs('site.contact')) aria-current="page" @endif>Contact</a>
</nav>
@if($g->phone)<a class="btn" href="tel:{{ preg_replace('/[^0-9+]/', '', $g->phone) }}">Appeler {{ $g->phone }}</a>@else<a class="btn" href="{{ route('site.contact', $g->slug) }}">Nous contacter</a>@endif
</div></header>
<main>@yield('content')</main>
<footer class="bottom"><div class="wrap">
<div><strong>{{ $g->name }}</strong><br>@if($g->address){{ $g->address }}<br>@endif @if($g->city){{ $g->city }}<br>@endif @if($g->phone){{ $g->phone }}@endif</div>
<div><strong>Horaires</strong><br>{!! nl2br(e($g->hours ?: 'Nous consulter')) !!}</div>
<div><a href="{{ route('site.index', $g->slug) }}">Nos occasions</a><br><a href="{{ route('site.about', $g->slug) }}">Le garage</a><br><a href="{{ route('site.contact', $g->slug) }}">Contact</a></div>
<div><a href="{{ route('site.legal', $g->slug) }}#mentions">Mentions légales</a><br><a href="{{ route('site.legal', $g->slug) }}#confidentialite">Confidentialité</a><br><a href="{{ route('site.legal', $g->slug) }}#cookies">Cookies</a></div>
</div></footer>
</body>
</html>
