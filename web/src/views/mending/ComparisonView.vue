<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { ApiError, get, patch, post, errorMessage } from '../../api'
import { METHODS, SHOTS, duration, shotLabel, usd } from '../../labels'
import { session, toast } from '../../session'
import type { AiConfig, Comparison, Render, RenderRating } from '../../types'
import ImageViewer, { type ViewerItem } from '../../components/ImageViewer.vue'

const route = useRoute()
const id = Number(route.params.id)
const data = ref<Comparison | null>(null)
const error = ref('')
const notFound = ref(false)
const COLORS = ['#2E5A88', '#1E7A50', '#A55A0B', '#5B3FC4', '#C0392B', '#4A5263']

/** Notes envoyées mais pas encore confirmées : elles priment sur les données rechargées. */
const inflight = new Map<number, RenderRating | null>()

function applyInflight(c: Comparison) {
  if (!inflight.size) return
  for (const shots of Object.values(c.grid)) for (const cell of Object.values(shots)) for (const r of Object.values(cell.renders)) if (r && inflight.has(r.id)) r.rating = inflight.get(r.id)!
}

async function load(silent = false) {
  try {
    const c = await get<Comparison>(`/admin/comparisons/${id}`)
    applyInflight(c)
    data.value = c
    if (!silent) initCards(c.configs)
    if (car.value === null || !c.vehicles.some((v) => v.id === car.value)) car.value = c.vehicles[0]?.id ?? null
  } catch (e) {
    if (e instanceof ApiError && e.status === 404) notFound.value = true
    else if (!silent) error.value = errorMessage(e)
  }
}

let timer: ReturnType<typeof setInterval> | undefined
watch(
  () => data.value?.status,
  (s) => {
    clearInterval(timer)
    if (s === 'running') timer = setInterval(() => load(true), 3000)
  },
)
onMounted(() => load())
onBeforeUnmount(() => clearInterval(timer))

const colorOf = (cfgId: number) => {
  const i = data.value?.configs.findIndex((c) => c.id === cfgId) ?? 0
  return COLORS[(i < 0 ? 0 : i) % COLORS.length]
}
const cfg = (cfgId: number) => data.value?.configs.find((c) => c.id === cfgId)

// ---------- cartes de réglages ----------
interface Card {
  shown: boolean
  method: string
  quality: string
  prompt: string
  saved: string
}
const cards = reactive<Record<number, Card>>({})
function initCards(list: AiConfig[]) {
  for (const c of list) {
    const base = { method: c.method, quality: c.quality, prompt: c.prompt ?? '' }
    cards[c.id] = { shown: cards[c.id]?.shown ?? true, ...base, saved: JSON.stringify(base) }
  }
}
const cardDirty = (cid: number) => {
  const c = cards[cid]
  return !!c && JSON.stringify({ method: c.method, quality: c.quality, prompt: c.prompt }) !== c.saved
}
const anyDirty = computed(() => (data.value?.configs ?? []).some((c) => cardDirty(c.id)))
const shownConfigs = computed(() => (data.value?.configs ?? []).filter((c) => cards[c.id]?.shown !== false))

async function saveCards(): Promise<boolean> {
  for (const c of data.value?.configs ?? []) {
    if (!cardDirty(c.id)) continue
    const k = cards[c.id]
    try {
      const updated = await patch<AiConfig>(`/admin/ai-configs/${c.id}`, { method: k.method, quality: k.quality, prompt: k.prompt })
      Object.assign(c, updated)
      k.saved = JSON.stringify({ method: k.method, quality: k.quality, prompt: k.prompt })
    } catch (e) {
      toast(`${c.name} : ${errorMessage(e)}`, 'error')
      return false
    }
  }
  return true
}

const running = ref(false)
async function rerun() {
  running.value = true
  try {
    if (!(await saveCards())) return
    const c = await post<Comparison>(`/admin/comparisons/${id}/run`)
    data.value = c
    toast('Test relancé. Les nouvelles photos arrivent au fur et à mesure.')
  } catch (e) {
    toast(errorMessage(e), 'error')
  } finally {
    running.value = false
  }
}

// ---------- grille ----------
const car = ref<number | null>(null)
const order = (k: string) => {
  const i = SHOTS.findIndex((s) => s.key === k)
  return i < 0 ? 99 : i
}
const rows = computed(() => {
  if (!data.value || car.value === null) return []
  const shots = data.value.grid[String(car.value)] ?? {}
  return Object.entries(shots)
    .sort(([a], [b]) => order(a) - order(b))
    .map(([key, cell]) => ({ key, label: SHOTS.some((s) => s.key === key) ? shotLabel(key) : 'Photo', source: cell.source_url, renders: cell.renders }))
})

// ---------- photos en grand : pour chaque angle, la photo du parking puis le rendu de chaque modèle ----------
const viewerItems = computed(() => {
  const items: (ViewerItem & { key: string })[] = []
  for (const r of rows.value) {
    if (r.source) items.push({ key: `${r.key}-src`, url: r.source, label: `${r.label} · photo du parking` })
    for (const c of shownConfigs.value) {
      const x = r.renders[c.id]
      if (x?.url && !isPending(x)) items.push({ key: `${r.key}-${c.id}`, url: x.url, label: `${r.label} · ${c.name}`, sub: [duration(x.duration_ms), usd(x.cost_usd, 3)].filter(Boolean).join(' · ') })
    }
  }
  return items
})
const viewerAt = ref<number | null>(null)
function zoom(key: string) {
  const i = viewerItems.value.findIndex((x) => x.key === key)
  viewerAt.value = i >= 0 ? i : 0
}

const totals = computed(() => {
  let total = 0
  let rated = 0
  for (const shots of Object.values(data.value?.grid ?? {}))
    for (const cell of Object.values(shots))
      for (const r of Object.values(cell.renders))
        if (r) {
          total++
          if (r.rating) rated++
        }
  return { total, rated }
})
const photoCount = computed(() => Object.values(data.value?.grid ?? {}).reduce((t, s) => t + Object.keys(s).length, 0))
const estCost = computed(() => photoCount.value * shownConfigs.value.reduce((t, c) => t + Number(c.unit_cost_usd), 0))

let summaryTimer: ReturnType<typeof setTimeout> | undefined
async function rate(r: Render, rating: RenderRating) {
  const next = r.rating === rating ? null : rating
  const before = r.rating
  r.rating = next
  inflight.set(r.id, next)
  try {
    const res = await post<Render>(`/renders/${r.id}/rate`, { rating: next })
    r.rating = res.rating
  } catch (e) {
    r.rating = before
    toast(errorMessage(e), 'error')
  } finally {
    inflight.delete(r.id)
  }
  clearTimeout(summaryTimer)
  summaryTimer = setTimeout(() => load(true), 700)
}

const RATE: { v: RenderRating; label: string; fg: string; bg: string }[] = [
  { v: 'publishable', label: 'Publiable', fg: '#1E7A50', bg: '#E9F5EE' },
  { v: 'redo', label: 'À refaire', fg: '#A55A0B', bg: '#FCF1E3' },
  { v: 'unusable', label: 'Inutilisable', fg: '#C0392B', bg: '#FBECEB' },
]

const best = computed(() => {
  const s = (data.value?.summary ?? []).filter((x) => x.publishable_pct !== null)
  if (!s.length) return null
  return s.reduce((a, b) => ((b.publishable_pct ?? 0) > (a.publishable_pct ?? 0) ? b : a)).ai_config_id
})

function exportCsv() {
  if (!data.value) return
  const head = ['Réglage', 'Méthode', 'Qualité', 'Notées', 'Publiables', 'À refaire', 'Inutilisables', '% publiables', 'Coût par voiture ($)', 'Temps moyen (ms)']
  const lines = data.value.summary.map((s) => {
    const c = cfg(s.ai_config_id)
    return [c?.name, c?.method, c?.quality, s.rated, s.publishable, s.redo, s.unusable, s.publishable_pct ?? '', s.cost_per_vehicle_usd ?? '', s.avg_duration_ms ?? '']
      .map((x) => `"${String(x ?? '').replace(/"/g, '""')}"`)
      .join(';')
  })
  const blob = new Blob(['﻿' + [head.join(';'), ...lines].join('\n')], { type: 'text/csv;charset=utf-8' })
  const a = document.createElement('a')
  a.href = URL.createObjectURL(blob)
  a.download = `comparatif-${id}.csv`
  a.click()
  setTimeout(() => URL.revokeObjectURL(a.href), 2000)
}

const garageName = computed(() => session.garages.find((g) => g.id === data.value?.garage_id)?.name)
const isPending = (r: Render | null) => !!r && (r.status === 'pending' || r.status === 'processing')
</script>

<template>
  <div v-if="notFound" class="empty" style="min-height: 50vh; justify-content: center">
    <strong>Ce test n'existe pas</strong>
    <RouterLink to="/mending/comparisons" class="btn btn-primary">Retour aux tests</RouterLink>
  </div>
  <template v-else>
    <div class="page-head">
      <div class="col" style="gap: 4px">
        <RouterLink to="/mending/comparisons" class="back">‹ Tous les tests</RouterLink>
        <h1>Comparer les modèles</h1>
        <p class="lead" style="margin: 0">
          Mêmes photos, plusieurs modèles, notées côte à côte.
          <template v-if="data">Test « {{ data.name }} »{{ garageName ? `, ${garageName}` : '' }}, {{ data.vehicles.length }} voiture{{ data.vehicles.length > 1 ? 's' : '' }}.</template>
        </p>
      </div>
      <div class="row">
        <button type="button" class="btn" :disabled="!data" @click="exportCsv">Exporter les résultats</button>
        <RouterLink to="/mending/comparisons" class="btn btn-primary">Nouveau test</RouterLink>
      </div>
    </div>

    <p v-if="error" class="alert alert-error" role="alert">{{ error }} <button type="button" class="btn-link" @click="(error = ''), load()">Réessayer</button></p>
    <p v-if="data?.status === 'running'" class="alert alert-info" role="status">
      <span class="spinner" aria-hidden="true" style="vertical-align: -3px; margin-right: 8px" />Photos en cours de génération, la page se met à jour toute seule.
    </p>

    <section aria-labelledby="score" class="card">
      <div class="row between" style="align-items: baseline">
        <h2 id="score" style="font-size: 18px">Résultats</h2>
        <span class="muted" style="font-size: 13px">{{ totals.rated }} photo{{ totals.rated > 1 ? 's' : '' }} notée{{ totals.rated > 1 ? 's' : '' }} sur {{ totals.total }}</span>
      </div>
      <div style="overflow-x: auto">
        <table class="t" style="min-width: 760px">
          <thead><tr><th>Modèle</th><th>Réglage</th><th>Publiables</th><th>À refaire</th><th>Inutilisables</th><th>Coût par voiture</th><th>Temps moyen</th></tr></thead>
          <tbody v-if="data">
            <tr v-for="s in data.summary" :key="s.ai_config_id">
              <td>
                <div class="row" style="flex-wrap: nowrap; gap: 10px">
                  <span class="dot" :style="{ background: colorOf(s.ai_config_id) }" />
                  <span style="font-weight: 500">{{ cfg(s.ai_config_id)?.name }}</span>
                  <span v-if="best === s.ai_config_id && (s.publishable_pct ?? 0) > 0" class="pill" style="font-size: 11px; padding: 2px 8px; background: #E9F5EE; color: #1E7A50">En tête</span>
                </div>
              </td>
              <td style="color: var(--ink-2)">{{ cfg(s.ai_config_id)?.method }} · {{ cfg(s.ai_config_id)?.quality }}</td>
              <td>
                <div class="row" style="flex-wrap: nowrap">
                  <div class="bar"><div :style="{ width: (s.publishable_pct ?? 0) + '%' }" /></div>
                  <span style="font-weight: 500">{{ s.publishable_pct === null ? '–' : `${s.publishable_pct} %` }}</span>
                </div>
              </td>
              <td>{{ s.redo }}</td>
              <td>{{ s.unusable }}</td>
              <td style="font-weight: 500">{{ usd(s.cost_per_vehicle_usd) }}</td>
              <td>{{ duration(s.avg_duration_ms) }}</td>
            </tr>
          </tbody>
          <tbody v-else>
            <tr v-for="i in 3" :key="i"><td colspan="7"><span class="skel" style="display: block; height: 20px" /></td></tr>
          </tbody>
        </table>
      </div>
      <span class="muted" style="font-size: 13px">Objectif production : 80 % de photos publiables et moins de 0,50 $ par voiture, refaites comprises. Prix indicatifs à revérifier chez chaque fournisseur.</span>
    </section>

    <section v-if="data" aria-labelledby="models" class="col" style="gap: 12px">
      <h2 id="models" style="font-size: 18px">Modèles comparés</h2>
      <div class="cards">
        <div v-for="c in data.configs" :key="c.id" class="mcard" :style="{ borderColor: cards[c.id]?.shown ? colorOf(c.id) : '#E3E6EC', opacity: cards[c.id]?.shown ? 1 : 0.6 }">
          <label class="row" style="gap: 10px; font-size: 16px; font-weight: 500; cursor: pointer; flex-wrap: nowrap">
            <input v-model="cards[c.id].shown" type="checkbox" style="width: 20px; height: 20px; margin: 0" />
            <span class="dot" :style="{ background: colorOf(c.id) }" />
            {{ c.name }}
            <span class="sr-only">(afficher dans la grille)</span>
          </label>
          <div class="two">
            <label class="lbl" style="font-size: 12px">
              Méthode
              <select v-model="cards[c.id].method" class="f f-sm">
                <option v-for="m in METHODS" :key="m.value" :value="m.value">{{ m.label }}</option>
              </select>
            </label>
            <label class="lbl" style="font-size: 12px">
              Qualité
              <select v-model="cards[c.id].quality" class="f f-sm">
                <option value="2K">2K</option>
                <option value="1K">1K</option>
              </select>
            </label>
          </div>
          <label class="lbl" style="font-size: 12px">Consigne envoyée au modèle<textarea v-model="cards[c.id].prompt" class="f" rows="3" style="font-size: 13px; line-height: 1.4" /></label>
          <div class="row between" style="font-size: 13px; color: var(--ink-2)">
            <span>Prix indicatif</span><span class="mono" style="font-size: 13px">{{ usd(c.unit_cost_usd, 3) }} / image</span>
          </div>
          <span v-if="cardDirty(c.id)" style="font-size: 12px; color: #A55A0B; font-weight: 500">Modifié : sera enregistré au prochain lancement</span>
        </div>
      </div>
      <div class="est">
        <span style="font-size: 14px">
          {{ data.vehicles.length }} voiture{{ data.vehicles.length > 1 ? 's' : '' }} × {{ photoCount }} vues au total × {{ shownConfigs.length }} modèle{{ shownConfigs.length > 1 ? 's' : '' }} =
          <strong>{{ photoCount * shownConfigs.length }} photos</strong>, environ <strong>{{ usd(estCost) }}</strong>
        </span>
        <button type="button" class="btn btn-primary" :disabled="running || data.status === 'running'" @click="rerun">
          <span v-if="running" class="spinner" aria-hidden="true" />{{ anyDirty ? 'Relancer le test avec ces réglages' : 'Relancer le test' }}
        </button>
      </div>
    </section>

    <section v-if="data" aria-labelledby="grid" class="col" style="gap: 12px">
      <div class="row between">
        <h2 id="grid" style="font-size: 18px">Noter les photos</h2>
        <div role="tablist" aria-label="Voiture" class="row" style="gap: 8px">
          <button v-for="v in data.vehicles" :key="v.id" type="button" role="tab" class="ptab" :aria-selected="car === v.id" :title="v.label" @click="car = v.id">{{ v.plate }}</button>
        </div>
      </div>
      <div style="overflow-x: auto">
        <div class="col" :style="{ gap: '12px', minWidth: `${140 + shownConfigs.length * 220}px` }">
          <div v-if="!rows.length" class="panel empty">Pas de photo extérieure pour cette voiture.</div>
          <div v-for="r in rows" :key="r.key" class="grow-row" :style="{ gridTemplateColumns: `120px repeat(${Math.max(shownConfigs.length, 1)}, minmax(0, 1fr))` }">
            <div class="col" style="gap: 6px; padding-top: 4px">
              <span style="font-size: 14px; font-weight: 500">{{ r.label }}</span>
              <button v-if="r.source" type="button" class="zoom" aria-label="Voir la photo du parking en grand" @click="zoom(`${r.key}-src`)"><img :src="r.source" alt="Photo parking" class="src" /></button>
              <div v-else class="src ph" />
              <span class="muted" style="font-size: 11px">Photo parking</span>
            </div>
            <div v-for="c in shownConfigs" :key="c.id" class="cell" :style="{ borderTopColor: colorOf(c.id) }">
              <template v-if="r.renders[c.id]">
                <button v-if="r.renders[c.id]!.url && !isPending(r.renders[c.id])" type="button" class="zoom" :aria-label="`Voir le rendu ${c.name} en grand`" @click="zoom(`${r.key}-${c.id}`)">
                  <img :src="r.renders[c.id]!.url!" :alt="`Rendu ${c.name}, ${r.label}`" class="rimg" loading="lazy" />
                </button>
                <div v-else-if="isPending(r.renders[c.id])" class="rimg wait"><span class="spinner" aria-hidden="true" /> En cours…</div>
                <div v-else class="rimg wait ko">{{ r.renders[c.id]!.error || 'Échec' }}</div>
                <div class="row between" style="font-size: 12px; color: var(--muted)">
                  <span>{{ duration(r.renders[c.id]!.duration_ms) }}</span><span class="mono">{{ usd(r.renders[c.id]!.cost_usd, 3) }}</span>
                </div>
                <div role="radiogroup" :aria-label="`Note pour ${c.name}, ${r.label}`" class="rates">
                  <button
                    v-for="b in RATE"
                    :key="b.v"
                    type="button"
                    role="radio"
                    :aria-checked="r.renders[c.id]!.rating === b.v"
                    :disabled="isPending(r.renders[c.id])"
                    :style="r.renders[c.id]!.rating === b.v ? { borderColor: b.fg, background: b.bg, color: b.fg } : undefined"
                    @click="rate(r.renders[c.id]!, b.v)"
                  >
                    {{ b.label }}
                  </button>
                </div>
              </template>
              <div v-else class="rimg wait">Pas encore lancé</div>
            </div>
          </div>
        </div>
      </div>
      <span class="muted" style="font-size: 13px">À regarder en priorité : jantes, logos, reflets et vitres. Une photo où la voiture a changé est inutilisable.</span>
    </section>
  </template>
  <ImageViewer v-if="viewerAt !== null" :items="viewerItems" :start="viewerAt" @close="viewerAt = null" />
</template>

<style scoped>
.zoom {
  padding: 0;
  border: 0;
  background: none;
  cursor: zoom-in;
  display: block;
  width: 100%;
}
.zoom:focus-visible {
  outline: 2px solid var(--accent);
  outline-offset: 2px;
}
.dot {
  width: 12px;
  height: 12px;
  border-radius: 3px;
  flex-shrink: 0;
  display: inline-block;
}
.bar {
  width: 90px;
  height: 8px;
  background: #F1F3F6;
  border-radius: 4px;
}
.bar div {
  height: 8px;
  background: var(--green);
  border-radius: 4px;
}
.cards {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 14px;
}
.mcard {
  background: #fff;
  border: 2px solid;
  border-radius: 14px;
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.two {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px;
}
.est {
  background: #fff;
  border: 1px solid var(--line);
  border-radius: 14px;
  padding: 16px;
  display: flex;
  flex-wrap: wrap;
  gap: 16px;
  align-items: center;
  justify-content: space-between;
}
.ptab {
  font-family: var(--mono);
  font-size: 13px;
  font-weight: 500;
  min-height: 40px;
  padding: 0 14px;
  border-radius: 999px;
  border: 1px solid var(--plate-line);
  background: #fff;
  color: var(--ink-2);
}
.ptab[aria-selected='true'] {
  background: var(--accent-soft);
  border-color: var(--accent-line);
  color: var(--accent-ink);
}
.grow-row {
  display: grid;
  gap: 10px;
  align-items: start;
}
.src {
  width: 100%;
  aspect-ratio: 4/3;
  border-radius: 6px;
  object-fit: cover;
}
.cell {
  background: #fff;
  border: 1px solid var(--line);
  border-top: 4px solid;
  border-radius: 10px;
  padding: 8px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.rimg {
  width: 100%;
  aspect-ratio: 4/3;
  border-radius: 6px;
  object-fit: cover;
  background: #EEF3F9;
}
.wait {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  font-size: 12px;
  color: var(--accent-ink);
  text-align: center;
  padding: 6px;
}
.wait.ko {
  background: var(--red-soft);
  color: var(--red-ink);
}
.rates {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 4px;
}
.rates button {
  font-size: 12px;
  font-weight: 500;
  min-height: 44px;
  border-radius: 6px;
  border: 1px solid var(--field-2);
  background: #fff;
  color: var(--ink-2);
  padding: 0 2px;
}
</style>
