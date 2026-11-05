<script setup lang="ts">
// Mon showroom : choisir le décor des photos (sa propre photo ou un décor Mending) et l'essayer sur une photo déjà envoyée.
import { computed, onMounted, ref } from 'vue'
import { get, post, put, errorMessage } from '../api'
import { toast } from '../session'
import BackdropTest, { type DecorChoice } from '../components/BackdropTest.vue'
import type { BackdropOptions, Shot } from '../types'

const data = ref<BackdropOptions | null>(null)
const error = ref('')
const saving = ref<string | null>(null)
const uploading = ref(false)
const fileInput = ref<HTMLInputElement | null>(null)

async function load() {
  error.value = ''
  try {
    data.value = await get<BackdropOptions>('/backdrop')
  } catch (e) {
    error.value = errorMessage(e)
  }
}
onMounted(load)

const choices = computed<DecorChoice[]>(() => {
  const d = data.value
  if (!d) return []
  return [{ key: 'own', label: 'Mon showroom', image_url: d.own.image_url }, ...d.library.map((b) => ({ key: `lib:${b.id}`, label: b.name, image_url: b.image_url }))]
})

async function choose(key: string) {
  if (!data.value || data.value.selected === key) return
  if (key === 'own' && !data.value.own.image_url) {
    fileInput.value?.click()
    return
  }
  saving.value = key
  try {
    data.value = await put<BackdropOptions>('/backdrop', { backdrop: key })
    toast('Décor choisi. Les prochaines photos y seront posées.')
  } catch (e) {
    toast(errorMessage(e), 'error')
  } finally {
    saving.value = null
  }
}

async function upload(e: Event) {
  const t = e.target as HTMLInputElement
  const f = t.files?.[0]
  t.value = ''
  if (!f || !data.value?.own.shot_id) return
  uploading.value = true
  const fd = new FormData()
  fd.append('file', f)
  try {
    await post<Shot>(`/shots/${data.value.own.shot_id}/image`, fd)
    data.value = await put<BackdropOptions>('/backdrop', { backdrop: 'own' })
    toast('Photo de votre showroom enregistrée et choisie comme décor.')
  } catch (err) {
    toast(errorMessage(err), 'error')
  } finally {
    uploading.value = false
  }
}
</script>

<template>
  <div class="page-head">
    <h1>Mon showroom</h1>
  </div>

  <p v-if="error" class="alert alert-error" role="alert">{{ error }} <button type="button" class="btn-link" @click="load">Réessayer</button></p>
  <div v-if="!data && !error" class="skel" style="height: 360px; border-radius: 12px" />

  <template v-if="data">
    <input ref="fileInput" type="file" accept="image/*" class="sr-only" tabindex="-1" aria-hidden="true" @change="upload" />

    <section class="panel col" style="gap: 14px" aria-labelledby="decor-t">
      <div class="row between">
        <h2 id="decor-t" class="h2">Le décor de vos photos</h2>
        <span class="muted small">Choisissez votre showroom ou un décor proposé par Mending</span>
      </div>
      <div role="radiogroup" aria-labelledby="decor-t" class="grid">
        <div class="opt" :class="{ on: data.selected === 'own' }">
          <button type="button" role="radio" :aria-checked="data.selected === 'own'" class="pick" :disabled="uploading || saving !== null" @click="choose('own')">
            <img v-if="data.own.image_url" :src="data.own.image_url" alt="" />
            <span v-else class="empty">{{ uploading ? 'Envoi…' : '+ Ajouter la photo de votre showroom' }}</span>
          </button>
          <div class="meta">
            <span class="name">Mon showroom</span>
            <span v-if="data.selected === 'own'" class="tag">Utilisé</span>
            <button v-if="data.own.image_url" type="button" class="btn-link small" :disabled="uploading" @click="fileInput?.click()">Changer la photo</button>
          </div>
        </div>
        <div v-for="b in data.library" :key="b.id" class="opt" :class="{ on: data.selected === `lib:${b.id}` }">
          <button type="button" role="radio" :aria-checked="data.selected === `lib:${b.id}`" class="pick" :disabled="saving !== null" @click="choose(`lib:${b.id}`)">
            <img v-if="b.image_url" :src="b.image_url" alt="" />
          </button>
          <div class="meta">
            <span class="name">{{ b.name }}</span>
            <span v-if="data.selected === `lib:${b.id}`" class="tag">Utilisé</span>
            <span v-else-if="b.is_default && !data.chosen" class="muted small">Par défaut</span>
          </div>
        </div>
      </div>
      <p v-if="!data.library.length && !data.own.image_url" class="muted" style="margin: 0">Ajoutez la photo de votre showroom vide, prise à hauteur de poitrine, téléphone à l'horizontale.</p>
    </section>

    <section class="panel col" style="gap: 14px" aria-labelledby="test-t">
      <h2 id="test-t" class="h2">Faire un essai</h2>
      <BackdropTest :choices="choices" :initial="data.selected === 'none' ? 'own' : data.selected" />
    </section>
  </template>
</template>

<style scoped>
.panel {
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 1px 0 rgba(0, 0, 0, 0.07), 0 0 0 1px rgba(0, 0, 0, 0.05);
  padding: 16px 18px;
  max-width: 1200px;
}
.h2 {
  font-size: 15px;
  font-weight: 600;
}
.small {
  font-size: 12px;
}
.grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: 12px;
}
.opt {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.pick {
  aspect-ratio: 16 / 10;
  border-radius: 10px;
  border: 0;
  padding: 0;
  overflow: hidden;
  background: #f1f1f1;
  cursor: pointer;
  outline: 2px solid transparent;
  outline-offset: 2px;
}
.opt.on .pick {
  outline-color: var(--ink);
}
.pick img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}
.empty {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 100%;
  border: 1.5px dashed var(--accent-line);
  border-radius: 10px;
  color: var(--accent-ink);
  font-size: 14px;
  font-weight: 550;
  padding: 12px;
  text-align: center;
}
.meta {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}
.name {
  font-size: 14px;
  font-weight: 550;
}
.tag {
  font-size: 12px;
  font-weight: 550;
  color: var(--green-ink);
  background: var(--green-tint);
  border-radius: 8px;
  padding: 1px 8px;
}
.meta .btn-link {
  margin-left: auto;
  min-height: 0;
}
</style>
