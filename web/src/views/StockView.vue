<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { get, post, errorMessage } from '../api'
import { STATUS, euros, num, days, vehicleTitle } from '../labels'
import { isManager, toast } from '../session'
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

watch([tab, q], () => {
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
onMounted(load)

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
    <h1>Mon stock</h1>
    <div class="row">
      <button v-if="isManager" type="button" class="btn" :disabled="!rows.length" @click="exportCsv">Exporter la liste</button>
      <RouterLink to="/vehicles/new" class="btn btn-primary">Ajouter une voiture</RouterLink>
    </div>
  </div>

  <div role="tablist" aria-label="Filtrer par étape" class="tabs">
    <button v-for="t in TABS" :key="t.id" type="button" role="tab" class="tab" :aria-selected="tab === t.id" @click="tab = t.id">
      {{ t.label }}<span class="n">{{ all ? count(t) : '' }}</span>
    </button>
  </div>

  <label class="search">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#5B606B" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7" /><path d="M20 20l-4-4" /></svg>
    <span class="sr-only">Chercher dans le stock</span>
    <input v-model="q" type="search" placeholder="Plaque ou modèle" />
  </label>

  <p v-if="error" class="alert alert-error" role="alert">
    {{ error }} <button type="button" class="btn-link" @click="load">Réessayer</button>
  </p>

  <div class="table-wrap">
    <table class="t" style="min-width: 860px">
      <thead>
        <tr>
          <th>Voiture</th>
          <th>Plaque</th>
          <th>Prix</th>
          <th>Km</th>
          <th>En stock</th>
          <th>Demandes</th>
          <th>Étape</th>
          <th><span class="sr-only">Action</span></th>
        </tr>
      </thead>
      <tbody v-if="!all && !error">
        <tr v-for="i in 6" :key="i">
          <td><div class="row"><span class="skel thumb" /><span class="skel" style="width: 140px; height: 16px" /></div></td>
          <td v-for="j in 7" :key="j"><span class="skel" style="display: block; width: 70px; height: 16px" /></td>
        </tr>
      </tbody>
      <tbody v-else>
        <tr v-for="v in rows" :key="v.id" class="clickable" @click="router.push(`/vehicles/${v.id}`)">
          <td>
            <div class="row" style="flex-wrap: nowrap; gap: 12px">
              <img v-if="v.cover_url" :src="v.cover_url" alt="" class="thumb" loading="lazy" />
              <span v-else class="thumb ph" />
              <RouterLink :to="`/vehicles/${v.id}`" class="car-link" @click.stop>{{ vehicleTitle(v, false) }}</RouterLink>
            </div>
          </td>
          <td><span class="plate">{{ v.plate }}</span></td>
          <td style="font-weight: 600">{{ euros(v.price) || '–' }}</td>
          <td>{{ num(v.mileage) || '–' }}</td>
          <td :style="{ color: v.days_in_stock > 45 ? '#B45309' : undefined, fontWeight: v.days_in_stock > 45 ? 600 : undefined }">{{ days(v.days_in_stock) }}</td>
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
        <RouterLink to="/vehicles/new" class="btn btn-primary">Ajouter une voiture</RouterLink>
      </template>
      <template v-else>Aucune voiture ici pour l'instant.</template>
    </div>
  </div>
  <span class="muted" style="font-size: 13px">En orange : en stock depuis plus de 45 jours. C'est peut-être le moment de revoir le prix.</span>
</template>

<style scoped>
.search {
  display: flex;
  align-items: center;
  gap: 10px;
  background: #fff;
  border: 1px solid var(--field-2);
  border-radius: 10px;
  padding: 0 12px;
  height: 44px;
  max-width: 420px;
}
.search:focus-within {
  outline: 3px solid #4c7bff;
  outline-offset: 1px;
}
.search input {
  border: 0;
  outline: 0;
  flex: 1;
  font: inherit;
  font-size: 15px;
  background: transparent;
  min-width: 0;
}
.car-link {
  font-weight: 600;
  color: var(--ink);
  text-decoration: none;
}
.car-link:hover {
  text-decoration: underline;
  color: var(--ink);
}
</style>
