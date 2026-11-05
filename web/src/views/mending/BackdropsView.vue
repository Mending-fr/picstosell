<script setup lang="ts">
// Espace Mending : décors proposés à tous les garages, celui par défaut, et essai sur les photos d'un garage.
import { computed, onMounted, reactive, ref } from 'vue'
import { ApiError, del, get, patch, post, errorMessage } from '../../api'
import { session, toast } from '../../session'
import AppModal from '../../components/AppModal.vue'
import BackdropTest, { type DecorChoice } from '../../components/BackdropTest.vue'
import type { Backdrop } from '../../types'

const list = ref<Backdrop[] | null>(null)
const error = ref('')
const busy = ref<number | null>(null)

async function load() {
  error.value = ''
  try {
    list.value = (await get<{ data: Backdrop[] }>('/admin/backdrops')).data
  } catch (e) {
    error.value = errorMessage(e)
  }
}
onMounted(load)

// ---------- ajout ----------
const addOpen = ref(false)
const form = reactive({ name: '', file: null as File | null, preview: '' })
const addErrors = ref<Record<string, string>>({})
const adding = ref(false)
function pickFile(e: Event) {
  const f = (e.target as HTMLInputElement).files?.[0] ?? null
  if (form.preview) URL.revokeObjectURL(form.preview)
  form.file = f
  form.preview = f ? URL.createObjectURL(f) : ''
}
function closeAdd() {
  addOpen.value = false
  if (form.preview) URL.revokeObjectURL(form.preview)
  Object.assign(form, { name: '', file: null, preview: '' })
  addErrors.value = {}
}
async function add() {
  addErrors.value = {}
  if (!form.name.trim()) addErrors.value.name = 'Donnez un nom au décor.'
  if (!form.file) addErrors.value.file = 'Choisissez une photo.'
  if (Object.keys(addErrors.value).length) return
  adding.value = true
  const fd = new FormData()
  fd.append('name', form.name.trim())
  fd.append('file', form.file!)
  try {
    const b = await post<Backdrop>('/admin/backdrops', fd)
    toast(`Décor « ${b.name} » ajouté.`)
    closeAdd()
    load()
  } catch (e) {
    if (e instanceof ApiError && e.status === 422) for (const [k, m] of Object.entries(e.errors)) addErrors.value[k] = m[0]
    else toast(errorMessage(e), 'error')
  } finally {
    adding.value = false
  }
}

// ---------- modifications ----------
async function rename(b: Backdrop, name: string) {
  const n = name.trim()
  if (!n || n === b.name) return
  busy.value = b.id
  try {
    Object.assign(b, await patch<Backdrop>(`/admin/backdrops/${b.id}`, { name: n }))
  } catch (e) {
    toast(errorMessage(e), 'error')
  } finally {
    busy.value = null
  }
}
async function makeDefault(b: Backdrop) {
  busy.value = b.id
  try {
    await patch(`/admin/backdrops/${b.id}`, { is_default: true })
    toast(`« ${b.name} » est le décor par défaut des garages sans showroom.`)
    load()
  } catch (e) {
    toast(errorMessage(e), 'error')
  } finally {
    busy.value = null
  }
}
async function remove(b: Backdrop) {
  if (!window.confirm(`Supprimer le décor « ${b.name} » ? Les garages qui l'utilisent repasseront sur leur showroom ou le décor par défaut.`)) return
  busy.value = b.id
  try {
    await del(`/admin/backdrops/${b.id}`)
    load()
  } catch (e) {
    toast(errorMessage(e), 'error')
  } finally {
    busy.value = null
  }
}

// ---------- essai ----------
const testGarage = ref<number | null>(session.garages[0]?.id ?? null)
const choices = computed<DecorChoice[]>(() => (list.value ?? []).map((b) => ({ key: `lib:${b.id}`, label: b.name, image_url: b.image_url })))
const initial = computed(() => choices.value.find((c) => list.value?.find((b) => `lib:${b.id}` === c.key)?.is_default)?.key ?? choices.value[0]?.key ?? '')
</script>

<template>
  <div class="page-head">
    <div class="col" style="gap: 4px">
      <h1>Décors</h1>
      <span class="muted" style="font-size: 14px">Proposés à tous les garages. Celui par défaut sert aux garages qui n'ont pas de photo de leur showroom.</span>
    </div>
    <button type="button" class="btn btn-primary" @click="addOpen = true">Ajouter un décor</button>
  </div>

  <p v-if="error" class="alert alert-error" role="alert">{{ error }} <button type="button" class="btn-link" @click="load">Réessayer</button></p>
  <div v-if="!list && !error" class="skel" style="height: 260px; border-radius: 12px" />

  <div v-else-if="list && !list.length" class="panel empty">
    <strong>Aucun décor pour l'instant</strong>
    <span>Ajoutez un premier décor : il deviendra celui par défaut.</span>
    <button type="button" class="btn btn-primary" @click="addOpen = true">Ajouter un décor</button>
  </div>

  <div v-else-if="list" class="grid">
    <div v-for="b in list" :key="b.id" class="panel item">
      <img v-if="b.image_url" :src="b.image_url" :alt="b.name" />
      <label class="sr-only" :for="`bn-${b.id}`">Nom du décor</label>
      <input :id="`bn-${b.id}`" class="f f-sm name" :value="b.name" :disabled="busy === b.id" @change="rename(b, ($event.target as HTMLInputElement).value)" @keydown.enter="($event.target as HTMLInputElement).blur()" />
      <div class="row between" style="flex-wrap: nowrap">
        <span v-if="b.is_default" class="tag">Par défaut</span>
        <button v-else type="button" class="btn-link small" :disabled="busy === b.id" @click="makeDefault(b)">Mettre par défaut</button>
        <button type="button" class="btn-link small danger" :disabled="busy === b.id" @click="remove(b)">Supprimer</button>
      </div>
    </div>
  </div>

  <section v-if="list && list.length" class="panel col" style="gap: 14px" aria-labelledby="test-t">
    <div class="row between">
      <h2 id="test-t" class="h2">Faire un essai</h2>
      <label class="row" style="gap: 8px; font-size: 13px">
        Sur les photos de
        <select v-model="testGarage" class="f f-sm" style="width: auto">
          <option v-for="g in session.garages" :key="g.id" :value="g.id">{{ g.name }}</option>
        </select>
      </label>
    </div>
    <BackdropTest v-if="testGarage" :key="testGarage" :choices="choices" :initial="initial" :garage-id="testGarage" />
    <p v-else class="muted" style="margin: 0">Aucun garage pour l'instant.</p>
  </section>

  <AppModal v-if="addOpen" title="Ajouter un décor" @close="closeAdd">
    <form class="col" style="gap: 14px" novalidate @submit.prevent="add">
      <label class="lbl">Nom<input v-model="form.name" class="f" placeholder="Ex. Studio clair" :aria-invalid="!!addErrors.name" /><span v-if="addErrors.name" class="err">{{ addErrors.name }}</span></label>
      <label class="lbl">
        Photo du décor
        <input type="file" accept="image/*" @change="pickFile" />
        <span v-if="addErrors.file" class="err">{{ addErrors.file }}</span>
        <span class="muted" style="font-weight: 400; font-size: 12px">Un lieu vide, pris à hauteur de poitrine, à l'horizontale, avec le sol visible.</span>
      </label>
      <img v-if="form.preview" :src="form.preview" alt="Aperçu" style="width: 100%; border-radius: 8px" />
    </form>
    <template #footer>
      <button type="button" class="btn" @click="closeAdd">Annuler</button>
      <button type="button" class="btn btn-primary" :disabled="adding" @click="add"><span v-if="adding" class="spinner" aria-hidden="true" />Ajouter</button>
    </template>
  </AppModal>
</template>

<style scoped>
.panel {
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 1px 0 rgba(0, 0, 0, 0.07), 0 0 0 1px rgba(0, 0, 0, 0.05);
  padding: 16px 18px;
}
.grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
  gap: 14px;
}
.item {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 12px;
}
.item img {
  width: 100%;
  aspect-ratio: 16 / 10;
  object-fit: cover;
  border-radius: 8px;
  display: block;
}
.name {
  font-weight: 550;
}
.tag {
  font-size: 12px;
  font-weight: 550;
  color: var(--green-ink);
  background: var(--green-tint);
  border-radius: 8px;
  padding: 2px 8px;
}
.small {
  font-size: 13px;
  min-height: 0;
}
.danger {
  color: var(--red-ink);
}
</style>
