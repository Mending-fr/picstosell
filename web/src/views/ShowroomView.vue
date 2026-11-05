<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { onBeforeRouteLeave } from 'vue-router'
import { get, patch, post, errorMessage } from '../api'
import { toast } from '../session'
import type { Placement, Shot, Showroom } from '../types'

const DEFAULT: Placement = { height: 42, bottom: 12, offset_x: 0 }

const shot = ref<Shot | null>(null)
const loaded = ref(false)
const error = ref('')
const place = reactive<Placement>({ ...DEFAULT })
const saved = ref('')
const saving = ref(false)
const uploading = ref(false)
const fileInput = ref<HTMLInputElement | null>(null)
const ratio = ref(3 / 2)

const dirty = computed(() => JSON.stringify({ ...place }) !== saved.value)

function fill(s: Shot) {
  shot.value = s
  place.height = s.placement.height
  place.bottom = s.placement.bottom
  place.offset_x = s.placement.offset_x
  saved.value = JSON.stringify({ ...place })
}

async function load() {
  error.value = ''
  try {
    const rooms = (await get<{ data: Showroom[] }>('/showrooms')).data
    const s = rooms[0]?.shots[0]
    if (s) fill(s)
  } catch (e) {
    error.value = errorMessage(e)
  } finally {
    loaded.value = true
  }
}
onMounted(load)

async function save() {
  if (!shot.value) return
  saving.value = true
  try {
    fill(await patch<Shot>(`/shots/${shot.value.id}`, { placement: { ...place } }))
    toast('Zone au sol enregistrée. Les prochaines photos seront posées à cet endroit.')
  } catch (e) {
    toast(errorMessage(e), 'error')
  } finally {
    saving.value = false
  }
}

async function upload(e: Event) {
  const t = e.target as HTMLInputElement
  const f = t.files?.[0]
  t.value = ''
  if (!f || !shot.value) return
  uploading.value = true
  const fd = new FormData()
  fd.append('file', f)
  try {
    const s = await post<Shot>(`/shots/${shot.value.id}/image`, fd)
    shot.value = { ...shot.value, image_url: s.image_url, configured: s.configured }
    toast('Photo du showroom enregistrée.')
  } catch (err) {
    toast(errorMessage(err), 'error')
  } finally {
    uploading.value = false
  }
}

function onImg(e: Event) {
  const img = e.target as HTMLImageElement
  if (img.naturalWidth && img.naturalHeight) ratio.value = img.naturalWidth / img.naturalHeight
}

function reset() {
  Object.assign(place, DEFAULT)
}

// Les silhouettes sont dessinées au plus près (viewBox recadré) : leur hauteur à l'écran est la hauteur de la voiture.
const silStyle = computed(() => ({
  height: place.height + '%',
  bottom: place.bottom + '%',
  left: `calc(50% + ${place.offset_x}%)`,
}))

onBeforeRouteLeave(() => !dirty.value || window.confirm("La zone au sol n'est pas enregistrée. Quitter quand même ?"))
</script>

<template>
  <div class="page-head">
    <h1>Mon showroom</h1>
    <span v-if="shot" class="pill" :style="shot.configured ? { color: 'var(--green-ink)', background: 'var(--green-tint)' } : { color: 'var(--amber-ink)', background: 'var(--amber-tint)' }">
      {{ shot.configured ? 'Prêt' : 'Photo à ajouter' }}
    </span>
    <span class="grow" />
    <span v-if="dirty" class="unsaved">Zone non enregistrée</span>
    <template v-if="shot">
      <input ref="fileInput" type="file" accept="image/*" class="sr-only" tabindex="-1" aria-hidden="true" @change="upload" />
      <button type="button" class="btn" :disabled="uploading" @click="fileInput?.click()">
        <span v-if="uploading" class="spinner" aria-hidden="true" />{{ shot.image_url ? 'Remplacer la photo' : 'Ajouter la photo du showroom' }}
      </button>
      <button type="button" class="btn btn-primary" :disabled="saving || !dirty" @click="save">
        <span v-if="saving" class="spinner" aria-hidden="true" />{{ dirty ? 'Enregistrer' : 'Enregistré' }}
      </button>
    </template>
  </div>

  <p v-if="error" class="alert alert-error" role="alert">{{ error }} <button type="button" class="btn-link" @click="load">Réessayer</button></p>

  <div v-if="!loaded" class="skel" style="height: 520px; border-radius: 16px" />

  <div v-else-if="shot" class="cols">
    <section aria-label="Le décor et la zone au sol" class="card editor">
      <div class="row between">
        <span style="font-size: 16px; font-weight: 500">La zone où poser les voitures</span>
        <span class="muted" style="font-size: 13px">Les deux contours doivent tenir sur le sol, à la bonne taille</span>
      </div>
      <div class="stage" :class="{ empty: !shot.image_url }" :style="{ aspectRatio: String(ratio) }">
        <img v-if="shot.image_url" :src="shot.image_url" alt="Photo du showroom" class="bg" @load="onImg" />
        <span v-else class="hint">Ajoutez une photo de votre showroom vide : c'est le décor de toutes vos voitures</span>
        <!-- de profil : la vue la plus longue -->
        <svg class="sil side" :style="silStyle" viewBox="15 55 305 120" preserveAspectRatio="xMidYMax meet" aria-hidden="true">
          <path d="M20 140 L40 95 Q60 70 110 62 L200 58 Q245 60 270 85 L305 100 Q318 110 315 140 Z" /><circle cx="85" cy="145" r="26" /><circle cx="255" cy="145" r="26" />
        </svg>
        <!-- de face : la vue la plus étroite, même hauteur -->
        <svg class="sil front" :style="silStyle" viewBox="55 50 220 124" preserveAspectRatio="xMidYMax meet" aria-hidden="true">
          <path d="M60 150 L60 95 Q70 60 110 52 L220 52 Q260 60 270 95 L270 150 Z" /><rect x="95" y="62" width="140" height="38" rx="6" /><rect x="70" y="150" width="40" height="22" rx="4" /><rect x="220" y="150" width="40" height="22" rx="4" />
        </svg>
      </div>
      <div class="grid-fields">
        <label class="lbl">Hauteur de la voiture <span class="muted" style="font-weight: 400">{{ place.height }} % de la photo</span><input v-model.number="place.height" type="range" min="10" max="90" /></label>
        <label class="lbl">Distance au bas de la photo <span class="muted" style="font-weight: 400">{{ place.bottom }} %</span><input v-model.number="place.bottom" type="range" min="0" max="60" /></label>
        <label class="lbl">Décalage gauche ou droite <span class="muted" style="font-weight: 400">{{ place.offset_x > 0 ? '+' : '' }}{{ place.offset_x }} %</span><input v-model.number="place.offset_x" type="range" min="-50" max="50" /></label>
      </div>
      <div class="row">
        <span class="legend"><span class="sw side" />De profil</span>
        <span class="legend"><span class="sw front" />De face</span>
        <button type="button" class="btn-link" style="margin-left: auto" @click="reset">Revenir au réglage par défaut</button>
      </div>
    </section>

    <aside class="tips">
      <section class="card tip">
        <h2 style="font-size: 15px">Un seul décor pour toutes les photos</h2>
        <p class="muted">Toutes les photos extérieures de vos voitures sont posées dans ce décor, à la même hauteur. Entre une vue de face et une vue de profil, seule la longueur de la voiture change.</p>
      </section>
      <section class="card tip">
        <h2 style="font-size: 15px">Pour photographier le showroom</h2>
        <ul>
          <li>Tenez le téléphone à hauteur de poitrine, comme sur le parking. Sinon la voiture semblera flotter ou s'enfoncer dans le sol.</li>
          <li>Téléphone à l'horizontale, bien droit.</li>
          <li>L'endroit où poser la voiture vide, avec le sol visible devant.</li>
          <li>Les lumières allumées, comme pour une visite.</li>
        </ul>
      </section>
    </aside>
  </div>
</template>

<style scoped>
.page-head {
  flex-wrap: nowrap;
  align-items: center;
}
.unsaved {
  font-size: 13px;
  font-weight: 500;
  color: var(--amber-ink);
  white-space: nowrap;
}
.cols {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 320px;
  gap: 18px;
  align-items: start;
}
.editor {
  min-width: 0;
  padding: 18px 20px;
  gap: 14px;
}
.stage {
  width: 100%;
  border-radius: 12px;
  position: relative;
  overflow: hidden;
  background: #d9d4ca;
}
.stage.empty {
  background: linear-gradient(#d9d4ca 0 62%, #bdb6a8 62% 100%);
  aspect-ratio: 3 / 2;
}
.bg {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}
.hint {
  position: absolute;
  top: 12px;
  left: 12px;
  font-size: 13px;
  color: var(--ink-2);
  background: rgba(255, 255, 255, 0.85);
  border-radius: 6px;
  padding: 3px 8px;
}
.sil {
  position: absolute;
  transform: translateX(-50%);
  overflow: visible;
  pointer-events: none;
  fill: none;
  stroke-width: 2.5;
  vector-effect: non-scaling-stroke;
  filter: drop-shadow(0 0 1px rgba(0, 0, 0, 0.5));
}
.sil.side {
  stroke: #e3b23c;
  stroke-dasharray: 10 7;
}
.sil.front {
  stroke: #fff;
  stroke-dasharray: 4 5;
}
.grid-fields {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 18px;
}
.grid-fields input[type='range'] {
  accent-color: var(--accent);
}
.legend {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  color: var(--muted);
  margin-right: 14px;
}
.sw {
  width: 22px;
  height: 0;
  border-top: 3px dashed;
}
.sw.side {
  border-color: #e3b23c;
}
.sw.front {
  border-color: #8a90a0;
  border-top-style: dotted;
}
.tips {
  display: flex;
  flex-direction: column;
  gap: 18px;
  min-width: 0;
}
.tip {
  padding: 18px 20px;
  gap: 10px;
}
.tip p {
  margin: 0;
  font-size: 14px;
  line-height: 1.55;
}
.tip ul {
  margin: 0;
  padding-left: 18px;
  font-size: 14px;
  line-height: 1.55;
  color: var(--ink-2);
  display: flex;
  flex-direction: column;
  gap: 6px;
}
@media (max-width: 1100px) {
  .cols {
    grid-template-columns: minmax(0, 1fr);
  }
}
@media (max-width: 860px) {
  .page-head {
    flex-wrap: wrap;
  }
  .grid-fields {
    grid-template-columns: minmax(0, 1fr);
  }
}
</style>
