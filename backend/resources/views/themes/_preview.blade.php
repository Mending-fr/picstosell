@if(! empty($previewing))
<div style="position:sticky;top:0;z-index:100;background:#1F2833;color:#fff;font:500 14px/1.4 system-ui,sans-serif;padding:10px 16px;display:flex;gap:16px;justify-content:center;align-items:center;flex-wrap:wrap">
<span>Aperçu du thème « {{ $previewing }} ». Les visiteurs voient toujours le thème actuel.</span>
<a href="{{ request()->fullUrlWithQuery(['apercu' => 'fin', 'theme' => null, 'signature' => null, 'expires' => null]) }}" style="color:#E3B23C">Quitter l'aperçu</a>
</div>
@endif
