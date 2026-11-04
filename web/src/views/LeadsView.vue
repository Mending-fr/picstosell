<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { get, patch, errorMessage } from '../api'
import { relative, euros, num, days, fuelLabel, STATUS, LEAD_TYPES } from '../labels'
import { session, toast } from '../session'
import type { Lead } from '../types'

const route = useRoute()
const router = useRouter()
const leads = ref<Lead[] | null>(null)
const error = ref('')
const filter = ref<'all' | 'new' | 'done'>('all')
const selId = ref<number | null>(route.query.id ? Number(route.query.id) : null)
const note = ref('')
const noteState = ref<'' | 'saving' | 'saved' | 'error'>('')
const detail = ref<HTMLElement | null>(null)

async function load() {
  error.value = ''
  try {
    const r = await get<{ data: Lead[]; new_count: number }>('/leads')
    leads.value = r.data
    session.newLeads = r.new_count
    if (!selId.value || !r.data.some((l) => l.id === selId.value)) selId.value = r.data[0]?.id ?? null
  } catch (e) {
    error.value = errorMessage(e)
  }
}
onMounted(load)

const shown = computed(() => (leads.value ?? []).filter((l) => filter.value === 'all' || l.status === filter.value))
const cur = computed(() => leads.value?.find((l) => l.id === selId.value) ?? null)
const newCount = computed(() => (leads.value ?? []).filter((l) => l.status === 'new').length)

watch(
  cur,
  (l, old) => {
    if (l && l.id !== old?.id) {
      note.value = l.note ?? ''
      noteState.value = ''
    }
  },
  { immediate: true },
)

function pick(l: Lead) {
  selId.value = l.id
  router.replace({ query: { id: String(l.id) } })
  if (window.matchMedia('(max-width: 860px)').matches) detail.value?.scrollIntoView({ behavior: 'smooth' })
}

function subject(l: Lead) {
  if (l.vehicle) return l.vehicle.label
  if (l.type === 'trade_in') return l.trade_in?.plate ? `Reprise ${l.trade_in.plate}` : 'Demande de reprise'
  return LEAD_TYPES[l.type] ?? 'Demande'
}
function source(l: Lead) {
  if (l.source === 'fiche' && l.vehicle) return `la fiche ${l.vehicle.label}`
  if (l.source === 'contact') return 'le formulaire de contact'
  if (l.source === 'reprise') return 'le formulaire de reprise'
  return l.source ? `« ${l.source} »` : 'votre site'
}
function telHref(p: string) {
  return `tel:${p.replace(/[^\d+]/g, '')}`
}

async function saveNote() {
  const l = cur.value
  if (!l || (l.note ?? '') === note.value.trim()) return
  noteState.value = 'saving'
  try {
    const r = await patch<Lead>(`/leads/${l.id}`, { note: note.value.trim() || null })
    l.note = r.note
    noteState.value = 'saved'
  } catch (e) {
    noteState.value = 'error'
    toast(errorMessage(e), 'error')
  }
}

const KIND: Record<string, string> = { vehicle: 'Une voiture', trade_in: 'Reprise', other: 'Autre demande' }

/** Les autres demandes sur la même voiture, pour le bloc « Sur cette voiture ». */
const sameCar = computed(() => {
  const c = cur.value
  if (!c?.vehicle) return []
  return (leads.value ?? []).filter((l) => l.id !== c.id && l.vehicle?.id === c.vehicle!.id)
})
const carFacts = computed(() => {
  const v = cur.value?.vehicle
  if (!v) return ''
  return [v.year, v.mileage != null ? `${num(v.mileage)} km` : null, fuelLabel(v.fuel ?? null) || null].filter(Boolean).join(' · ')
})
const onlineFor = computed(() => {
  const p = cur.value?.vehicle?.published_at
  if (!p || cur.value?.vehicle?.status !== 'online') return ''
  const n = Math.max(0, Math.floor((Date.now() - new Date(p).getTime()) / 86400000))
  return n === 0 ? "aujourd'hui" : days(n)
})

async function setStatus(l: Lead, status: 'new' | 'done') {
  const before = l.status
  l.status = status
  session.newLeads = newCount.value
  try {
    await patch<Lead>(`/leads/${l.id}`, { status, ...(note.value.trim() !== (l.note ?? '') ? { note: note.value.trim() || null } : {}) })
    if (status === 'done') {
      toast('Demande marquée comme traitée.')
      const next = (leads.value ?? []).find((x) => x.status === 'new')
      if (next && filter.value !== 'done') selId.value = next.id
    }
  } catch (e) {
    l.status = before
    session.newLeads = newCount.value
    toast(errorMessage(e), 'error')
  }
}
</script>

<template>
  <div class="page-head">
    <h1>Demandes</h1>
    <div role="tablist" aria-label="Filtrer" class="tabs grow">
      <button type="button" role="tab" class="tab" :aria-selected="filter === 'all'" @click="filter = 'all'">Toutes<span class="n">{{ leads?.length ?? '' }}</span></button>
      <button type="button" role="tab" class="tab" :aria-selected="filter === 'new'" @click="filter = 'new'">Nouvelles<span class="n">{{ leads ? newCount : '' }}</span></button>
      <button type="button" role="tab" class="tab" :aria-selected="filter === 'done'" @click="filter = 'done'">Traitées</button>
    </div>
    <span class="muted hint">Les messages laissés sur votre site, du plus récent au plus ancien</span>
  </div>

  <p v-if="error" class="alert alert-error" role="alert">{{ error }} <button type="button" class="btn-link" @click="load">Réessayer</button></p>

  <div v-if="leads && !leads.length" class="panel empty">
    <strong>Aucune demande pour l'instant</strong>
    <span>Quand un client remplit un formulaire sur votre site, sa demande arrive ici.</span>
    <a v-if="session.garage?.site_url" :href="session.garage.site_url" target="_blank" rel="noopener" class="btn">Voir mon site</a>
  </div>

  <div v-else class="cols">
    <div role="listbox" aria-label="Demandes" class="list panel">
      <template v-if="!leads">
        <div v-for="i in 4" :key="i" class="item"><span class="skel" style="width: 60%; height: 16px" /><span class="skel" style="width: 80%; height: 13px" /></div>
      </template>
      <button
        v-for="l in shown"
        :key="l.id"
        type="button"
        role="option"
        class="item"
        :class="{ on: l.id === selId, unread: l.status === 'new' }"
        :aria-selected="l.id === selId"
        @click="pick(l)"
      >
        <span class="dot" :aria-label="l.status === 'new' ? 'Nouvelle' : undefined" />
        <span class="it">
          <span class="it-top">
            <span class="name">{{ l.name }}</span>
            <span class="when">{{ relative(l.created_at) }}</span>
          </span>
          <span class="it-sub">{{ KIND[l.type] ?? 'Demande' }} · {{ subject(l) }}</span>
          <span v-if="l.message" class="it-msg">{{ l.message }}</span>
        </span>
      </button>
      <div v-if="leads && !shown.length" class="empty" style="padding: 24px">Aucune demande dans cette liste.</div>
    </div>

    <section ref="detail" aria-label="Détail de la demande" class="detail panel">
      <template v-if="cur">
        <div class="row between" style="align-items: flex-start; flex-wrap: nowrap">
          <div class="col" style="gap: 4px; min-width: 0">
            <h2 style="font-size: 22px">{{ cur.name }}</h2>
            <span class="muted" style="font-size: 13px">{{ KIND[cur.type] ?? 'Demande' }} · {{ relative(cur.created_at) }} · depuis {{ source(cur) }}</span>
          </div>
          <span class="pill" :style="cur.status === 'new' ? { color: 'var(--accent-ink)', background: 'var(--accent-soft)' } : { color: 'var(--green-ink)', background: 'var(--green-tint)' }">{{ cur.status === 'new' ? 'Nouvelle' : 'Traitée' }}</span>
        </div>

        <div class="acts">
          <a v-if="cur.phone" :href="telHref(cur.phone)" class="btn btn-primary btn-lg">Appeler {{ cur.phone }}</a>
          <a v-if="cur.email" :href="`mailto:${cur.email}`" class="btn btn-lg">Écrire un e-mail</a>
          <span class="grow" />
          <button v-if="cur.status === 'new'" type="button" class="btn btn-green btn-lg" @click="setStatus(cur, 'done')">Marquer comme traitée</button>
          <button v-else type="button" class="btn btn-lg" @click="setStatus(cur, 'new')">Remettre en nouvelle</button>
        </div>

        <div class="msg">
          <span class="k">Son message</span>
          <p>{{ cur.message || 'Pas de message, rappelez simplement le client.' }}</p>
        </div>

        <div class="facts">
          <div class="fact">
            <span class="k">Téléphone</span>
            <a v-if="cur.phone" :href="telHref(cur.phone)" class="v">{{ cur.phone }}</a>
            <span v-else class="v muted">Non indiqué</span>
          </div>
          <div class="fact">
            <span class="k">E-mail</span>
            <a v-if="cur.email" :href="`mailto:${cur.email}`" class="v" style="word-break: break-all">{{ cur.email }}</a>
            <span v-else class="v muted">Non indiqué</span>
          </div>
          <div class="fact">
            <span class="k">Reprise</span>
            <span class="v">{{ cur.trade_in ? [cur.trade_in.plate, cur.trade_in.mileage ? `${num(cur.trade_in.mileage)} km` : ''].filter(Boolean).join(', ') : 'Non' }}</span>
          </div>
        </div>

        <label class="lbl note">
          Note pour l'équipe
          <textarea v-model="note" class="f" rows="3" placeholder="Ex. rappelé, essai prévu samedi 10 h" @blur="saveNote" />
          <span class="muted" style="font-weight: 400; font-size: 12px" aria-live="polite">
            {{ noteState === 'saving' ? 'Enregistrement…' : noteState === 'saved' ? 'Note enregistrée' : noteState === 'error' ? 'La note n\'a pas été enregistrée' : 'Enregistrée toute seule quand vous quittez le champ' }}
          </span>
        </label>
      </template>
      <template v-else-if="!leads">
        <span class="skel" style="width: 40%; height: 26px" />
        <span class="skel" style="height: 80px" />
      </template>
      <div v-else class="empty">Choisissez une demande dans la liste.</div>
    </section>

    <aside aria-label="La voiture demandée" class="car">
      <template v-if="cur?.vehicle">
        <section class="panel car-card">
          <img v-if="cur.vehicle.cover_url" :src="cur.vehicle.cover_url" alt="" class="car-img" />
          <span v-else class="car-img ph" />
          <div class="car-t">
            <span class="row between" style="flex-wrap: nowrap; align-items: baseline">
              <span class="car-name">{{ cur.vehicle.label }}</span>
              <span class="car-name">{{ euros(cur.vehicle.price) }}</span>
            </span>
            <span v-if="carFacts" class="muted" style="font-size: 13px">{{ carFacts }}</span>
          </div>
          <div class="row" style="gap: 8px">
            <span v-if="cur.vehicle.plate" class="plate">{{ cur.vehicle.plate }}</span>
            <span v-if="onlineFor" class="pill" style="color: var(--accent-ink); background: var(--accent-soft)">{{ onlineFor === "aujourd'hui" ? "En ligne depuis aujourd'hui" : `En ligne depuis ${onlineFor}` }}</span>
            <span v-else-if="cur.vehicle.status" class="pill" style="color: var(--slate-ink); background: var(--slate-tint)">{{ STATUS[cur.vehicle.status]?.label }}</span>
          </div>
          <RouterLink :to="`/vehicles/${cur.vehicle.id}`" class="car-link">Ouvrir la fiche de la voiture ›</RouterLink>
        </section>
        <section class="panel same">
          <span class="same-t">Sur cette voiture</span>
          <span v-if="!sameCar.length" class="muted" style="font-size: 13px">Première demande sur cette voiture.</span>
          <template v-else>
            <span class="muted" style="font-size: 13px">{{ sameCar.length + 1 }} demandes en tout.</span>
            <button v-for="o in sameCar" :key="o.id" type="button" class="same-it" @click="pick(o)">
              <span>{{ o.name }}, {{ relative(o.created_at) }}</span>
              <span class="muted">{{ o.status === 'new' ? 'Nouvelle' : 'Traitée' }}</span>
            </button>
          </template>
        </section>
      </template>
      <section v-else-if="cur" class="panel same">
        <span class="same-t">Pas de voiture précise</span>
        <span class="muted" style="font-size: 13px">Le client a écrit depuis {{ source(cur) }}.</span>
      </section>
    </aside>
  </div>
</template>

<style scoped>
.page-head {
  flex-wrap: nowrap;
  align-items: center;
}
.page-head .tabs {
  margin-left: 12px;
  gap: 6px;
}
.page-head .tab {
  min-height: 36px;
  padding: 0 14px;
  font-size: 13.5px;
}
.hint {
  font-size: 13px;
  white-space: nowrap;
}
.cols {
  display: grid;
  grid-template-columns: 360px minmax(0, 1fr) 340px;
  gap: 18px;
  align-items: start;
}
.panel {
  background: #fff;
  border: 1px solid #e6e7ea;
  border-radius: 16px;
}
.list {
  padding: 6px;
  display: flex;
  flex-direction: column;
  gap: 2px;
  max-height: calc(100vh - 64px - 28px - 56px - 32px - 20px);
  overflow-y: auto;
}
.item {
  width: 100%;
  text-align: left;
  border: 0;
  border-radius: 12px;
  background: transparent;
  padding: 12px 14px;
  display: flex;
  gap: 12px;
  align-items: flex-start;
  color: var(--ink);
}
.item:hover {
  background: var(--ground);
}
.item.on {
  background: var(--accent-tint);
}
.dot {
  width: 8px;
  height: 8px;
  border-radius: 4px;
  margin-top: 6px;
  flex-shrink: 0;
}
.unread .dot {
  background: var(--accent);
}
.it {
  display: flex;
  flex-direction: column;
  gap: 3px;
  flex: 1;
  min-width: 0;
}
.it-top {
  display: flex;
  justify-content: space-between;
  gap: 8px;
}
.name {
  font-size: 14px;
  font-weight: 500;
}
.unread .name {
  font-weight: 600;
}
.when {
  font-size: 12px;
  color: var(--muted-3);
  white-space: nowrap;
}
.it-sub {
  font-size: 13px;
  color: var(--ink-2);
}
.it-msg {
  font-size: 13px;
  color: var(--muted-3);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.detail {
  padding: 22px 26px;
  display: flex;
  flex-direction: column;
  gap: 18px;
  min-width: 0;
}
.acts {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
  align-items: center;
}
.msg {
  background: #f7f7f5;
  border-radius: 14px;
  padding: 16px 18px;
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.msg p {
  margin: 0;
  font-size: 15px;
  line-height: 1.55;
  white-space: pre-line;
}
.facts {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 12px;
}
.fact {
  display: flex;
  flex-direction: column;
  gap: 3px;
  min-width: 0;
}
.k {
  font-size: 12px;
  font-weight: 500;
  color: var(--muted-3);
}
.v {
  font-size: 14px;
  font-weight: 500;
}
a.v {
  text-decoration: none;
}
.car {
  display: flex;
  flex-direction: column;
  gap: 18px;
  min-width: 0;
}
.car-card {
  padding: 14px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.car-img {
  width: 100%;
  aspect-ratio: 16 / 10;
  object-fit: cover;
  border-radius: 10px;
  display: block;
}
.car-t {
  display: flex;
  flex-direction: column;
  gap: 3px;
  padding: 0 4px;
}
.car-card > .row {
  padding: 0 4px;
}
.car-name {
  font-size: 16px;
  font-weight: 500;
}
.car-link {
  font-size: 14px;
  font-weight: 500;
  text-decoration: none;
  padding: 0 4px 4px;
}
.same {
  padding: 16px 18px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.same-t {
  font-size: 14px;
  font-weight: 500;
}
.same-it {
  font: inherit;
  font-size: 13px;
  text-align: left;
  background: none;
  border: 0;
  border-top: 1px solid var(--line-2);
  padding: 8px 0 0;
  display: flex;
  justify-content: space-between;
  gap: 8px;
  color: var(--ink);
}
.same-it:hover span:first-child {
  text-decoration: underline;
}
@media (max-width: 1280px) {
  .cols {
    grid-template-columns: 320px minmax(0, 1fr);
  }
  .car {
    grid-column: 2;
  }
  .hint {
    display: none;
  }
}
@media (max-width: 860px) {
  .page-head {
    flex-wrap: wrap;
  }
  .page-head .tabs {
    margin-left: 0;
  }
  .cols {
    grid-template-columns: minmax(0, 1fr);
  }
  .car {
    grid-column: auto;
  }
  .list {
    max-height: none;
  }
  .detail {
    padding: 16px;
  }
  .facts {
    grid-template-columns: minmax(0, 1fr);
  }
}
</style>
