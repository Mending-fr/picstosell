<!doctype html>
<html lang="fr"><head><meta charset="utf-8">@include('documents._base')
<style>
  .page { position: relative; height: 272mm; margin: 16mm 18mm 0; }
  .file { font-size: 12pt; font-weight: bold; font-style: italic; margin-top: 4mm; }
  .title { text-align: center; color: {{ $color }}; margin-top: 6mm; line-height: 1; }
  .make { font-size: 38pt; font-weight: bold; text-transform: uppercase; }
  .version { font-size: 36pt; text-transform: uppercase; }
  .plate { font-size: 21pt; font-style: italic; margin-top: 1.5mm; }
  .price { text-align: center; font-size: 64pt; font-weight: bold; margin-top: 3mm; line-height: 1; }
  .price small { font-size: 30pt; }
  .note { text-align: center; font-size: 10pt; font-style: italic; margin-top: 1mm; }
  .specs { width: 100%; margin-top: 5mm; border-bottom: 0.4mm solid #1a1a1a; }
  .specs td { padding: 0; line-height: 1.12; vertical-align: bottom; }
  .k { font-size: 18pt; }
  .v { font-size: 20pt; font-weight: bold; text-align: right; text-transform: uppercase; }
  .ks { font-size: 10.5pt; }
  .vs { font-size: 10.5pt; text-align: right; text-transform: uppercase; }
  .bottom { width: 100%; margin-top: 5mm; }
  .bottom td { vertical-align: middle; }
  .badges { text-align: center; }
  .badge { display: inline-block; border: 0.5mm solid {{ $color }}; color: {{ $color }}; border-radius: 3mm; padding: 1mm 3mm; margin: 0 1mm 2mm; font-size: 12pt; font-weight: bold; }
  .critair { text-align: center; width: 36mm; }
  .critair img { width: 32mm; height: 32mm; }
</style></head>
<body><div class="page">
  <table class="head"><tr>
    <td class="lines">
      @foreach ($headerLines as $i => $line)
        @if ($i === 0)<b>{{ $line }}</b>@else{{ $line }}@endif<br>
      @endforeach
      @if ($g->phone)<span class="sym">&#9742;</span>&nbsp;&nbsp;{{ $g->phone }}@endif
    </td>
    <td style="text-align: right; width: 50mm">@if ($logo)<img class="logo" src="{{ $logo }}" alt="">@endif</td>
  </tr></table>
  @if ($v->file_number)<div class="file">Dossier V.O. n°{{ $v->file_number }}</div>@endif

  <div class="title">
    <div class="make">{{ trim($v->make.' '.$v->model) }}</div>
    @if ($v->version)<div class="version">{{ $v->version }}</div>@endif
    <div class="plate">{{ $v->plate }}</div>
  </div>

  <div class="price">{{ $price ?? '–' }} <small>€</small></div>
  @if ($priceNote)<div class="note">{{ $priceNote }}</div>@endif

  <table class="specs">
    <tr><td class="k">Kilométrage garanti :</td><td class="v">{{ $km ? $km.' kms' : '–' }}</td></tr>
    <tr><td class="k">Mise en circulation :</td><td class="v">{{ $v->first_registration?->format('d/m/y') ?? '–' }}</td></tr>
    <tr><td class="k">Puissance fiscale :</td><td class="v">{{ $v->fiscal_hp ? $v->fiscal_hp.' CV' : '–' }}</td></tr>
    <tr><td class="k">Énergie :</td><td class="v">{{ $fuel ?? '–' }}</td></tr>
    @if ($v->vin)<tr><td class="ks">VIN :</td><td class="vs">{{ $v->vin }}</td></tr>@endif
    @if ($origin)<tr><td class="ks">Origine :</td><td class="vs">{{ $origin }}</td></tr>@endif
    @if ($v->warranty)<tr><td class="k">Garantie :</td><td class="v">{{ $v->warranty }}</td></tr>@endif
  </table>

  <table class="bottom"><tr>
    @if (count($badges))<td class="badges">@foreach ($badges as $b)<span class="badge">{{ $b }}</span> @endforeach</td>@endif
    @if ($critairImg)<td class="critair"><img src="{{ $critairImg }}" alt="Crit'Air {{ $critair }}"></td>@endif
  </tr></table>

  @if ($footer)<div class="footer"><span>{{ $footer }}</span></div>@endif
</div></body></html>
