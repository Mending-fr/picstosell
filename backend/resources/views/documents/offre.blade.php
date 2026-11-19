<!doctype html>
<html lang="fr"><head><meta charset="utf-8">@include('documents._base')
<style>
  .page { position: relative; height: 276mm; margin: 12mm 16mm 0; }
  .title { color: {{ $color }}; margin-top: 7mm; }
  .make { font-size: 26pt; font-weight: bold; text-transform: uppercase; line-height: 1.05; }
  .version { font-size: 17pt; text-transform: uppercase; }
  .photos { width: 100%; margin-top: 5mm; }
  .photos td { width: 50%; padding: 0 1.5mm; }
  .photos img { width: 100%; height: 62mm; }
  .pricebox { width: 100%; margin-top: 5mm; }
  .price { font-size: 34pt; font-weight: bold; }
  .note { font-size: 10pt; font-style: italic; color: #555; }
  .badges { text-align: right; vertical-align: middle; }
  .badge { display: inline-block; border: 0.4mm solid {{ $color }}; color: {{ $color }}; border-radius: 2.5mm; padding: 0.7mm 2.4mm; margin: 0 0 1.5mm 1.5mm; font-size: 10.5pt; font-weight: bold; }
  h2 { font-size: 13pt; color: {{ $color }}; border-bottom: 0.3mm solid {{ $color }}; padding-bottom: 1mm; margin: 6mm 0 2mm; }
  .specs { width: 100%; font-size: 11.5pt; }
  .specs td { padding: 1.1mm 0; border-bottom: 0.2mm solid #e3e3e3; }
  .specs .k { color: #555; width: 45mm; }
  .specs .v { font-weight: bold; }
  .cols td { vertical-align: top; }
  .eq { font-size: 10.5pt; line-height: 1.4; }
  .desc { font-size: 10.5pt; line-height: 1.45; }
  .critair img { width: 16mm; height: 16mm; }
  .contact { position: absolute; left: 0; right: 0; bottom: 4mm; font-size: 10.5pt; color: #555; border-top: 0.3mm solid #e3e3e3; padding-top: 2mm; }
</style></head>
<body><div class="page">
  <table class="head"><tr>
    <td class="lines" style="font-size: 10.5pt">
      @foreach ($headerLines as $i => $line)
        @if ($i === 0)<b>{{ $line }}</b>@else{{ $line }}@endif<br>
      @endforeach
    </td>
    <td style="text-align: right; width: 46mm">@if ($logo)<img class="logo" src="{{ $logo }}" alt="">@endif</td>
  </tr></table>

  <div class="title">
    <div class="make">{{ trim($v->make.' '.$v->model) }}</div>
    @if ($v->version)<div class="version">{{ $v->version }}</div>@endif
  </div>

  @if (count($photos))
  <table class="photos"><tr>
    @foreach ($photos as $p)<td><img src="{{ $p }}" alt=""></td>@endforeach
  </tr></table>
  @endif

  <table class="pricebox"><tr>
    <td style="vertical-align: middle">
      <div class="price">{{ $price ?? '–' }} €</div>
      @if ($priceNote)<div class="note">{{ $priceNote }}</div>@endif
    </td>
    <td class="badges">@foreach ($badges as $b)<span class="badge">{{ $b }}</span>@endforeach</td>
  </tr></table>

  <table class="cols" style="width: 100%"><tr>
    <td style="width: 58%; padding-right: 5mm">
      <h2>Caractéristiques</h2>
      <table class="specs">
        <tr><td class="k">Kilométrage</td><td class="v">{{ $km ? $km.' km' : '–' }}</td></tr>
        <tr><td class="k">Mise en circulation</td><td class="v">{{ $v->first_registration?->format('d/m/Y') ?? '–' }}</td></tr>
        <tr><td class="k">Énergie</td><td class="v">{{ $fuel ?? '–' }}</td></tr>
        @if ($v->gearbox)<tr><td class="k">Boîte de vitesses</td><td class="v">{{ ucfirst($v->gearbox) }}</td></tr>@endif
        @if ($v->power_hp)<tr><td class="k">Puissance</td><td class="v">{{ $v->power_hp }} ch</td></tr>@endif
        <tr><td class="k">Puissance fiscale</td><td class="v">{{ $v->fiscal_hp ? $v->fiscal_hp.' CV' : '–' }}</td></tr>
        @if ($v->color)<tr><td class="k">Couleur</td><td class="v">{{ $v->color }}</td></tr>@endif
        @if ($v->doors || $v->seats)<tr><td class="k">Portes, places</td><td class="v">{{ $v->doors ?? '–' }} portes, {{ $v->seats ?? '–' }} places</td></tr>@endif
        @if ($v->co2 !== null)<tr><td class="k">CO2</td><td class="v">{{ $v->co2 }} g/km{{ $co2Class ? ' (classe '.$co2Class.')' : '' }}</td></tr>@endif
        @if ($critair)<tr><td class="k">Crit'Air</td><td class="v">{{ \App\Services\Vehicle\Emissions::CRITAIR_LABELS[$critair] ?? $critair }}</td></tr>@endif
        @if ($v->vin)<tr><td class="k">Numéro de série</td><td class="v">{{ $v->vin }}</td></tr>@endif
        @if ($origin)<tr><td class="k">Origine</td><td class="v">{{ $origin }}</td></tr>@endif
        @if ($v->warranty)<tr><td class="k">Garantie</td><td class="v">{{ $v->warranty }}</td></tr>@endif
      </table>
    </td>
    <td>
      @if (count($v->equipment ?? []))
        <h2>Équipements</h2>
        <div class="eq">
          @foreach ($v->equipment as $grp)
            @if (count($grp['items'] ?? []))<b>{{ $grp['group'] }} :</b> {{ implode(', ', $grp['items']) }}<br>@endif
          @endforeach
        </div>
      @endif
      @if ($critairImg)<div class="critair" style="margin-top: 4mm"><img src="{{ $critairImg }}" alt=""></div>@endif
    </td>
  </tr></table>

  @if ($v->description)
    <h2>Description</h2>
    <div class="desc">{{ \Illuminate\Support\Str::limit($v->description, 900) }}</div>
  @endif

  <div class="contact">
    {{ $g->name }}@if ($g->address) · {{ $g->address }}@endif @if ($g->city) {{ $g->city }}@endif @if ($g->phone) · {{ $g->phone }}@endif @if ($g->email) · {{ $g->email }}@endif
    @if ($v->file_number)<span style="float: right">Dossier {{ $v->file_number }}</span>@endif
  </div>
</div></body></html>
