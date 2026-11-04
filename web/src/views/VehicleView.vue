<script setup lang="ts">
import { onThousandsInput } from '../inputs'
import PriceHelper from '../components/PriceHelper.vue'
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { onBeforeRouteLeave, useRoute, useRouter } from 'vue-router'
import { ApiError, del, download, get, patch, post, errorMessage } from '../api'
import { FUELS, GEARBOXES, RENDER_STATE, STATUS, copyText, listingText, parseNum, num, shotLabel, vehicleTitle, euros } from '../labels'
import { isManager, toast, refreshSidebar } from '../session'
import StatusPill from '../components/StatusPill.vue'
import PhotoPicker, { type PickedPhoto } from '../components/PhotoPicker.vue'
import EquipmentEditor from '../components/EquipmentEditor.vue'
import { uploadPhotos } from '../uploads'
import type { EquipmentGroup, Photo, Render, Vehicle } from '../types'

const route = useRoute()
const router = useRouter()
const id = Number(route.params.id)

const v = ref<Vehicle | null>(null)
const loadError = ref('')
const notFound = ref(false)
const queue = ref<number[]>([])

// ---------- chargement ----------
async function load(silent = false) {
  try {
    const data = await get<Vehicle>(`/vehicles/${id}`)
    v.value = data
    if (!silent || !dirty.value) fillForm(data)
    if (selected.value === null || !data.photos?.some((p) => p.id === selected.value)) selectFirst()
  } catch (e) {
    if (e instanceof ApiError && e.status === 404) notFound.value = true
    else if (!silent) loadError.value = errorMessage(e)
  }
}

async function loadQueue() {
  if (!isManager.value) return
  try {
    const r = await get<{ data: Vehicle[] }>('/vehicles?status=to_review')
    queue.value = r.data.map((x) => x.id).reverse()
  } catch {
    /* facultatif */
  }
}

// ---------- photos et rendus ----------
const photos = computed<Photo[]>(() => {
  const list = [...(v.value?.photos ?? [])]
  const rank = (p: Photo) => (p.kind === 'exterior' ? 0 : 1)
  return list.sort((a, b) => rank(a) - rank(b) || a.position - b.position || a.id - b.id)
})
const renderOf = computed(() => {
  const m = new Map<number, Render>()
  for (const r of v.value?.renders ?? []) m.set(r.photo_id, r)
  return m
})
const selected = ref<number | null>(null)
const cur = computed(() => photos.value.find((p) => p.id === selected.value) ?? null)
const curRender = computed(() => (cur.value ? renderOf.value.get(cur.value.id) ?? null : null))

function stateOf(p: Photo) {
  if (p.kind !== 'exterior') return RENDER_STATE.asis
  const r = renderOf.value.get(p.id)
  return RENDER_STATE[r ? r.status : 'none']
}

function selectFirst() {
  const firstReview = photos.value.find((p) => renderOf.value.get(p.id)?.status === 'to_review')
  selected.value = (firstReview ?? photos.value[0])?.id ?? null
}

const exterior = computed(() => photos.value.filter((p) => p.kind === 'exterior'))
const needRender = computed(() =>
  exterior.value.filter((p) => {
    const r = renderOf.value.get(p.id)
    return !r || r.status === 'failed'
  }),
)
const pending = computed(() => (v.value?.renders ?? []).some((r) => r.status === 'pending' || r.status === 'processing'))
const toReview = computed(() => exterior.value.filter((p) => renderOf.value.get(p.id)?.status === 'to_review').length)

// rafraîchissement automatique pendant la préparation
let timer: ReturnType<typeof setInterval> | undefined
watch(
  pending,
  (p) => {
    clearInterval(timer)
    if (p) timer = setInterval(() => load(true), 3000)
  },
  { immediate: true },
)

const viewMode = ref<'side' | 'overlay'>('side')
const split = ref(50)
const acting = ref(false)

function replaceRender(r: Render) {
  if (!v.value) return
  const list = (v.value.renders ?? []).filter((x) => x.photo_id !== r.photo_id)
  v.value.renders = [...list, r]
}

function nextToReview() {
  const list = photos.value
  const start = list.findIndex((p) => p.id === selected.value)
  for (let i = 1; i <= list.length; i++) {
    const p = list[(start + i) % list.length]
    if (renderOf.value.get(p.id)?.status === 'to_review') {
      selected.value = p.id
      return
    }
  }
}

async function act(kind: 'approve' | 'retry' | 'keep') {
  const r = curRender.value
  if (!r || acting.value) return
  if (kind === 'approve' && !r.url) return
  acting.value = true
  const before = { ...r }
  // mise à jour immédiate, corrigée par la réponse du serveur
  if (kind === 'approve') replaceRender({ ...r, status: 'approved' })
  else if (kind === 'keep') replaceRender({ ...r, status: 'kept_original' })
  else replaceRender({ ...r, status: 'pending', url: null })
  if (kind !== 'retry') nextToReview()
  try {
    const path = kind === 'approve' ? 'approve' : kind === 'keep' ? 'keep-original' : 'retry'
    const res = await post<Render>(`/renders/${r.id}/${path}`)
    replaceRender(res)
    if (kind === 'retry') toast('On refait cette photo. Elle revient dans quelques instants.')
    load(true)
  } catch (e) {
    replaceRender(before)
    toast(errorMessage(e), 'error')
  } finally {
    acting.value = false
  }
}

const launching = ref(false)
async function launchRenders() {
  launching.value = true
  try {
    const r = await post<{ data: Render[] }>(`/vehicles/${id}/renders`)
    r.data.forEach(replaceRender)
    toast(r.data.length ? 'Préparation des photos showroom lancée.' : 'Toutes les photos sont déjà préparées.')
    await load(true)
  } catch (e) {
    toast(errorMessage(e), 'error')
  } finally {
    launching.value = false
  }
}

async function removePhoto(p: Photo) {
  if (!window.confirm(`Supprimer la photo « ${shotLabel(p.shot_key)} » ?`)) return
  const old = v.value?.photos
  if (v.value) v.value.photos = (v.value.photos ?? []).filter((x) => x.id !== p.id)
  selectFirst()
  try {
    await del(`/photos/${p.id}`)
    load(true)
  } catch (e) {
    if (v.value && old) v.value.photos = old
    toast(errorMessage(e), 'error')
  }
}

// ---------- ajout de photos ----------
const adding = ref(false)
const newPhotos = ref<PickedPhoto[]>([])
const sending = ref(false)
const takenKeys = computed(() => exterior.value.map((p) => p.shot_key ?? '').filter(Boolean))
async function sendPhotos() {
  if (!newPhotos.value.length) return
  sending.value = true
  const sent = await uploadPhotos(id, newPhotos.value)
  const hasExt = newPhotos.value.some((p) => p.state === 'done' && p.slot !== 'interior' && p.slot !== 'detail')
  if (hasExt) {
    try {
      await post(`/vehicles/${id}/renders`)
    } catch (e) {
      toast(errorMessage(e), 'error')
    }
  }
  sending.value = false
  if (sent === newPhotos.value.length) {
    toast(`${sent} photo${sent > 1 ? 's' : ''} ajoutée${sent > 1 ? 's' : ''}.`)
    newPhotos.value = []
    adding.value = false
  }
  await load(true)
  refreshSidebar()
}

// ---------- annonce ----------
const form = reactive({
  price: '',
  mileage: '',
  gearbox: '',
  fuel: '',
  first_registration: '',
  power_hp: '',
  fiscal_hp: '',
  make: '',
  model: '',
  version: '',
  color: '',
  doors: '',
  seats: '',
  description: '',
  history_url: '',
})
const priceCar = computed(() => ({
  plate: v.value?.plate ?? null,
  make: form.make,
  model: form.model,
  version: form.version || null,
  first_registration: form.first_registration || null,
  mileage: parseNum(form.mileage),
  fuel: form.fuel || null,
  gearbox: form.gearbox || null,
  power_hp: parseNum(form.power_hp),
  color: form.color || null,
}))
const equipment = ref<EquipmentGroup[]>([])
const original = ref('')
const fieldErrors = ref<Record<string, string>>({})

function snapshot() {
  return JSON.stringify({ ...form, equipment: equipment.value })
}
function fillForm(d: Vehicle) {
  form.price = d.price != null ? num(d.price) : ''
  form.mileage = d.mileage != null ? num(d.mileage) : ''
  form.gearbox = d.gearbox ?? ''
  form.fuel = d.fuel ?? ''
  form.first_registration = d.first_registration ? d.first_registration.slice(0, 7) : ''
  form.power_hp = d.power_hp != null ? String(d.power_hp) : ''
  form.fiscal_hp = d.fiscal_hp != null ? String(d.fiscal_hp) : ''
  form.make = d.make ?? ''
  form.model = d.model ?? ''
  form.version = d.version ?? ''
  form.color = d.color ?? ''
  form.doors = d.doors != null ? String(d.doors) : ''
  form.seats = d.seats != null ? String(d.seats) : ''
  form.description = d.description ?? ''
  form.history_url = d.history_url ?? ''
  equipment.value = JSON.parse(JSON.stringify(d.equipment ?? []))
  original.value = snapshot()
}
const dirty = computed(() => original.value !== '' && snapshot() !== original.value)

const saving = ref(false)
async function saveListing(quiet = false): Promise<boolean> {
  fieldErrors.value = {}
  if (form.history_url.trim() && !/^https?:\/\//i.test(form.history_url.trim())) {
    fieldErrors.value.history_url = 'Collez un lien complet qui commence par https://'
    return false
  }
  saving.value = true
  try {
    const d = await patch<Vehicle>(`/vehicles/${id}`, {
      price: parseNum(form.price),
      mileage: parseNum(form.mileage),
      gearbox: form.gearbox || null,
      fuel: form.fuel || null,
      first_registration: form.first_registration ? `${form.first_registration}-01` : null,
      power_hp: parseNum(form.power_hp),
      fiscal_hp: parseNum(form.fiscal_hp),
      make: form.make.trim() || null,
      model: form.model.trim() || null,
      version: form.version.trim() || null,
      color: form.color.trim() || null,
      doors: parseNum(form.doors),
      seats: parseNum(form.seats),
      description: form.description.trim() || null,
      history_url: form.history_url.trim() || null,
      equipment: equipment.value.filter((g) => g.group.trim()).map((g) => ({ group: g.group.trim(), items: g.items })),
    })
    v.value = { ...d, photos: v.value?.photos ?? d.photos, renders: d.renders ?? v.value?.renders }
    fillForm(d)
    if (!quiet) toast('Annonce enregistrée.')
    return true
  } catch (e) {
    if (e instanceof ApiError && e.status === 422) {
      for (const [k, m] of Object.entries(e.errors)) fieldErrors.value[k] = m[0]
      toast('Certaines informations sont à corriger.', 'error')
    } else toast(errorMessage(e), 'error')
    return false
  } finally {
    saving.value = false
  }
}

const rewriting = ref(false)
async function rewrite() {
  rewriting.value = true
  try {
    const r = await post<{ description: string }>(`/vehicles/${id}/description`)
    form.description = r.description
    toast('Nouveau texte proposé. Relisez-le puis enregistrez.')
  } catch (e) {
    toast(errorMessage(e), 'error')
  } finally {
    rewriting.value = false
  }
}
const showEquipment = ref(false)
const equipmentCount = computed(() => equipment.value.reduce((n, g) => n + g.items.length, 0))
const editHistory = ref(false)

// ---------- mise en ligne ----------
const publishing = ref(false)
const publishError = ref('')
const isOnline = computed(() => v.value?.status === 'online')
const isSold = computed(() => v.value?.status === 'sold')

async function publishAll() {
  if (!v.value || publishing.value || !isManager.value) return
  publishError.value = ''
  if (!parseNum(form.price)) {
    publishError.value = 'Indiquez un prix avant de mettre en ligne.'
    fieldErrors.value.price = 'Prix obligatoire'
    document.getElementById('v-price')?.focus()
    return
  }
  publishing.value = true
  try {
    if (dirty.value && !(await saveListing(true))) return
    if (toReview.value > 0) {
      const d = await post<Vehicle>(`/vehicles/${id}/approve-all`)
      v.value = { ...v.value, ...d }
    }
    const d = await post<Vehicle>(`/vehicles/${id}/publish`, { published: true })
    v.value = { ...v.value, ...d }
    toast('En ligne sur votre site.')
    refreshSidebar()
    queue.value = queue.value.filter((x) => x !== id)
  } catch (e) {
    publishError.value = errorMessage(e)
  } finally {
    publishing.value = false
  }
}

async function unpublish() {
  publishing.value = true
  try {
    const d = await post<Vehicle>(`/vehicles/${id}/publish`, { published: false })
    v.value = { ...v.value!, ...d }
    toast("L'annonce est retirée de votre site.")
  } catch (e) {
    toast(errorMessage(e), 'error')
  } finally {
    publishing.value = false
  }
}

const confirmSold = ref(false)
async function markSold() {
  if (!confirmSold.value) {
    confirmSold.value = true
    setTimeout(() => (confirmSold.value = false), 5000)
    return
  }
  confirmSold.value = false
  publishing.value = true
  try {
    const d = await post<Vehicle>(`/vehicles/${id}/sold`)
    v.value = { ...v.value!, ...d }
    toast("Vendue. L'annonce est retirée de votre site.")
    refreshSidebar()
  } catch (e) {
    toast(errorMessage(e), 'error')
  } finally {
    publishing.value = false
  }
}

const exporting = ref(false)
async function exportZip() {
  if (!v.value) return
  exporting.value = true
  try {
    await download(`/vehicles/${id}/export`, `${v.value.plate}.zip`)
  } catch (e) {
    toast(errorMessage(e), 'error')
  } finally {
    exporting.value = false
  }
}
async function copyListing() {
  if (!v.value) return
  const ok = await copyText(listingText({ ...v.value, description: form.description, equipment: equipment.value }))
  toast(ok ? "Texte de l'annonce copié. Collez-le sur Leboncoin ou La Centrale." : 'La copie a échoué.', ok ? 'ok' : 'error')
}

const queuePos = computed(() => queue.value.indexOf(id))
const nextVehicle = computed(() => {
  const others = queue.value.filter((x) => x !== id)
  if (!others.length) return null
  const after = queue.value.slice(queuePos.value + 1).find((x) => x !== id)
  return after ?? others[0]
})

// ---------- clavier ----------
function onKey(e: KeyboardEvent) {
  const t = e.target as HTMLElement
  const typing = t && (t.tagName === 'INPUT' || t.tagName === 'TEXTAREA' || t.tagName === 'SELECT' || t.isContentEditable)
  if ((e.metaKey || e.ctrlKey) && e.key === 'Enter') {
    e.preventDefault()
    if (!isOnline.value && !isSold.value) publishAll()
    return
  }
  if (typing || e.metaKey || e.ctrlKey || e.altKey || !isManager.value) return
  const k = e.key.toLowerCase()
  const r = curRender.value
  if (k === 'arrowright' || k === 'arrowleft') {
    const list = photos.value
    const i = list.findIndex((p) => p.id === selected.value)
    const n = list[(i + (k === 'arrowright' ? 1 : list.length - 1)) % list.length]
    if (n) selected.value = n.id
    return
  }
  if (!r) return
  if (k === 'a' && r.status === 'to_review') act('approve')
  else if (k === 'r' && ['to_review', 'approved', 'failed', 'kept_original'].includes(r.status)) act('retry')
  else if (k === 'o' && ['to_review', 'failed', 'approved'].includes(r.status)) act('keep')
}

onMounted(() => {
  load()
  loadQueue()
  document.addEventListener('keydown', onKey)
})
onBeforeUnmount(() => {
  clearInterval(timer)
  document.removeEventListener('keydown', onKey)
})
onBeforeRouteLeave(() => {
  if (dirty.value && !window.confirm("Vos modifications de l'annonce ne sont pas enregistrées. Quitter quand même ?")) return false
  return true
})
</script>

<template>
  <div class="page">
    <div v-if="notFound" class="empty" style="min-height: 60vh; justify-content: center">
      <strong>Cette voiture n'existe pas ou a été supprimée</strong>
      <RouterLink to="/vehicles" class="btn btn-primary">Retour au stock</RouterLink>
    </div>
    <div v-else-if="loadError" class="empty" style="min-height: 60vh; justify-content: center">
      <strong>Impossible d'afficher cette voiture</strong>
      <span>{{ loadError }}</span>
      <button type="button" class="btn btn-primary" @click="(loadError = ''), load()">Réessayer</button>
    </div>

    <template v-else>
      <!-- en-tête -->
      <div class="page-head">
        <div class="row" style="gap: 14px">
          <button type="button" class="icon-btn" aria-label="Retour" @click="router.back()">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#3B4150" stroke-width="2" aria-hidden="true"><path d="M15 6l-6 6 6 6" /></svg>
          </button>
          <template v-if="v">
            <span class="plate plate-lg">{{ v.plate }}</span>
            <h1 class="title">{{ vehicleTitle(v) }}</h1>
            <span v-if="queuePos >= 0 && v.status === 'to_review'" class="pill" style="color: #A55A0B; background: #FCF1E3">
              Voiture {{ queuePos + 1 }} sur {{ queue.length }} à vérifier
            </span>
            <StatusPill v-else :status="v.status" />
          </template>
          <template v-else>
            <span class="skel" style="width: 110px; height: 30px" />
            <span class="skel" style="width: 280px; height: 28px" />
          </template>
        </div>
        <div v-if="v && isManager" class="row">
          <template v-if="dirty">
            <span class="unsaved">Modifications non enregistrées</span>
            <button type="button" class="btn btn-primary" :disabled="saving" @click="saveListing()">
              <span v-if="saving" class="spinner" aria-hidden="true" />Enregistrer
            </button>
          </template>
          <button type="button" class="btn" :disabled="exporting" @click="exportZip">
            <span v-if="exporting" class="spinner" aria-hidden="true" />Télécharger les photos
          </button>
          <button v-if="!isOnline && !isSold" type="button" class="btn btn-green" :disabled="publishing" @click="publishAll">
            <span v-if="publishing" class="spinner" aria-hidden="true" />
            Tout est bon, mettre en ligne <span class="kbd-inv" aria-hidden="true">⌘ ↵</span>
          </button>
          <RouterLink v-else-if="nextVehicle" :to="`/vehicles/${nextVehicle}`" class="btn btn-primary">Voiture suivante à vérifier</RouterLink>
        </div>
      </div>
      <p v-if="publishError" class="alert alert-error" role="alert" style="margin: 0">{{ publishError }}</p>

      <div class="layout">
        <!-- photos -->
        <section aria-label="Photos de la voiture" class="photos">
          <template v-if="!v">
            <div class="skel" style="aspect-ratio: 16/7; border-radius: 14px" />
            <div class="strip"><div v-for="i in 6" :key="i" class="skel" style="aspect-ratio: 4/3.6" /></div>
          </template>

          <template v-else-if="!photos.length">
            <div class="card">
              <div class="col" style="gap: 4px">
                <h2 style="font-size: 18px">Pas encore de photos</h2>
                <p class="muted" style="margin: 0">
                  Prenez les photos avec l'appli sur le parking, ou importez-les ici depuis l'ordinateur. On les place ensuite dans votre showroom.
                </p>
              </div>
              <PhotoPicker v-model="newPhotos" :disabled="sending" />
              <div class="row" style="justify-content: flex-end">
                <button type="button" class="btn btn-primary" :disabled="!newPhotos.length || sending" @click="sendPhotos">
                  <span v-if="sending" class="spinner" aria-hidden="true" />
                  Envoyer et préparer les photos
                </button>
              </div>
            </div>
          </template>

          <template v-else>
            <div v-if="needRender.length && !pending" class="alert alert-info row between">
              <span>{{ needRender.length }} photo{{ needRender.length > 1 ? 's' : '' }} du parking {{ needRender.length > 1 ? "n'ont" : "n'a" }} pas encore de version showroom.</span>
              <button type="button" class="btn btn-primary btn-sm" :disabled="launching" @click="launchRenders">
                <span v-if="launching" class="spinner" aria-hidden="true" />Préparer les photos showroom
              </button>
            </div>
            <div v-if="pending" class="alert alert-info" role="status">
              <span class="spinner" aria-hidden="true" style="vertical-align: -3px; margin-right: 8px" />Photos en préparation, la page se met à jour toute seule.
            </div>

            <div v-if="cur" class="card viewer">
              <div class="row between">
                <span style="font-size: 16px; font-weight: 500">{{ cur.kind === 'exterior' ? `Vue ${shotLabel(cur.shot_key).toLowerCase()}` : cur.kind === 'interior' ? 'Intérieur' : 'Détail' }}</span>
                <div v-if="curRender?.url" role="group" aria-label="Affichage" class="seg">
                  <button type="button" :aria-pressed="viewMode === 'side'" @click="viewMode = 'side'">Côte à côte</button>
                  <button type="button" :aria-pressed="viewMode === 'overlay'" @click="viewMode = 'overlay'">Superposé</button>
                </div>
              </div>

              <!-- photo telle quelle -->
              <figure v-if="cur.kind !== 'exterior'" class="fig">
                <img :src="cur.url" :alt="cur.kind === 'interior' ? 'Intérieur' : 'Détail'" class="img" />
                <figcaption>Cette photo est publiée telle quelle.</figcaption>
              </figure>

              <!-- superposé -->
              <div v-else-if="viewMode === 'overlay' && curRender?.url" class="col" style="gap: 8px">
                <div class="ov">
                  <img :src="cur.url" alt="Avant : sur le parking" class="img" />
                  <img :src="curRender.url" alt="Après : dans votre showroom" class="img top" :style="{ clipPath: `inset(0 0 0 ${split}%)` }" />
                  <span class="ov-line" :style="{ left: split + '%' }" aria-hidden="true" />
                  <span class="tag l">Parking</span><span class="tag r">Showroom</span>
                </div>
                <label class="lbl"><span class="sr-only">Glisser pour comparer avant et après</span><input v-model.number="split" type="range" min="0" max="100" /></label>
              </div>

              <!-- côte à côte -->
              <div v-else class="pair">
                <figure class="fig">
                  <img :src="cur.url" alt="Avant : sur le parking" class="img" />
                  <figcaption>Avant : sur le parking</figcaption>
                </figure>
                <figure class="fig">
                  <img v-if="curRender?.url && curRender.status !== 'pending' && curRender.status !== 'processing'" :src="curRender.url" alt="Après : dans votre showroom" class="img" />
                  <div v-else-if="curRender && (curRender.status === 'pending' || curRender.status === 'processing')" class="img wait">
                    <span class="spinner" aria-hidden="true" /> En préparation…
                  </div>
                  <div v-else-if="curRender?.status === 'failed'" class="img wait ko">
                    La préparation a échoué{{ curRender.error ? ` : ${curRender.error}` : '' }}.
                  </div>
                  <div v-else class="img wait">Pas encore préparée</div>
                  <figcaption>Après : dans votre showroom. Seul le décor change, la carrosserie reste celle de la photo.</figcaption>
                </figure>
              </div>

              <div v-if="isManager && curRender" class="row">
                <button type="button" class="btn btn-primary" :disabled="acting || curRender.status !== 'to_review' || !curRender.url" @click="act('approve')">
                  C'est bon <span class="kbd-inv" aria-hidden="true">A</span>
                </button>
                <button type="button" class="btn" :disabled="acting || curRender.status === 'pending' || curRender.status === 'processing'" @click="act('retry')">
                  Refaire <span class="kbd" aria-hidden="true">R</span>
                </button>
                <button type="button" class="btn" :disabled="acting || curRender.status === 'kept_original' || curRender.status === 'pending' || curRender.status === 'processing'" @click="act('keep')">
                  Garder la photo du parking <span class="kbd" aria-hidden="true">O</span>
                </button>
                <span class="muted" style="font-size: 13px; margin-left: auto" :style="{ color: stateOf(cur).color }">{{ stateOf(cur).label }}</span>
              </div>
              <div v-else-if="isManager && cur.kind === 'exterior' && !curRender" class="row">
                <button type="button" class="btn btn-primary" :disabled="launching" @click="launchRenders">Préparer les photos showroom</button>
              </div>
            </div>

            <div class="strip" role="listbox" aria-label="Toutes les photos">
              <button
                v-for="p in photos"
                :key="p.id"
                type="button"
                role="option"
                class="th"
                :aria-selected="p.id === selected"
                @click="selected = p.id"
              >
                <img :src="renderOf.get(p.id)?.status === 'approved' || renderOf.get(p.id)?.status === 'to_review' ? renderOf.get(p.id)?.url ?? p.thumb_url : p.thumb_url" alt="" loading="lazy" />
                <span class="th-l">{{ p.kind === 'exterior' ? shotLabel(p.shot_key) : p.kind === 'interior' ? 'Intérieur' : 'Détail' }}</span>
                <span class="th-s" :style="{ color: stateOf(p).color }">{{ stateOf(p).label }}</span>
              </button>
            </div>

            <div class="row">
              <button v-if="!adding" type="button" class="btn btn-sm" @click="adding = true">Ajouter ou remplacer des photos</button>
              <button v-if="isManager && cur" type="button" class="btn btn-sm btn-danger" @click="removePhoto(cur)">Supprimer cette photo</button>
              <span class="muted" style="font-size: 13px">Astuce : flèches gauche et droite pour passer d'une photo à l'autre.</span>
            </div>
            <div v-if="adding" class="card">
              <PhotoPicker v-model="newPhotos" :taken="takenKeys" :disabled="sending" />
              <div class="row" style="justify-content: flex-end">
                <button type="button" class="btn" :disabled="sending" @click="(adding = false), (newPhotos = [])">Annuler</button>
                <button type="button" class="btn btn-primary" :disabled="!newPhotos.length || sending" @click="sendPhotos">
                  <span v-if="sending" class="spinner" aria-hidden="true" />Envoyer les photos
                </button>
              </div>
            </div>
          </template>
        </section>

        <!-- la voiture -->
        <section v-if="isManager" aria-label="La voiture" class="card side">
          <div class="row between">
            <h2 class="side-t">La voiture</h2>
            <span v-if="v" class="muted" style="font-size: 13px">{{ STATUS[v.status].label }}</span>
          </div>
          <template v-if="v">
            <div class="two">
              <label class="lbl">Prix TTC (€)<input id="v-price" :value="form.price" class="f" inputmode="numeric" @input="form.price = onThousandsInput($event)" :aria-invalid="!!fieldErrors.price" /><span v-if="fieldErrors.price" class="err">{{ fieldErrors.price }}</span></label>
              <label class="lbl">Kilométrage<input :value="form.mileage" class="f" inputmode="numeric" @input="form.mileage = onThousandsInput($event)" :aria-invalid="!!fieldErrors.mileage" /></label>
              <label class="lbl">
                Boîte
                <select v-model="form.gearbox" class="f">
                  <option value="">Non précisée</option>
                  <option v-for="o in GEARBOXES" :key="o.value" :value="o.value">{{ o.label }}</option>
                </select>
              </label>
              <label class="lbl">
                Énergie
                <select v-model="form.fuel" class="f">
                  <option value="">Non précisée</option>
                  <option v-for="o in FUELS" :key="o.value" :value="o.value">{{ o.label }}</option>
                </select>
              </label>
              <label class="lbl">1re immatriculation<input v-model="form.first_registration" class="f" type="month" /></label>
              <div class="two-in">
                <label class="lbl">Puissance<input v-model="form.power_hp" class="f" inputmode="numeric" placeholder="ch" aria-label="Puissance en chevaux" /></label>
                <label class="lbl">CV<input v-model="form.fiscal_hp" class="f" inputmode="numeric" /></label>
              </div>
            </div>
            <PriceHelper :car="priceCar" @use="(p) => (form.price = num(p))" />
            <details class="more">
              <summary>Marque, modèle, couleur…</summary>
              <div class="two" style="margin-top: 12px">
                <label class="lbl">Marque<input v-model="form.make" class="f" /></label>
                <label class="lbl">Modèle<input v-model="form.model" class="f" /></label>
                <label class="lbl" style="grid-column: 1 / -1">Version<input v-model="form.version" class="f" /></label>
                <label class="lbl">Couleur<input v-model="form.color" class="f" /></label>
                <div class="two-in">
                  <label class="lbl">Portes<input v-model="form.doors" class="f" inputmode="numeric" /></label>
                  <label class="lbl">Places<input v-model="form.seats" class="f" inputmode="numeric" /></label>
                </div>
              </div>
            </details>

            <div class="hist">
              <div class="col" style="gap: 2px; min-width: 0">
                <span style="font-size: 14px; font-weight: 500">Historique</span>
                <span class="muted" style="font-size: 13px">{{ form.history_url ? 'Lien HistoVec ajouté' : 'Aucun historique pour le moment' }}</span>
              </div>
              <div class="row" style="gap: 4px">
                <a v-if="v.history_url && !editHistory" :href="v.history_url" target="_blank" rel="noopener" class="btn-link" style="display: inline-flex; align-items: center">Voir</a>
                <button type="button" class="btn-link" @click="editHistory = !editHistory">{{ editHistory ? 'Fermer' : form.history_url ? 'Changer' : 'Ajouter' }}</button>
              </div>
              <label v-if="editHistory" class="lbl" style="flex-basis: 100%">
                Lien HistoVec
                <input v-model="form.history_url" class="f" type="url" placeholder="Collez le lien ici" :aria-invalid="!!fieldErrors.history_url" />
                <span v-if="fieldErrors.history_url" class="err">{{ fieldErrors.history_url }}</span>
              </label>
            </div>
          </template>
          <template v-else>
            <div v-for="i in 5" :key="i" class="skel" style="height: 44px" />
          </template>
        </section>

        <!-- annonce -->
        <section v-if="isManager" aria-label="L'annonce" class="card side">
          <div class="row between">
            <h2 class="side-t">L'annonce</h2>
            <span class="muted" style="font-size: 12px">Texte écrit par l'IA, à relire</span>
          </div>
          <template v-if="v">
            <label class="lbl">Description<textarea v-model="form.description" class="f" rows="7" placeholder="Quelques phrases sur la voiture : entretien, propriétaires, points forts." /></label>
            <div class="row">
              <button type="button" class="btn btn-sm" :disabled="rewriting" @click="rewrite"><span v-if="rewriting" class="spinner" aria-hidden="true" />Réécrire le texte</button>
              <button type="button" class="btn btn-sm" :aria-expanded="showEquipment" @click="showEquipment = !showEquipment">Équipements ({{ equipmentCount }})</button>
            </div>
            <EquipmentEditor v-if="showEquipment" v-model="equipment" />

            <div class="where">
              <span class="muted" style="font-size: 13px; font-weight: 500">Où la mettre en ligne</span>
              <div class="where-row">
                <div class="col" style="gap: 2px">
                  <span style="font-weight: 500">Votre site</span>
                  <span class="muted" style="font-size: 13px">{{ isOnline ? 'En ligne' : isSold ? 'Vendue, retirée du site' : 'Pas encore en ligne' }}</span>
                </div>
                <button v-if="isOnline" type="button" class="btn btn-sm" :disabled="publishing" @click="unpublish">Retirer de mon site</button>
                <button v-else-if="!isSold" type="button" class="btn btn-sm btn-green" :disabled="publishing" @click="publishAll">Mettre en ligne</button>
              </div>
              <div class="where-row">
                <div class="col" style="gap: 2px">
                  <span style="font-weight: 500">Leboncoin et La Centrale</span>
                  <span class="muted" style="font-size: 13px">Photos et texte prêts à copier</span>
                </div>
                <div class="row" style="gap: 6px">
                  <button type="button" class="btn btn-sm" :disabled="exporting" @click="exportZip">Photos (ZIP)</button>
                  <button type="button" class="btn btn-sm" @click="copyListing">Copier le texte</button>
                </div>
              </div>
              <button v-if="!isSold" type="button" class="btn btn-danger" :disabled="publishing" @click="markSold">
                {{ confirmSold ? 'Confirmer : la voiture est vendue' : 'Marquer vendue' }}
              </button>
              <span v-else class="muted" style="font-size: 13px">Vendue {{ v.sold_at ? `le ${new Date(v.sold_at).toLocaleDateString('fr-FR')}` : '' }} à {{ euros(v.price) }}.</span>
            </div>
          </template>
          <template v-else>
            <div v-for="i in 4" :key="i" class="skel" style="height: 44px" />
          </template>
        </section>
      </div>
    </template>
  </div>
</template>

<style scoped>
.page {
  display: flex;
  flex-direction: column;
  gap: 18px;
}
.title {
  font-size: 22px;
}
.unsaved {
  font-size: 13px;
  font-weight: 500;
  color: var(--amber-ink);
}
/* trois colonnes : photos, la voiture, l'annonce */
.layout {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 340px 380px;
  gap: 18px;
  align-items: start;
}
.photos {
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.side {
  min-width: 0;
  padding: 18px 20px;
  gap: 14px;
}
.side-t {
  font-size: 15px;
  font-weight: 500;
}
@media (max-width: 1280px) {
  .layout {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  .photos {
    grid-column: 1 / -1;
  }
}
@media (max-width: 800px) {
  .layout {
    grid-template-columns: minmax(0, 1fr);
  }
}
.viewer {
  padding: 16px;
  gap: 14px;
}
.pair {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
}
.fig {
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.fig figcaption {
  font-size: 13px;
  color: var(--muted);
}
.img {
  width: 100%;
  aspect-ratio: 4/3;
  object-fit: cover;
  border-radius: 10px;
  background: #EEF0F3;
}
.wait {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  color: var(--accent-dark);
  background: #EEF3F9;
  font-size: 14px;
  text-align: center;
  padding: 12px;
}
.wait.ko {
  color: var(--red-ink);
  background: var(--red-soft);
}
.ov {
  position: relative;
}
.ov .top {
  position: absolute;
  inset: 0;
}
.ov-line {
  position: absolute;
  top: 0;
  bottom: 0;
  width: 2px;
  background: #fff;
  box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.2);
  transform: translateX(-1px);
}
.tag {
  position: absolute;
  top: 10px;
  font-size: 12px;
  font-weight: 500;
  background: rgba(255, 255, 255, 0.9);
  border-radius: 6px;
  padding: 3px 8px;
}
.tag.l {
  left: 10px;
}
.tag.r {
  right: 10px;
}
.strip {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(96px, 1fr));
  gap: 8px;
}
.th {
  text-align: left;
  border: 2px solid var(--line);
  border-radius: 10px;
  background: #fff;
  padding: 6px;
  display: flex;
  flex-direction: column;
  gap: 4px;
  color: var(--ink);
}
.th[aria-selected='true'] {
  border-color: var(--accent);
}
.th img {
  width: 100%;
  aspect-ratio: 4/3;
  object-fit: cover;
  border-radius: 6px;
  background: #EEF0F3;
}
.th-l {
  font-size: 12px;
  font-weight: 500;
}
.th-s {
  font-size: 12px;
}
.two {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
}
.two-in {
  display: grid;
  grid-template-columns: 1.4fr 1fr;
  gap: 8px;
}
.more summary {
  cursor: pointer;
  font-size: 14px;
  font-weight: 500;
  color: var(--accent);
  min-height: 32px;
  display: flex;
  align-items: center;
}
.hist {
  display: flex;
  justify-content: space-between;
  align-items: center;
  border: 1px solid var(--line);
  border-radius: 10px;
  padding: 10px 12px;
  gap: 10px;
  flex-wrap: wrap;
}
.where {
  border-top: 1px solid var(--line-2);
  padding-top: 14px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.where-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}
@media (max-width: 860px) {
  .page {
    padding: 16px 16px 40px;
  }
  .title {
    font-size: 20px;
  }
  .pair {
    grid-template-columns: 1fr;
  }
  .side {
    position: static;
  }
}
</style>
