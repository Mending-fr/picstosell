@extends($theme->view('layout'))
@section('title', 'Message envoyé')
@section('content')
<div class="wrap" style="max-width:640px;padding-top:80px;display:flex;flex-direction:column;align-items:center;text-align:center;gap:18px">
<div style="width:72px;height:72px;border-radius:36px;background:#E3F4EA;display:flex;align-items:center;justify-content:center"><svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="#0F7A4A" stroke-width="2.5" aria-hidden="true"><path d="M5 12l5 5 9-10"/></svg></div>
<h1 style="font-size:32px">C'est envoyé, merci</h1>
<p style="font-size:17px;line-height:1.6;color:#3D414A;margin:0">On vous rappelle au numéro que vous avez laissé.@if($g->phone) Si c'est urgent, appelez-nous au {{ $g->phone }}.@endif</p>
<div style="display:flex;gap:12px;flex-wrap:wrap;justify-content:center"><a class="btn" href="{{ route('site.index', $g->slug) }}">Revoir nos occasions</a><a class="btn ghost" href="{{ route('site.about', $g->slug) }}">Comment venir au garage</a></div>
</div>
@endsection
