<script setup lang="ts">
// Essai d'un décor sur une photo déjà envoyée : le rendu n'est pas ajouté à la voiture.
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { get, post, errorMessage } from '../api'
import ImageViewer from './ImageViewer.vue'
import type { Render, TestPhoto } from '../types'

export interface DecorChoice {
  key: string
  label: string
  image_url: string | null
}

const props = defineProps<{
  choices: DecorChoice[]
  initial: string
  /** Garage à utiliser (espace Mending) ; sinon le garage courant. */
  garageId?: number
}>()

const opts = computed(() => (props.garageId ? { garageId: props.garageId } : {}))
const decor = ref(props.initial)
const photos = ref<TestPhoto[] | null>(null)
const photoId = ref<number | null>(null)
const error = ref('')
const running = ref(false)
const result = ref<(Render & { source_url?: string | null }) | null>(null)
const viewer = ref<number | null>(null)
let timer: ReturnType<typeof setTimeout> | null = null

watch(
  () => props.initial,
  (v) => (decor.value = v),
)

async function loadPhotos() {
  photos.value = null
  photoId.value = null
  try {
    photos.value = (await get<{ data: TestPhoto[] }>('/backdrop/photos', opts.value)).data
    photoId.value = photos.value[0]?.id ?? null
  } catch (e) {
    error.value = errorMessage(e)
    photos.value = []
  }
}
watch(() => props.garageId, loadPhotos, { immediate: true })

const pending = computed(() => !!result.value && ['pending', 'processing'].includes(result.value.status))
const decorName = computed(() => props.choices.find((c) => c.key === decor.value)?.label ?? '')

async function poll(id: number) {
  try {
    result.value = await get<Render & { source_url?: string | null }>(`/backdrop/test/${id}`, opts.value)
  } catch (e) {
    error.value = errorMessage(e)
    running.value = false
    return
  }
  if (pending.value) timer = setTimeout(() => poll(id), 2500)
  else running.value = false
}

async function run() {
  if (!photoId.value || !decor.value) return
  error.value = ''
  running.value = true
  if (timer) clearTimeout(timer)
  try {
    const r = await post<Render>('/backdrop/test', { photo_id: photoId.value, backdrop: decor.value }, opts.value)
    result.value = r
    poll(r.id)
  } catch (e) {
    error.value = errorMessage(e)
    running.value = false
  }
}
onBeforeUnmount(() => timer && clearTimeout(timer))

const viewerItems = computed(() => {
  const r = result.value
  if (!r?.url) return []
  return [
    ...(r.source_url ? [{ url: r.source_url, label: 'Photo du parking' }] : []),
    { url: r.url, label: `Dans le décor « ${decorName.value} »` },
  ]
})
</script>

<template>
  <div class="bt">
    <div class="row" style="gap: 12px; align-items: flex-end">
      <label class="lbl" style="min-width: 220px">
        Décor à essayer
        <select v-model="decor" class="f">
          <option v-for="c in choices" :key="c.key" :value="c.key" :disabled="!c.image_url">{{ c.label }}{{ c.image_url ? '' : ' (pas de photo)' }}</option>
        </select>
      </label>
      <span class="grow" />
      <button type="button" class="btn btn-primary" :disabled="!photoId || running" @click="run">
        <span v-if="running" class="spinner" aria-hidden="true" />{{ running ? 'Essai en cours…' : 'Lancer l\'essai' }}
      </button>
    </div>

    <div class="col" style="gap: 6px">
      <span class="lbl">Photo de voiture</span>
      <div v-if="!photos" class="strip"><span v-for="i in 6" :key="i" class="skel th" /></div>
      <p v-else-if="!photos.length" class="muted" style="margin: 0">Aucune photo extérieure envoyée pour l'instant : ajoutez d'abord une voiture avec ses photos.</p>
      <div v-else role="radiogroup" aria-label="Photo de voiture" class="strip">
        <button
          v-for="p in photos"
          :key="p.id"
          type="button"
          role="radio"
          class="th"
          :aria-checked="photoId === p.id"
          :title="`${p.vehicle} · ${p.label}`"
          @click="photoId = p.id"
        >
          <img v-if="p.thumb_url" :src="p.thumb_url" :alt="`${p.vehicle}, ${p.label}`" loading="lazy" />
        </button>
      </div>
    </div>

    <p v-if="error" class="err" style="margin: 0">{{ error }}</p>

    <div v-if="result" class="res">
      <figure>
        <img v-if="result.source_url" :src="result.source_url" alt="Photo du parking" />
        <figcaption>Photo du parking</figcaption>
      </figure>
      <figure>
        <button v-if="result.url && !pending" type="button" class="zoom" aria-label="Voir en grand" @click="viewer = viewerItems.length - 1">
          <img :src="result.url" :alt="`Dans le décor ${decorName}`" />
        </button>
        <div v-else-if="pending" class="wait"><span class="spinner" aria-hidden="true" /> Préparation, environ une minute…</div>
        <div v-else class="wait ko">L'essai a échoué{{ result.error ? ` : ${result.error}` : '' }}.</div>
        <figcaption>
          Dans le décor « {{ decorName }} »<template v-if="result.cost_usd && !pending"> · {{ result.cost_usd }} $</template>
        </figcaption>
      </figure>
    </div>
    <span class="muted small">Un essai passe par l'IA comme une vraie photo. Il n'est ajouté à aucune voiture.</span>
    <ImageViewer v-if="viewer !== null" :items="viewerItems" :start="viewer" @close="viewer = null" />
  </div>
</template>

<style scoped>
.bt {
  display: flex;
  flex-direction: column;
  gap: 14px;
}
.strip {
  display: flex;
  gap: 8px;
  overflow-x: auto;
  padding: 3px 2px 6px;
}
.th {
  width: 96px;
  height: 72px;
  flex: none;
  border-radius: 8px;
  border: 0;
  padding: 0;
  overflow: hidden;
  background: #ebebeb;
  cursor: pointer;
  outline: 2px solid transparent;
  outline-offset: 2px;
}
.th img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}
.th[aria-checked='true'] {
  outline-color: var(--ink);
}
.res {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
}
figure {
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 6px;
}
figure img,
.wait {
  width: 100%;
  aspect-ratio: 4 / 3;
  object-fit: cover;
  border-radius: 8px;
  display: block;
  background: #ebebeb;
}
figcaption {
  font-size: 13px;
  color: var(--muted);
}
.zoom {
  padding: 0;
  border: 0;
  background: none;
  cursor: zoom-in;
  display: block;
  width: 100%;
}
.wait {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  color: var(--muted);
  font-size: 14px;
  text-align: center;
  padding: 12px;
}
.wait.ko {
  color: var(--red-ink);
  background: var(--red-tint);
}
.small {
  font-size: 12px;
}
@media (max-width: 640px) {
  .res {
    grid-template-columns: minmax(0, 1fr);
  }
}
</style>
