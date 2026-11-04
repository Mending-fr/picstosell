<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { get, patch, post, errorMessage } from '../api'
import { toast } from '../session'
import type { Placement, Shot, Showroom } from '../types'

const showrooms = ref<Showroom[] | null>(null)
const error = ref('')
const selId = ref<number | null>(null)
const place = reactive<Placement>({ scale: 66, bottom: 12, offset_x: 0 })
const saved = ref('')
const saving = ref(false)
const uploading = ref(false)
const fileInput = ref<HTMLInputElement | null>(null)

const shots = computed<Shot[]>(() => [...(showrooms.value?.[0]?.shots ?? [])].sort((a, b) => a.position - b.position))
const cur = computed(() => shots.value.find((s) => s.id === selId.value) ?? null)
const dirty = computed(() => JSON.stringify({ ...place }) !== saved.value)

function select(s: Shot) {
  if (dirty.value && cur.value && !window.confirm("Le réglage de cet angle n'est pas enregistré. Changer d'angle quand même ?")) return
  selId.value = s.id
  place.scale = s.placement.scale
  place.bottom = s.placement.bottom
  place.offset_x = s.placement.offset_x
  saved.value = JSON.stringify({ ...place })
}

async function load() {
  error.value = ''
  try {
    showrooms.value = (await get<{ data: Showroom[] }>('/showrooms')).data
    const first = shots.value.find((s) => !s.configured) ?? shots.value[0]
    if (first) select(first)
  } catch (e) {
    error.value = errorMessage(e)
  }
}
onMounted(load)

function replaceShot(s: Shot) {
  const room = showrooms.value?.[0]
  if (!room) return
  room.shots = room.shots.map((x) => (x.id === s.id ? s : x))
}

async function save() {
  if (!cur.value) return
  saving.value = true
  try {
    const s = await patch<Shot>(`/shots/${cur.value.id}`, { placement: { ...place } })
    replaceShot(s)
    saved.value = JSON.stringify({ ...place })
    toast(`Réglage « ${s.label} » enregistré.`)
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
  if (!f || !cur.value) return
  uploading.value = true
  const fd = new FormData()
  fd.append('file', f)
  try {
    const s = await post<Shot>(`/shots/${cur.value.id}/image`, fd)
    replaceShot(s)
    toast('Photo du showroom enregistrée.')
  } catch (err) {
    toast(errorMessage(err), 'error')
  } finally {
    uploading.value = false
  }
}

function reset() {
  place.scale = 66
  place.bottom = 12
  place.offset_x = 0
}
</script>

<template>
  <div class="page-head">
    <div class="col" style="gap: 4px">
      <h1>Mon showroom</h1>
      <p class="lead" style="margin: 0">Les photos de votre showroom, une par angle. Chaque voiture photographiée sur le parking y est placée.</p>
    </div>
  </div>

  <p v-if="error" class="alert alert-error" role="alert">{{ error }} <button type="button" class="btn-link" @click="load">Réessayer</button></p>

  <div role="tablist" aria-label="Angles" class="angles">
    <template v-if="!showrooms">
      <div v-for="i in 6" :key="i" class="skel" style="aspect-ratio: 4/3.7; border-radius: 10px" />
    </template>
    <button v-for="s in shots" :key="s.id" type="button" role="tab" class="angle" :aria-selected="s.id === selId" @click="select(s)">
      <img v-if="s.image_url" :src="s.image_url" alt="" />
      <div v-else class="noimg" />
      <span class="a-l">{{ s.label }}</span>
      <span class="a-s" :style="{ color: s.configured ? '#0B5E39' : '#B45309' }">{{ s.configured ? 'Réglé' : 'À régler' }}</span>
    </button>
  </div>

  <div v-if="cur" class="split">
    <section aria-label="Réglage de l'angle" class="card editor">
      <div class="row between">
        <span style="font-size: 17px; font-weight: 700">{{ cur.label }}</span>
        <span class="muted" style="font-size: 13px">Placez la silhouette là où la voiture doit apparaître</span>
      </div>
      <div class="stage" :class="{ empty: !cur.image_url }">
        <img v-if="cur.image_url" :src="cur.image_url" alt="Photo du showroom" class="bg" />
        <span v-else class="hint">Ajoutez une photo de votre showroom prise depuis cet angle</span>
        <img
          :src="cur.overlay_url"
          alt="Emplacement de la voiture"
          class="sil"
          :style="{ width: place.scale + '%', bottom: place.bottom + '%', left: `calc(50% + ${place.offset_x}%)` }"
        />
      </div>
      <div class="grid-fields">
        <label class="lbl">Taille de la voiture <span class="muted" style="font-weight: 400">{{ place.scale }} %</span><input v-model.number="place.scale" type="range" min="10" max="100" /></label>
        <label class="lbl">Distance au bas de la photo <span class="muted" style="font-weight: 400">{{ place.bottom }} %</span><input v-model.number="place.bottom" type="range" min="0" max="60" /></label>
        <label class="lbl">Décalage gauche ou droite <span class="muted" style="font-weight: 400">{{ place.offset_x > 0 ? '+' : '' }}{{ place.offset_x }} %</span><input v-model.number="place.offset_x" type="range" min="-50" max="50" /></label>
      </div>
      <div class="row">
        <input ref="fileInput" type="file" accept="image/*" class="sr-only" tabindex="-1" aria-hidden="true" @change="upload" />
        <button type="button" class="btn" :disabled="uploading" @click="fileInput?.click()">
          <span v-if="uploading" class="spinner" aria-hidden="true" />{{ cur.image_url ? 'Remplacer la photo' : 'Ajouter la photo du showroom' }}
        </button>
        <button type="button" class="btn-link" @click="reset">Revenir au réglage par défaut</button>
        <button type="button" class="btn btn-dark" style="margin-left: auto" :disabled="saving || !dirty" @click="save">
          <span v-if="saving" class="spinner" aria-hidden="true" />{{ dirty ? 'Enregistrer' : 'Enregistré' }}
        </button>
      </div>
    </section>

    <aside aria-label="Aperçu sur le téléphone" class="phone-col">
      <span class="muted" style="font-size: 13px; font-weight: 600; align-self: flex-start">Ce que verra la personne sur le parking</span>
      <div class="phone">
        <span class="p-title">{{ cur.label }}</span>
        <div class="p-screen">
          <img :src="cur.overlay_url" alt="" style="width: 170px" />
        </div>
        <div class="p-bar"><span class="shutter" /></div>
      </div>
      <span class="muted" style="font-size: 13px; text-align: center">La silhouette aide à cadrer la voiture sous le même angle que votre showroom.</span>
    </aside>
  </div>
</template>

<style scoped>
.angles {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
  gap: 10px;
}
.angle {
  text-align: left;
  border: 2px solid var(--line);
  border-radius: 10px;
  background: #fff;
  padding: 6px;
  display: flex;
  flex-direction: column;
  gap: 6px;
  color: var(--ink);
}
.angle[aria-selected='true'] {
  border-color: var(--accent);
}
.angle img,
.noimg {
  width: 100%;
  aspect-ratio: 4/3;
  border-radius: 6px;
  object-fit: cover;
  background: #d9d4ca;
}
.a-l {
  font-size: 13px;
  font-weight: 600;
}
.a-s {
  font-size: 12px;
}
.split {
  display: flex;
  flex-wrap: wrap;
  gap: 20px;
  align-items: flex-start;
}
.editor {
  flex: 999 1 520px;
  min-width: 0;
  padding: 18px;
  gap: 14px;
}
.stage {
  aspect-ratio: 16/10;
  border-radius: 10px;
  position: relative;
  overflow: hidden;
  background: #d9d4ca;
}
.stage.empty {
  background: linear-gradient(#d9d4ca 0 62%, #bdb6a8 62% 100%);
}
.bg {
  width: 100%;
  height: 100%;
  object-fit: cover;
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
  filter: drop-shadow(0 0 1px rgba(0, 0, 0, 0.6));
  background: rgba(31, 79, 216, 0.1);
  border-radius: 8px;
  pointer-events: none;
}
.phone-col {
  flex: 1 1 240px;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 10px;
  align-items: center;
}
.phone {
  width: 220px;
  height: 420px;
  border-radius: 28px;
  background: #0e0f12;
  padding: 12px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.p-title {
  color: #fff;
  font-size: 12px;
  text-align: center;
  padding-top: 8px;
}
.p-screen {
  flex: 1;
  border-radius: 14px;
  background: repeating-linear-gradient(135deg, #2a2c31 0 12px, #24262b 12px 24px);
  display: flex;
  align-items: center;
  justify-content: center;
}
.p-bar {
  height: 52px;
  display: flex;
  align-items: center;
  justify-content: center;
}
.shutter {
  width: 44px;
  height: 44px;
  border-radius: 22px;
  border: 3px solid #fff;
  display: block;
}
</style>
