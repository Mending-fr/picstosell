<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { get, post, errorMessage } from '../api'
import { STATUS, euros, num, days, vehicleTitle, fuelLabel } from '../labels'
import { isManager, toast } from '../session'
import { modals, openAdd } from '../modals'
import StatusPill from '../components/StatusPill.vue'
import type { Vehicle, VehicleStatus } from '../types'

const route = useRoute()
const router = useRouter()
const all = ref<Vehicle[] | null>(null)
const error = ref('')

type Tab = 'all' | 'to_photograph' | 'to_review' | 'ready' | 'online' | 'sold'
const TABS: { id: Tab; label: string; match: VehicleStatus[] }[] = [
  { id: 'all', label: 'Toutes', match: ['to_photograph', 'processing', 'to_review', 'ready', 'online', 'sold'] },
  { id: 'to_photograph', label: 'À photographier', match: ['to_photograph', 'processing'] },
  { id: 'to_review', label: 'À vérifier', match: ['to_review'] },
  { id: 'ready', label: 'Prêtes', match: ['ready'] },
  { id: 'online', label: 'En ligne', match: ['online'] },
  { id: 'sold', label: 'Vendues', match: ['sold'] },
]

const tab = ref<Tab>((TABS.find((t) => t.id === route.query.status)?.id ?? 'all') as Tab)
const q = ref(typeof route.query.q === 'string' ? route.query.q : '')

// La recherche de la barre du haut arrive par l'adresse (?q=), même quand on est déjà sur le stock.
watch(
  () => route.query.q,
  (v) => {
    const s = typeof v === 'string' ? v : ''
    if (s !== q.value.trim()) q.value = s
  },
)

watch([tab, q], () => {
  page.value = 1
  const query: Record<string, string> = {}
  if (tab.value !== 'all') query.status = tab.value
  if (q.value.trim()) query.q = q.value.trim()
  router.replace({ query })
})

async function load() {
  error.value = ''
  try {
    all.value = (await get<{ data: Vehicle[] }>('/vehicles')).data
  } catch (e) {
    error.value = errorMessage(e)
  }
}
onMounted(() => {
  load()
  // Ancien lien /vehicles/new : on ouvre la modale d'ajout par-dessus le stock.
  if (route.query.ajouter) {
    const query = { ...route.query }
    delete query.ajouter
    router.replace({ query })
    openAdd()
  }
})
watch(
  () => modals.version,
  () => load(),
)

function matchesQ(v: Vehicle, s: string) {
  if (!s) return true
  const plate = v.plate.replace(/[^A-Z0-9]/gi, '').toLowerCase()
  const sq = s.replace(/[^a-z0-9]/gi, '').toLowerCase()
  return `${v.plate} ${v.make ?? ''} ${v.model ?? ''} ${v.version ?? ''}`.toLowerCase().includes(s) || (!!sq && plate.includes(sq))
}

const searched = computed(() => {
  const s = q.value.trim().toLowerCase()
  return (all.value ?? []).filter((v) => matchesQ(v, s))
})
const rows = computed(() => {
  const t = TABS.find((x) => x.id === tab.value)!
  return searched.value.filter((v) => t.match.includes(v.status))
})
const count = (t: (typeof TABS)[number]) => searched.value.filter((v) => t.match.includes(v.status)).length

// ---------- pages : autant de lignes que l'écran en affiche, sans défiler ----------
const page = ref(1)
const perPage = ref(10)
function fit() {
  // barre (64) + marges (60) + titre (40) + en-tête du tableau (42) + pied (44) ; une ligne fait 57 px
  perPage.value = Math.max(6, Math.floor((window.innerHeight - 64 - 60 - 40 - 42 - 44 - 20) / 57))
}
onMounted(() => {
  fit()
  window.addEventListener('resize', fit)
})
onBeforeUnmount(() => window.removeEventListener('resize', fit))
const pages = computed(() => Math.max(1, Math.ceil(rows.value.length / perPage.value)))
const shown = computed(() => {
  const p = Math.min(page.value, pages.value)
  return rows.value.slice((p - 1) * perPage.value, p * perPage.value)
})
const year = (v: Vehicle) => (v.first_registration ? v.first_registration.slice(0, 4) : '–')

function cta(v: Vehicle): string {
  if (v.status === 'sold' && v.published_at) return "Retirer l'annonce"
  if (!isManager.value) return v.status === 'to_photograph' ? 'Ajouter les photos' : 'Voir'
  return STATUS[v.status].cta
}

const busy = ref<number | null>(null)
async function action(v: Vehicle) {
  if (v.status === 'sold' && v.published_at) {
    busy.value = v.id
    try {
      await post(`/vehicles/${v.id}/publish`, { published: false })
      v.published_at = null
      toast("L'annonce est retirée de votre site.")
    } catch (e) {
      toast(errorMessage(e), 'error')
    } finally {
      busy.value = null
    }
    return
  }
  router.push(`/vehicles/${v.id}`)
}

function exportCsv() {
  const head = ['Plaque', 'Marque', 'Modèle', 'Version', 'Prix', 'Kilométrage', 'Jours en stock', 'Demandes', 'Étape']
  const lines = rows.value.map((v) =>
    [v.plate, v.make, v.model, v.version, v.price, v.mileage, v.days_in_stock, v.leads_count, STATUS[v.status].label]
      .map((x) => `"${String(x ?? '').replace(/"/g, '""')}"`)
      .join(';'),
  )
  const blob = new Blob(['﻿' + [head.join(';'), ...lines].join('\n')], { type: 'text/csv;charset=utf-8' })
  const a = document.createElement('a')
  a.href = URL.createObjectURL(blob)
  a.download = `stock-${new Date().toISOString().slice(0, 10)}.csv`
  a.click()
  setTimeout(() => URL.revokeObjectURL(a.href), 2000)
}
</script>

<template>
  <div class="page-head">
    <h1>Mon parc</h1>
    <div role="tablist" aria-label="Filtrer par étape" class="tabs grow">
      <button v-for="t in TABS" :key="t.id" type="button" role="tab" class="tab" :aria-selected="tab === t.id" @click="tab = t.id">
        {{ t.label }}<span class="n">{{ all ? count(t) : '' }}</span>
      </button>
      <span v-if="q.trim()" class="qchip">
        « {{ q.trim() }} »
        <button type="button" aria-label="Effacer la recherche" @click="q = ''">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" /></svg>
        </button>
      </span>
    </div>
    <div class="row">
      <button v-if="isManager" type="button" class="btn" :disabled="!rows.length" @click="exportCsv">Exporter la liste</button>
      <button type="button" class="btn btn-primary" aria-keyshortcuts="N" @click="openAdd">
        Ajouter une voiture<span class="kbd-inv" aria-hidden="true">N</span>
      </button>
    </div>
  </div>

  <p v-if="error" class="alert alert-error" role="alert">
    {{ error }} <button type="button" class="btn-link" @click="load">Réessayer</button>
  </p>

  <div class="table-wrap">
    <table class="t" style="min-width: 980px">
      <thead>
        <tr>
          <th>Voiture</th>
          <th>Plaque</th>
          <th>Année</th>
          <th>Énergie</th>
          <th class="r">Km</th>
          <th class="r">Prix</th>
          <th>En stock</th>
          <th>Demandes</th>
          <th>Étape</th>
          <th><span class="sr-only">Action</span></th>
        </tr>
      </thead>
      <tbody v-if="!all && !error">
        <tr v-for="i in 6" :key="i">
          <td><div class="row"><span class="skel thumb" /><span class="skel" style="width: 140px; height: 16px" /></div></td>
          <td v-for="j in 9" :key="j"><span class="skel" style="display: block; width: 70px; height: 16px" /></td>
        </tr>
      </tbody>
      <tbody v-else>
        <tr v-for="v in shown" :key="v.id" class="clickable" @click="router.push(`/vehicles/${v.id}`)">
          <td>
            <div class="row" style="flex-wrap: nowrap; gap: 12px">
              <img v-if="v.cover_url" :src="v.cover_url" alt="" class="thumb" loading="lazy" />
              <span v-else class="thumb ph" />
              <span class="col" style="gap: 1px; min-width: 0">
                <RouterLink :to="`/vehicles/${v.id}`" class="car-link" @click.stop>{{ vehicleTitle(v, false) }}</RouterLink>
                <span v-if="v.version" class="ver">{{ v.version }}</span>
              </span>
            </div>
          </td>
          <td><span class="plate">{{ v.plate }}</span></td>
          <td>{{ year(v) }}</td>
          <td class="soft">{{ fuelLabel(v.fuel) || '–' }}</td>
          <td class="r">{{ num(v.mileage) || '–' }}</td>
          <td class="r" style="font-weight: 500">{{ euros(v.price) || '–' }}</td>
          <td :style="{ color: v.days_in_stock > 45 ? 'var(--amber-ink)' : undefined, fontWeight: v.days_in_stock > 45 ? 500 : undefined }">{{ days(v.days_in_stock) }}</td>
          <td>{{ v.status === 'online' || v.status === 'sold' || v.leads_count ? v.leads_count : '–' }}</td>
          <td><StatusPill :status="v.status" /></td>
          <td style="text-align: right">
            <button type="button" class="btn-link" :disabled="busy === v.id" @click.stop="action(v)">{{ cta(v) }}</button>
          </td>
        </tr>
      </tbody>
    </table>
    <div v-if="all && !rows.length" class="empty">
      <template v-if="q.trim()">
        <strong>Aucune voiture ne correspond à « {{ q.trim() }} »</strong>
        <button type="button" class="btn" @click="q = ''">Effacer la recherche</button>
      </template>
      <template v-else-if="all.length === 0">
        <strong>Votre stock est vide</strong>
        <span>Ajoutez votre première voiture : tapez la plaque, on remplit le reste.</span>
        <button type="button" class="btn btn-primary" @click="openAdd">Ajouter une voiture</button>
      </template>
      <template v-else>Aucune voiture ici pour l'instant.</template>
    </div>
  </div>
  <div v-if="all && rows.length" class="foot">
    <span>
      {{ shown.length }} {{ shown.length > 1 ? 'voitures' : 'voiture' }} sur {{ rows.length }}
      <span class="sep">·</span> en ambre, en stock depuis plus de 45 jours : le prix est peut-être à revoir
    </span>
    <span v-if="pages > 1" class="pager">
      <button type="button" class="btn-link" :disabled="page <= 1" @click="page--">‹ Précédentes</button>
      Page {{ Math.min(page, pages) }} sur {{ pages }}
      <button type="button" class="btn-link" :disabled="page >= pages" @click="page++">Suivantes ›</button>
    </span>
  </div>
</template>

<style scoped>
.page-head {
  flex-wrap: nowrap;
  align-items: center;
}
.page-head .tabs {
  margin-left: 12px;
  flex-wrap: wrap;
  gap: 6px;
}
.page-head .tab {
  min-height: 36px;
  padding: 0 14px;
  font-size: 13.5px;
}
.qchip {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  height: 36px;
  padding: 0 6px 0 14px;
  border-radius: 999px;
  background: #1f2833;
  color: #fff;
  font-size: 13.5px;
}
.qchip button {
  width: 26px;
  height: 26px;
  border-radius: 13px;
  border: 0;
  background: rgba(255, 255, 255, 0.14);
  color: #fff;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}
.t td {
  padding-top: 8px;
  padding-bottom: 8px;
  height: 40px;
}
.r {
  text-align: right;
}
.soft {
  color: var(--muted);
}
.ver {
  font-size: 12px;
  color: var(--muted-3);
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 280px;
}
.foot {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
  font-size: 13px;
  color: var(--muted-3);
  margin-top: -6px;
}
.sep {
  margin: 0 4px;
}
.pager {
  display: inline-flex;
  align-items: center;
  gap: 14px;
}
.pager .btn-link {
  font-size: 13px;
  min-height: 0;
}
@media (max-width: 1100px) {
  .page-head {
    flex-wrap: wrap;
  }
  .page-head .tabs {
    order: 3;
    flex-basis: 100%;
    margin-left: 0;
  }
}
.car-link {
  font-weight: 500;
  color: var(--ink);
  text-decoration: none;
}
.car-link:hover {
  text-decoration: underline;
  color: var(--ink);
}
</style>
