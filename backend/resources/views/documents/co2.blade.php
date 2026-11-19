<!doctype html>
<html lang="fr"><head><meta charset="utf-8">@include('documents._base')
<style>
  .page { margin: 12mm 18mm 0; }
  h1 { text-align: center; font-size: 32pt; margin: 2mm 0 1mm; }
  .sub { font-size: 9.5pt; font-style: italic; }
  .band { background: {{ $color }}; color: #1a1a1a; text-align: center; font-size: 15pt; padding: 1.2mm 0; margin-top: 1.5mm; }
  .info { width: 100%; font-size: 12.5pt; border-bottom: 0.3mm solid {{ $color }}; }
  .info td { padding: 1.3mm 0; }
  .info .val { font-weight: bold; text-transform: uppercase; }
  .grid { width: 100%; border-bottom: 0.3mm solid {{ $color }}; }
  .grid td { vertical-align: top; }
  .left { width: 50%; border-right: 0.3mm solid {{ $color }}; padding-right: 3mm; }
  .co2t { font-size: 21pt; font-weight: bold; }
  .expl { font-size: 12pt; }
  .tiny { font-size: 8pt; }
  .lbl { font-size: 14pt; font-weight: bold; margin: 2mm 0 1mm; }
  .big { font-size: 34pt; font-weight: bold; text-align: center; margin-top: 6mm; }
  .big span { color: #3a7ca5; }
  .scale { width: 160mm; }
  .critair { text-align: center; margin-top: 1mm; }
  .critair img { width: 20mm; height: 20mm; }
  .foot { font-size: 10pt; margin: 1.5mm 0 0; }
  .weight { font-size: 14pt; font-weight: bold; margin-top: 1mm; }
</style></head>
<body><div class="page">
  <h1>Émission de CO2</h1>
  <div class="sub">du modèle d'occasion sur la base des données indiquées sur le certificat de première immatriculation du véhicule</div>
  <div class="band">Véhicule d'occasion (VO)</div>
  <table class="info">
    <tr><td style="width: 32mm">Modèle :</td><td class="val">{{ trim($v->make.' '.$v->model) }}</td></tr>
    <tr><td>Version :</td><td class="val">{{ $v->version ?? '–' }}</td></tr>
    <tr><td>Genre :</td><td class="val" style="text-transform: none">{{ $genre }}</td></tr>
    <tr><td>Énergie :</td><td class="val">{{ $fuel ?? '–' }}</td></tr>
  </table>

  <table class="grid"><tr>
    <td class="left">
      <div class="expl"><span class="co2t">CO<sub>2</sub></span>&nbsp; Le CO2 (dioxyde de carbone) est le principal gaz à effet de serre responsable du changement climatique.</div>
      <div class="tiny">Mesures effectuées selon les directives ou règlements CEE en vigueur</div>
    </td>
    <td><div class="big">{{ $v->co2 ?? '–' }} <span>g/km</span></div></td>
  </tr></table>

  <div class="lbl">Émissions de CO2 faibles</div>
  <img class="scale" src="{{ $co2Scale }}" alt="Classe {{ $co2Class ?? 'inconnue' }}">
  <div class="lbl" style="margin-top: 1mm">Émissions de CO2 élevées</div>
  @if ($critairImg)<div class="critair"><img src="{{ $critairImg }}" alt="Crit'Air {{ $critair }}"></div>@endif

  @if ($v->empty_weight)<div class="weight">Poids G1 = {{ $v->empty_weight }}</div>@endif
  <p class="foot">La consommation de carburant et les émissions de CO2 d'un véhicule sont fonction de son rendement énergétique, mais également du comportement au volant et d'autres facteurs non techniques.</p>
  <p class="foot">Les informations sur les consommations de carburant et les émissions de CO2 de tous les modèles de véhicules neufs, contenues dans le guide de l'ADEME, peuvent être obtenues gratuitement dans tous les points de vente, auprès de l'ADEME et consultées sur le site internet : <b style="color: #3a7ca5">www.ademe.fr</b></p>
</div></body></html>
