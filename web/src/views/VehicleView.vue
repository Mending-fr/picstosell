<script setup lang="ts">
import { onThousandsInput } from '../inputs'
import PriceHelper from '../components/PriceHelper.vue'
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { onBeforeRouteLeave, useRoute, useRouter } from 'vue-router'
import { ApiError, del, download, get, patch, post, errorMessage } from '../api'
import { FUELS, GEARBOXES, RENDER_STATE, SHOTS, INTERIOR_LABELS, DETAIL_LABELS, copyText, listingText, parseNum, num, vehicleTitle } from '../labels'
import { isManager, toast, refreshSidebar } from '../session'
import StatusPill from '../components/StatusPill.vue'
import PhotoPicker, { type PickedPhoto } from '../components/PhotoPicker.vue'
import EquipmentEditor from '../components/EquipmentEditor.vue'
import ImageViewer, { type ViewerItem } from '../components/ImageViewer.vue'
import AppModal from '../components/AppModal.vue'
import MoreMenu, { type MenuItem } from '../components/MoreMenu.vue'
import { useSaveBar } from '../saveBar'
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

function nextToReview(): boolean {
  const list = photos.value
  const start = list.findIndex((p) => p.id === selected.value)
  for (let i = 1; i <= list.length; i++) {
    const p = list[(start + i) % list.length]
    if (renderOf.value.get(p.id)?.status === 'to_review') {
      selected.value = p.id
      return true
    }
  }
  return false
}

// ---------- vérification des photos, dans une fenêtre ----------
const reviewOpen = ref(false)
function openReview(photoId?: number) {
  if (photoId) selected.value = photoId
  else {
    const first = photos.value.find((p) => renderOf.value.get(p.id)?.status === 'to_review')
    if (first) selected.value = first.id
  }
  reviewOpen.value = true
}
const curIndex = computed(() => photos.value.findIndex((p) => p.id === selected.value))
function step(d: number) {
  const list = photos.value
  if (!list.length) return
  selected.value = list[(curIndex.value + d + list.length) % list.length].id
}
/** Vignette : la version showroom quand elle existe, sinon la photo du parking. */
function thumbOf(p: Photo) {
  const r = renderOf.value.get(p.id)
  return r?.url && (r.status === 'approved' || r.status === 'to_review') ? r.url : p.thumb_url
}
/** Pastille sur la vignette, seulement quand il y a quelque chose à faire ou à attendre. */
function badgeOf(p: Photo): { label: string; tone: string } | null {
  if (p.kind !== 'exterior') return null
  const r = renderOf.value.get(p.id)
  if (!r) return { label: 'Sans showroom', tone: 'slate' }
  if (r.status === 'to_review') return { label: 'À vérifier', tone: 'amber' }
  if (r.status === 'pending' || r.status === 'processing') return { label: 'En préparation', tone: 'blue' }
  if (r.status === 'failed') return { label: 'Échec', tone: 'red' }
  return null
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
  if (kind !== 'retry' && !nextToReview() && reviewOpen.value) {
    reviewOpen.value = false
    toast('Toutes les photos sont vérifiées.')
  }
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

// ---------- ce que montre la photo (reconnu par l'IA, corrigeable) ----------
const fixing = ref(false)
function classOf(p: Photo): string {
  if (p.kind === 'exterior') return `exterior:${p.shot_key ?? ''}`
  const list = p.kind === 'interior' ? INTERIOR_LABELS : DETAIL_LABELS
  return `${p.kind}:${p.label && list.includes(p.label) ? p.label : list[list.length - 1]}`
}
async function reclassify(p: Photo, value: string) {
  const [kind, rest] = value.split(':') as [Photo['kind'], string]
  fixing.value = true
  try {
    const np = await patch<Photo>(`/photos/${p.id}`, kind === 'exterior' ? { kind, shot_key: rest || null } : { kind, label: rest })
    if (v.value) v.value.photos = (v.value.photos ?? []).map((x) => (x.id === p.id ? np : x))
    if (p.kind === 'exterior' && kind !== 'exterior') {
      toast('Photo publiée telle quelle : elle ne passe plus par le showroom.')
      load(true)
    } else if (p.kind !== 'exterior' && kind === 'exterior') {
      toast('Photo rangée en extérieur. Lancez « Préparer les photos showroom » pour la placer dans le décor.')
      load(true)
    }
  } catch (e) {
    toast(errorMessage(e), 'error')
  } finally {
    fixing.value = false
  }
}

async function removePhoto(p: Photo) {
  if (!window.confirm(`Supprimer la photo « ${p.display_label} » ?`)) return
  if (photos.value.length <= 1) reviewOpen.value = false
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
const addOpen = ref(false)
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
    addOpen.value = false
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
// Enregistrer et Annuler sont dans la barre du haut, comme dans Shopylike.
useSaveBar({ dirty, saving, save: () => saveListing(), discard: () => v.value && fillForm(v.value) })
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
const equipOpen = ref(false)
const equipmentCount = computed(() => equipment.value.reduce((n, g) => n + g.items.length, 0))

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

async function markSold() {
  if (!window.confirm("Marquer cette voiture comme vendue ? L'annonce sera retirée de votre site.")) return
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

const moreItems = computed<MenuItem[]>(() => [
  { label: 'Télécharger les photos (ZIP)', action: exportZip, disabled: exporting.value || !photos.value.length },
  { label: "Copier le texte de l'annonce", action: copyListing },
  ...(isSold.value ? [] : [{ label: 'Marquer comme vendue', action: markSold, danger: true, disabled: publishing.value }]),
])

const queuePos = computed(() => queue.value.indexOf(id))
const nextVehicle = computed(() => {
  const others = queue.value.filter((x) => x !== id)
  if (!others.length) return null
  const after = queue.value.slice(queuePos.value + 1).find((x) => x !== id)
  return after ?? others[0]
})

// ---------- photos en grand ----------
// Pour chaque angle : la photo du parking puis sa version showroom, pour passer de l'une à l'autre avec les flèches.
const viewerItems = computed(() => {
  const items: (ViewerItem & { key: string })[] = []
  for (const p of photos.value) {
    const name = p.display_label
    items.push({ key: `${p.id}-p`, url: p.url, label: p.kind === 'exterior' ? `${name} · sur le parking` : name })
    const r = renderOf.value.get(p.id)
    if (p.kind === 'exterior' && r?.url && r.status !== 'pending' && r.status !== 'processing' && r.status !== 'failed') {
      items.push({ key: `${p.id}-s`, url: r.url, label: `${name} · dans le showroom`, sub: stateOf(p).label })
    }
  }
  return items
})
const viewerAt = ref<number | null>(null)
function zoom(photoId: number, which: 'p' | 's') {
  const i = viewerItems.value.findIndex((x) => x.key === `${photoId}-${which}`)
  viewerAt.value = i >= 0 ? i : 0
}

// ---------- clavier ----------
function onKey(e: KeyboardEvent) {
  const t = e.target as HTMLElement
  const typing = t && (t.tagName === 'INPUT' || t.tagName === 'TEXTAREA' || t.tagName === 'SELECT' || t.isContentEditable)
  if ((e.metaKey || e.ctrlKey) && e.key === 'Enter') {
    e.preventDefault()
    if (!isOnline.value && !isSold.value) publishAll()
    return
  }
  if (typing || e.metaKey || e.ctrlKey || e.altKey || !isManager.value || !reviewOpen.value || viewerAt.value !== null) return
  const k = e.key.toLowerCase()
  const r = curRender.value
  if (k === 'arrowright' || k === 'arrowleft') {
    step(k === 'arrowright' ? 1 : -1)
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
      <RouterLink to="/vehicles" class="btn btn-primary">Retour au parc</RouterLink>
    </div>
    <div v-else-if="loadError" class="empty" style="min-height: 60vh; justify-content: center">
      <strong>Impossible d'afficher cette voiture</strong>
      <span>{{ loadError }}</span>
      <button type="button" class="btn btn-primary" @click="(loadError = ''), load()">Réessayer</button>
    </div>

    <template v-else>
      <!-- en-tête : retour, titre, statut ; à droite la file à vérifier et les actions secondaires -->
      <header class="head">
        <button type="button" class="icon-btn" aria-label="Retour" @click="router.back()">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M15 6l-6 6 6 6" /></svg>
        </button>
        <div v-if="v" class="head-t">
          <h1 class="title" :title="vehicleTitle(v)">{{ vehicleTitle(v) }}</h1>
          <span class="plate">{{ v.plate }}</span>
          <StatusPill :status="v.status" />
        </div>
        <div v-else class="head-t"><span class="skel" style="width: 320px; height: 24px" /></div>
        <div v-if="v && isManager" class="head-a">
          <template v-if="queue.length > 1 || (queue.length === 1 && queuePos < 0)">
            <span class="muted small">{{ queuePos >= 0 ? `${queuePos + 1} sur ${queue.length} à vérifier` : `${queue.length} à vérifier` }}</span>
            <RouterLink v-if="nextVehicle" :to="`/vehicles/${nextVehicle}`" class="btn">Suivante</RouterLink>
          </template>
          <MoreMenu :items="moreItems" />
        </div>
      </header>

      <div class="layout">
        <!-- colonne principale -->
        <div class="main-col">
          <!-- photos -->
          <section class="card" aria-labelledby="ph-t">
            <div class="card-h">
              <h2 id="ph-t">Photos <span v-if="photos.length" class="muted count">{{ photos.length }}</span></h2>
              <button v-if="isManager && photos.length" type="button" class="btn-link" @click="addOpen = true">Ajouter des photos</button>
            </div>

            <template v-if="!v">
              <div class="grid"><div v-for="i in 7" :key="i" class="skel tile" :class="{ big: i === 1 }" /></div>
            </template>

            <button v-else-if="!photos.length" type="button" class="drop" :disabled="!isManager" @click="addOpen = true">
              <strong>Ajouter des photos</strong>
              <span>Prises avec l'appli sur le parking ou importées depuis l'ordinateur. On les place ensuite dans votre showroom.</span>
            </button>

            <template v-else>
              <div v-if="pending" class="note blue" role="status">
                <span class="spinner" aria-hidden="true" />
                <span class="grow">Photos en préparation. La page se met à jour toute seule.</span>
              </div>
              <div v-else-if="isManager && toReview" class="note amber">
                <span class="grow">{{ toReview }} photo{{ toReview > 1 ? 's' : '' }} à vérifier avant la mise en ligne</span>
                <button type="button" class="btn btn-sm btn-primary" @click="openReview()">Vérifier</button>
              </div>
              <div v-else-if="isManager && needRender.length" class="note">
                <span class="grow">{{ needRender.length }} photo{{ needRender.length > 1 ? 's' : '' }} sans version showroom</span>
                <button type="button" class="btn btn-sm" :disabled="launching" @click="launchRenders"><span v-if="launching" class="spinner" aria-hidden="true" />Préparer</button>
              </div>

              <div class="grid">
                <button v-for="(p, i) in photos" :key="p.id" type="button" class="tile" :class="{ big: i === 0 }" :title="p.display_label" @click="openReview(p.id)">
                  <img :src="thumbOf(p)" :alt="p.display_label" loading="lazy" />
                  <span v-if="badgeOf(p)" class="badge-s" :class="badgeOf(p)!.tone">{{ badgeOf(p)!.label }}</span>
                </button>
              </div>
            </template>
          </section>

          <!-- description -->
          <section v-if="isManager" class="card" aria-labelledby="ds-t">
            <div class="card-h">
              <h2 id="ds-t">Description</h2>
              <button type="button" class="btn-link" :disabled="rewriting || !v" @click="rewrite"><span v-if="rewriting" class="spinner" aria-hidden="true" style="width: 12px; height: 12px; margin-right: 6px" />Réécrire avec l'IA</button>
            </div>
            <label class="sr-only" for="v-desc">Description</label>
            <textarea id="v-desc" v-model="form.description" class="f" rows="8" :disabled="!v" placeholder="Quelques phrases sur la voiture : entretien, propriétaires, points forts." />
            <span class="muted small">Texte proposé par l'IA à partir des informations de la voiture. Relisez-le avant de mettre en ligne.</span>
          </section>

          <!-- caractéristiques -->
          <section v-if="isManager" class="card" aria-labelledby="ca-t">
            <div class="card-h"><h2 id="ca-t">Caractéristiques</h2></div>
            <div v-if="v" class="fields">
              <label class="lbl">Marque<input v-model="form.make" class="f" /></label>
              <label class="lbl">Modèle<input v-model="form.model" class="f" /></label>
              <label class="lbl">Version<input v-model="form.version" class="f" /></label>
              <label class="lbl">Kilométrage<input :value="form.mileage" class="f" inputmode="numeric" :aria-invalid="!!fieldErrors.mileage" @input="form.mileage = onThousandsInput($event)" /></label>
              <label class="lbl">1re immatriculation<input v-model="form.first_registration" class="f" type="month" /></label>
              <label class="lbl">
                Énergie
                <select v-model="form.fuel" class="f">
                  <option value="">Non précisée</option>
                  <option v-for="o in FUELS" :key="o.value" :value="o.value">{{ o.label }}</option>
                </select>
              </label>
              <label class="lbl">
                Boîte
                <select v-model="form.gearbox" class="f">
                  <option value="">Non précisée</option>
                  <option v-for="o in GEARBOXES" :key="o.value" :value="o.value">{{ o.label }}</option>
                </select>
              </label>
              <label class="lbl">Puissance (ch)<input v-model="form.power_hp" class="f" inputmode="numeric" /></label>
              <label class="lbl">Puissance fiscale (CV)<input v-model="form.fiscal_hp" class="f" inputmode="numeric" /></label>
              <label class="lbl">Couleur<input v-model="form.color" class="f" /></label>
              <label class="lbl">Portes<input v-model="form.doors" class="f" inputmode="numeric" /></label>
              <label class="lbl">Places<input v-model="form.seats" class="f" inputmode="numeric" /></label>
            </div>
            <div v-else class="fields"><span v-for="i in 6" :key="i" class="skel" style="height: 52px" /></div>
          </section>

          <!-- équipements -->
          <section v-if="isManager" class="card" aria-labelledby="eq-t">
            <div class="card-h">
              <h2 id="eq-t">Équipements <span v-if="equipmentCount" class="muted count">{{ equipmentCount }}</span></h2>
              <button type="button" class="btn-link" :disabled="!v" @click="equipOpen = true">{{ equipmentCount ? 'Modifier' : 'Ajouter' }}</button>
            </div>
            <dl v-if="equipment.some((g) => g.items.length)" class="eq">
              <template v-for="g in equipment.filter((x) => x.items.length)" :key="g.group">
                <dt>{{ g.group || 'Autres' }}</dt>
                <dd>{{ g.items.join(' · ') }}</dd>
              </template>
            </dl>
            <span v-else class="muted small">Aucun équipement renseigné.</span>
          </section>
        </div>

        <!-- colonne latérale -->
        <aside v-if="isManager" class="side-col">
          <section class="card" aria-labelledby="pub-t">
            <h2 id="pub-t">Votre site</h2>
            <template v-if="v">
              <div class="state">
                <span class="dot" :class="isOnline ? 'on' : isSold ? 'sold' : 'off'" aria-hidden="true" />
                <span>{{ isOnline ? 'En ligne' : isSold ? `Vendue${v.sold_at ? ` le ${new Date(v.sold_at).toLocaleDateString('fr-FR')}` : ''}` : 'Pas encore en ligne' }}</span>
              </div>
              <button v-if="!isOnline && !isSold" type="button" class="btn btn-primary full" :disabled="publishing" @click="publishAll">
                <span v-if="publishing" class="spinner" aria-hidden="true" />Mettre en ligne <span class="kbd-inv" aria-hidden="true">⌘ ↵</span>
              </button>
              <button v-else-if="isOnline" type="button" class="btn full" :disabled="publishing" @click="unpublish">Retirer de mon site</button>
              <span v-if="!isOnline && !isSold && toReview" class="muted small">Les {{ toReview }} photo{{ toReview > 1 ? 's' : '' }} à vérifier seront validées en même temps.</span>
              <p v-if="publishError" class="err" role="alert" style="margin: 0">{{ publishError }}</p>
            </template>
            <span v-else class="skel" style="height: 32px" />
          </section>

          <section class="card" aria-labelledby="pr-t">
            <h2 id="pr-t">Prix</h2>
            <label class="lbl">
              Prix de vente TTC
              <span class="suffix">
                <input id="v-price" :value="form.price" class="f" inputmode="numeric" :disabled="!v" :aria-invalid="!!fieldErrors.price" @input="form.price = onThousandsInput($event)" />
                <span aria-hidden="true">€</span>
              </span>
              <span v-if="fieldErrors.price" class="err">{{ fieldErrors.price }}</span>
            </label>
            <PriceHelper v-if="v" :car="priceCar" @use="(p) => (form.price = num(p))" />
          </section>

          <section class="card" aria-labelledby="hi-t">
            <div class="card-h">
              <h2 id="hi-t">Historique</h2>
              <a v-if="v?.history_url" :href="v.history_url" target="_blank" rel="noopener" class="btn-link">Ouvrir</a>
            </div>
            <label class="lbl">
              Lien du rapport HistoVec
              <input v-model="form.history_url" class="f" type="url" :disabled="!v" placeholder="https://" :aria-invalid="!!fieldErrors.history_url" />
              <span v-if="fieldErrors.history_url" class="err">{{ fieldErrors.history_url }}</span>
            </label>
          </section>

          <section class="card" aria-labelledby="ot-t">
            <h2 id="ot-t">Leboncoin, La Centrale</h2>
            <span class="muted small">Récupérez les photos et le texte pour publier l'annonce sur ces sites.</span>
            <div class="row" style="gap: 8px">
              <button type="button" class="btn btn-sm" :disabled="exporting || !photos.length" @click="exportZip"><span v-if="exporting" class="spinner" aria-hidden="true" />Photos (ZIP)</button>
              <button type="button" class="btn btn-sm" :disabled="!v" @click="copyListing">Copier le texte</button>
            </div>
          </section>
        </aside>
      </div>
    </template>
  </div>

  <!-- vérifier une photo -->
  <AppModal v-if="reviewOpen && cur" xl :title="`${cur.display_label} · ${curIndex + 1} sur ${photos.length}`" @close="reviewOpen = false">
    <div class="rv-bar">
      <label v-if="isManager" class="rv-kind">
        <span class="muted small">Ce que montre la photo</span>
        <select class="f f-sm" :value="classOf(cur)" :disabled="fixing" @change="reclassify(cur, ($event.target as HTMLSelectElement).value)">
          <optgroup label="Extérieur (placée dans le showroom)">
            <option v-for="s in SHOTS" :key="s.key" :value="`exterior:${s.key}`">{{ s.label }}</option>
            <option value="exterior:">Extérieur, autre angle</option>
          </optgroup>
          <optgroup label="Intérieur (publiée telle quelle)">
            <option v-for="l in INTERIOR_LABELS" :key="l" :value="`interior:${l}`">{{ l }}</option>
          </optgroup>
          <optgroup label="Détail (publiée telle quelle)">
            <option v-for="l in DETAIL_LABELS" :key="l" :value="`detail:${l}`">{{ l }}</option>
          </optgroup>
        </select>
      </label>
      <span v-if="cur.classified === 'ai'" class="pill ai">Reconnue par l'IA</span>
      <span class="grow" />
      <span class="small" :style="{ color: stateOf(cur).color }">{{ stateOf(cur).label }}</span>
      <div v-if="curRender?.url && cur.kind === 'exterior'" role="group" aria-label="Affichage" class="seg">
        <button type="button" :aria-pressed="viewMode === 'side'" @click="viewMode = 'side'">Côte à côte</button>
        <button type="button" :aria-pressed="viewMode === 'overlay'" @click="viewMode = 'overlay'">Superposé</button>
      </div>
    </div>

    <figure v-if="cur.kind !== 'exterior'" class="fig one">
      <button type="button" class="zoom" aria-label="Voir en grand" @click="zoom(cur.id, 'p')"><img :src="cur.url" :alt="cur.display_label" class="img" /></button>
      <figcaption>Photo publiée telle quelle.</figcaption>
    </figure>

    <div v-else-if="viewMode === 'overlay' && curRender?.url" class="col" style="gap: 8px">
      <div class="ov">
        <img :src="cur.url" alt="Sur le parking" class="img" />
        <img :src="curRender.url" alt="Dans votre showroom" class="img top" :style="{ clipPath: `inset(0 0 0 ${split}%)` }" />
        <span class="ov-line" :style="{ left: split + '%' }" aria-hidden="true" />
        <span class="tag l">Parking</span><span class="tag r">Showroom</span>
      </div>
      <label><span class="sr-only">Glisser pour comparer</span><input v-model.number="split" type="range" min="0" max="100" /></label>
    </div>

    <div v-else class="pair">
      <figure class="fig">
        <button type="button" class="zoom" aria-label="Voir la photo du parking en grand" @click="zoom(cur.id, 'p')"><img :src="cur.url" alt="Sur le parking" class="img" /></button>
        <figcaption>Sur le parking</figcaption>
      </figure>
      <figure class="fig">
        <button v-if="curRender?.url && curRender.status !== 'pending' && curRender.status !== 'processing'" type="button" class="zoom" aria-label="Voir la photo showroom en grand" @click="zoom(cur.id, 's')"><img :src="curRender.url" alt="Dans votre showroom" class="img" /></button>
        <div v-else-if="curRender && (curRender.status === 'pending' || curRender.status === 'processing')" class="img wait"><span class="spinner" aria-hidden="true" /> En préparation…</div>
        <div v-else-if="curRender?.status === 'failed'" class="img wait ko">La préparation a échoué{{ curRender.error ? ` : ${curRender.error}` : '' }}.</div>
        <div v-else class="img wait">
          <span>Pas encore de version showroom</span>
          <button v-if="isManager" type="button" class="btn btn-sm" :disabled="launching" @click="launchRenders">Préparer</button>
        </div>
        <figcaption>Dans votre showroom</figcaption>
      </figure>
    </div>

    <template #footer>
      <button type="button" class="icon-btn" aria-label="Photo précédente" @click="step(-1)"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path d="M15 6l-6 6 6 6" /></svg></button>
      <button type="button" class="icon-btn" aria-label="Photo suivante" @click="step(1)"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path d="M9 6l6 6-6 6" /></svg></button>
      <button v-if="isManager" type="button" class="btn-link danger" @click="removePhoto(cur)">Supprimer</button>
      <span class="grow" />
      <template v-if="isManager && curRender">
        <button type="button" class="btn" :disabled="acting || ['kept_original', 'pending', 'processing'].includes(curRender.status)" @click="act('keep')">Garder la photo du parking <span class="kbd" aria-hidden="true">O</span></button>
        <button type="button" class="btn" :disabled="acting || ['pending', 'processing'].includes(curRender.status)" @click="act('retry')">Refaire <span class="kbd" aria-hidden="true">R</span></button>
        <button type="button" class="btn btn-primary" :disabled="acting || curRender.status !== 'to_review' || !curRender.url" @click="act('approve')">C'est bon <span class="kbd-inv" aria-hidden="true">A</span></button>
      </template>
    </template>
  </AppModal>

  <!-- ajouter des photos -->
  <AppModal v-if="addOpen" title="Ajouter des photos" wide @close="!sending && ((addOpen = false), (newPhotos = []))">
    <PhotoPicker v-model="newPhotos" :taken="takenKeys" :disabled="sending" />
    <template #footer>
      <button type="button" class="btn" :disabled="sending" @click="(addOpen = false), (newPhotos = [])">Annuler</button>
      <button type="button" class="btn btn-primary" :disabled="!newPhotos.length || sending" @click="sendPhotos"><span v-if="sending" class="spinner" aria-hidden="true" />Envoyer</button>
    </template>
  </AppModal>

  <!-- équipements -->
  <AppModal v-if="equipOpen" title="Équipements" wide @close="equipOpen = false">
    <EquipmentEditor v-model="equipment" />
    <template #footer>
      <span class="muted small grow">Enregistrez ensuite avec la barre du haut.</span>
      <button type="button" class="btn btn-primary" @click="equipOpen = false">Terminé</button>
    </template>
  </AppModal>

  <ImageViewer v-if="viewerAt !== null" :items="viewerItems" :start="viewerAt" @close="viewerAt = null" />
</template>

<style scoped>
.page {
  max-width: 1160px;
  margin: 0 auto;
  width: 100%;
  display: flex;
  flex-direction: column;
  gap: 16px;
}
.small {
  font-size: 12.5px;
}
.head {
  display: flex;
  align-items: center;
  gap: 12px;
  min-height: 40px;
}
.head-t {
  display: flex;
  align-items: center;
  gap: 10px;
  min-width: 0;
  flex: 1;
}
.title {
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  min-width: 0;
}
.head-a {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-shrink: 0;
}
.layout {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 300px;
  gap: 16px;
  align-items: start;
}
.main-col,
.side-col {
  display: flex;
  flex-direction: column;
  gap: 16px;
  min-width: 0;
}
.card-h {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  min-height: 28px;
}
.count {
  font-weight: 450;
  margin-left: 4px;
}
.full {
  width: 100%;
}

/* photos */
.grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(112px, 1fr));
  grid-auto-rows: 1fr;
  gap: 8px;
}
.tile {
  position: relative;
  aspect-ratio: 1;
  border: 0;
  padding: 0;
  border-radius: 8px;
  overflow: hidden;
  background: #f1f1f1;
  box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.08);
  cursor: pointer;
}
.tile.big {
  grid-column: span 2;
  grid-row: span 2;
}
.tile img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.tile:hover img {
  filter: brightness(0.94);
}
.badge-s {
  position: absolute;
  left: 6px;
  bottom: 6px;
  font-size: 11.5px;
  font-weight: 550;
  border-radius: 6px;
  padding: 1px 6px;
  background: #fff;
  color: var(--ink);
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.15);
}
.badge-s.amber {
  background: #ffd79d;
  color: #5e4200;
}
.badge-s.blue {
  background: #e0f0ff;
  color: #00428a;
}
.badge-s.red {
  background: #fedad9;
  color: #8e0b21;
}
.badge-s.slate {
  background: #f1f1f1;
  color: var(--muted);
}
.drop {
  border: 1.5px dashed #b5b5b5;
  border-radius: 10px;
  background: #fafafa;
  padding: 32px 16px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  text-align: center;
  color: var(--muted);
  font-size: 13px;
}
.drop strong {
  color: var(--accent-ink);
  font-size: 14px;
}
.drop:hover:not(:disabled) {
  background: #f4f4f4;
}
.note {
  display: flex;
  align-items: center;
  gap: 10px;
  border-radius: 8px;
  padding: 8px 10px 8px 12px;
  font-size: 13px;
  background: #f1f1f1;
}
.note.amber {
  background: #fff1e0;
  color: #5e4200;
}
.note.blue {
  background: #eaf4ff;
  color: #00428a;
}

/* caractéristiques */
.fields {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 12px;
}
.eq {
  margin: 0;
  display: grid;
  grid-template-columns: max-content 1fr;
  gap: 6px 16px;
  font-size: 13px;
}
.eq dt {
  font-weight: 550;
}
.eq dd {
  margin: 0;
  color: var(--ink-2);
}

/* colonne latérale */
.state {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
}
.state .dot.on {
  background: var(--green-dot);
}
.state .dot.off {
  background: #b5b5b5;
}
.state .dot.sold {
  background: var(--red-ink);
}
.suffix {
  position: relative;
  display: block;
}
.suffix .f {
  padding-right: 28px;
}
.suffix > span {
  position: absolute;
  right: 10px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--muted);
  font-size: 13px;
}

/* fenêtre de vérification */
.rv-bar {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}
.rv-kind {
  display: flex;
  align-items: center;
  gap: 8px;
}
.rv-kind select {
  width: auto;
  max-width: 240px;
}
.pill.ai {
  color: var(--violet-ink);
  background: var(--violet-tint);
}
.zoom {
  padding: 0;
  border: 0;
  background: none;
  cursor: zoom-in;
  display: block;
  width: 100%;
  border-radius: 8px;
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
  gap: 6px;
}
.fig.one {
  max-width: 640px;
  align-self: center;
  width: 100%;
}
.fig figcaption {
  font-size: 12.5px;
  color: var(--muted);
}
.img {
  width: 100%;
  aspect-ratio: 4/3;
  object-fit: cover;
  border-radius: 8px;
  background: #f1f1f1;
}
.wait {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 10px;
  color: var(--muted);
  font-size: 13px;
  text-align: center;
  padding: 12px;
}
.wait.ko {
  color: #8e0b21;
  background: #fedad9;
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
  top: 8px;
  font-size: 12px;
  font-weight: 550;
  background: rgba(255, 255, 255, 0.92);
  border-radius: 6px;
  padding: 2px 8px;
}
.tag.l {
  left: 8px;
}
.tag.r {
  right: 8px;
}
.danger {
  color: var(--red-ink);
}

@media (max-width: 1000px) {
  .layout {
    grid-template-columns: minmax(0, 1fr);
  }
}
@media (max-width: 640px) {
  .fields {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  .pair {
    grid-template-columns: minmax(0, 1fr);
  }
  .head-t .plate {
    display: none;
  }
}
</style>
