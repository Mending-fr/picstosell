<script setup lang="ts">
// Mon showroom : on choisit la photo du showroom, c'est tout. La voiture y est posée automatiquement.
import { onMounted, ref } from 'vue'
import { get, post, errorMessage } from '../api'
import { toast } from '../session'
import type { Shot, Showroom } from '../types'

const shot = ref<Shot | null>(null)
const loaded = ref(false)
const error = ref('')
const uploading = ref(false)
const fileInput = ref<HTMLInputElement | null>(null)
const over = ref(false)

async function load() {
  error.value = ''
  try {
    const rooms = (await get<{ data: Showroom[] }>('/showrooms')).data
    shot.value = rooms[0]?.shots[0] ?? null
  } catch (e) {
    error.value = errorMessage(e)
  } finally {
    loaded.value = true
  }
}
onMounted(load)

async function send(f: File | undefined) {
  if (!f || !shot.value) return
  if (!f.type.startsWith('image/')) {
    toast('Choisissez une photo.', 'error')
    return
  }
  uploading.value = true
  const fd = new FormData()
  fd.append('file', f)
  try {
    const s = await post<Shot>(`/shots/${shot.value.id}/image`, fd)
    shot.value = { ...shot.value, image_url: s.image_url, configured: s.configured }
    toast('Showroom enregistré. Les prochaines voitures y seront posées.')
  } catch (err) {
    toast(errorMessage(err), 'error')
  } finally {
    uploading.value = false
  }
}

function onPick(e: Event) {
  const t = e.target as HTMLInputElement
  send(t.files?.[0])
  t.value = ''
}
function onDrop(e: DragEvent) {
  over.value = false
  send(e.dataTransfer?.files?.[0])
}
</script>

<template>
  <div class="page-head">
    <h1>Mon showroom</h1>
    <button v-if="shot?.image_url" type="button" class="btn" :disabled="uploading" @click="fileInput?.click()">
      <span v-if="uploading" class="spinner" aria-hidden="true" />Changer la photo
    </button>
  </div>

  <p v-if="error" class="alert alert-error" role="alert">{{ error }} <button type="button" class="btn-link" @click="load">Réessayer</button></p>

  <div v-if="!loaded" class="skel" style="height: 480px; border-radius: 12px" />

  <template v-else-if="shot">
    <input ref="fileInput" type="file" accept="image/*" class="sr-only" tabindex="-1" aria-hidden="true" @change="onPick" />

    <figure v-if="shot.image_url" class="card shown">
      <img :src="shot.image_url" alt="Photo du showroom" />
      <figcaption class="muted">Vos voitures sont posées dans ce décor.</figcaption>
    </figure>

    <div
      v-else
      class="drop"
      :class="{ over }"
      @dragover.prevent="over = true"
      @dragleave.prevent="over = false"
      @drop.prevent="onDrop"
    >
      <strong>Choisissez la photo de votre showroom</strong>
      <span class="muted">Le showroom vide, pris à hauteur de poitrine, téléphone à l'horizontale.</span>
      <button type="button" class="btn btn-primary" :disabled="uploading" @click="fileInput?.click()">
        <span v-if="uploading" class="spinner" aria-hidden="true" />Choisir une photo
      </button>
      <span class="muted small">ou glissez-la ici</span>
    </div>
  </template>
</template>

<style scoped>
.shown {
  margin: 0;
  padding: 12px;
  gap: 10px;
  max-width: 1100px;
}
.shown img {
  width: 100%;
  border-radius: 8px;
  display: block;
}
.shown figcaption {
  font-size: 13px;
  padding: 0 4px 2px;
}
.drop {
  max-width: 1100px;
  aspect-ratio: 16 / 9;
  border: 1.5px dashed var(--accent-line);
  border-radius: 12px;
  background: #fff;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 10px;
  text-align: center;
  padding: 24px;
}
.drop.over {
  border-color: var(--accent-ink);
  background: var(--accent-tint);
}
.drop strong {
  font-size: 17px;
  font-weight: 600;
}
.small {
  font-size: 12px;
}
</style>
