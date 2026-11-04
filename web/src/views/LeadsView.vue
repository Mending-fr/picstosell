<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { get, patch, errorMessage } from '../api'
import { relative, euros, num, LEAD_TYPES } from '../labels'
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
  <div class="col" style="gap: 4px">
    <h1>Demandes reçues</h1>
    <p class="lead" style="margin: 0">Les messages laissés sur votre site, du plus récent au plus ancien.</p>
  </div>

  <div role="tablist" aria-label="Filtrer" class="tabs">
    <button type="button" role="tab" class="tab" :aria-selected="filter === 'all'" @click="filter = 'all'">Toutes<span class="n">{{ leads?.length ?? '' }}</span></button>
    <button type="button" role="tab" class="tab" :aria-selected="filter === 'new'" @click="filter = 'new'">Nouvelles<span class="n">{{ leads ? newCount : '' }}</span></button>
    <button type="button" role="tab" class="tab" :aria-selected="filter === 'done'" @click="filter = 'done'">Traitées</button>
  </div>

  <p v-if="error" class="alert alert-error" role="alert">{{ error }} <button type="button" class="btn-link" @click="load">Réessayer</button></p>

  <div v-if="leads && !leads.length" class="panel empty">
    <strong>Aucune demande pour l'instant</strong>
    <span>Quand un client remplit un formulaire sur votre site, sa demande arrive ici.</span>
    <a v-if="session.garage?.site_url" :href="session.garage.site_url" target="_blank" rel="noopener" class="btn">Voir mon site</a>
  </div>

  <div v-else class="split">
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
        <span class="row between" style="flex-wrap: nowrap">
          <span class="name">{{ l.name }}</span>
          <span class="muted" style="font-size: 12px; white-space: nowrap">{{ relative(l.created_at) }}</span>
        </span>
        <span style="font-size: 13px; color: var(--ink-2)">{{ subject(l) }}</span>
        <span class="state" :style="{ color: l.status === 'new' ? '#B45309' : '#5B606B' }">{{ l.status === 'new' ? 'Nouvelle' : 'Traitée' }}</span>
      </button>
      <div v-if="leads && !shown.length" class="empty" style="padding: 24px">Aucune demande dans cette liste.</div>
    </div>

    <section ref="detail" aria-label="Détail de la demande" class="detail panel">
      <template v-if="cur">
        <div class="row between" style="align-items: flex-start">
          <div class="col" style="gap: 4px">
            <h2 style="font-size: 22px">{{ cur.name }}</h2>
            <span class="muted" style="font-size: 14px">{{ relative(cur.created_at) }} · depuis {{ source(cur) }}</span>
          </div>
          <span class="pill" :style="{ color: cur.status === 'new' ? '#B45309' : '#5B606B', background: '#F5F5F2' }">{{ cur.status === 'new' ? 'Nouvelle' : 'Traitée' }}</span>
        </div>
        <div class="facts">
          <div class="fact">
            <span class="k">Téléphone</span>
            <a v-if="cur.phone" :href="telHref(cur.phone)" class="v">{{ cur.phone }}</a>
            <span v-else class="v">Non indiqué</span>
          </div>
          <div class="fact">
            <span class="k">Voiture</span>
            <RouterLink v-if="cur.vehicle" :to="`/vehicles/${cur.vehicle.id}`" class="v">{{ cur.vehicle.label }}{{ cur.vehicle.price ? `, ${euros(cur.vehicle.price)}` : '' }}</RouterLink>
            <span v-else class="v">Pas encore choisie</span>
          </div>
          <div class="fact">
            <span class="k">Reprise</span>
            <span class="v">{{ cur.trade_in ? [cur.trade_in.plate, cur.trade_in.mileage ? `${num(cur.trade_in.mileage)} km` : ''].filter(Boolean).join(', ') : 'Non' }}</span>
          </div>
          <div v-if="cur.email" class="fact">
            <span class="k">E-mail</span>
            <a :href="`mailto:${cur.email}`" class="v" style="font-size: 14px; word-break: break-all">{{ cur.email }}</a>
          </div>
        </div>
        <div class="col" style="gap: 6px">
          <span class="muted" style="font-size: 13px; font-weight: 600">Son message</span>
          <p class="msg">{{ cur.message || 'Pas de message, rappelez simplement le client.' }}</p>
        </div>
        <label class="lbl">
          Note pour l'équipe
          <textarea v-model="note" class="f" rows="2" placeholder="Ex. rappelé, essai prévu samedi 10 h" @blur="saveNote" />
          <span class="muted" style="font-weight: 400; font-size: 12px" aria-live="polite">
            {{ noteState === 'saving' ? 'Enregistrement…' : noteState === 'saved' ? 'Note enregistrée' : noteState === 'error' ? 'La note n\'a pas été enregistrée' : 'Enregistrée automatiquement quand vous quittez le champ' }}
          </span>
        </label>
        <div class="row">
          <a v-if="cur.phone" :href="telHref(cur.phone)" class="btn btn-primary btn-lg">Appeler {{ cur.phone }}</a>
          <button v-if="cur.status === 'new'" type="button" class="btn btn-lg" @click="setStatus(cur, 'done')">Marquer comme traitée</button>
          <button v-else type="button" class="btn btn-lg" @click="setStatus(cur, 'new')">Remettre en nouvelle</button>
        </div>
      </template>
      <template v-else-if="!leads">
        <span class="skel" style="width: 40%; height: 26px" />
        <span class="skel" style="height: 80px" />
      </template>
      <div v-else class="empty">Choisissez une demande dans la liste.</div>
    </section>
  </div>
</template>

<style scoped>
.split {
  display: flex;
  flex-wrap: wrap;
  gap: 16px;
  align-items: flex-start;
}
.list {
  flex: 1 1 320px;
  min-width: 0;
  overflow: hidden;
  display: flex;
  flex-direction: column;
}
.item {
  width: 100%;
  text-align: left;
  border: 0;
  border-bottom: 1px solid var(--line-2);
  border-left: 4px solid transparent;
  background: #fff;
  padding: 14px 16px;
  display: flex;
  flex-direction: column;
  gap: 4px;
  color: var(--ink);
}
.item:last-child {
  border-bottom: 0;
}
.item.on {
  background: var(--accent-soft);
  border-left-color: var(--accent);
}
.name {
  font-size: 15px;
  font-weight: 500;
}
.unread .name {
  font-weight: 700;
}
.state {
  font-size: 12px;
  font-weight: 600;
}
.detail {
  flex: 2 1 420px;
  min-width: 0;
  padding: 24px;
  display: flex;
  flex-direction: column;
  gap: 18px;
}
.facts {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 12px;
}
.fact {
  background: var(--ground);
  border-radius: 10px;
  padding: 12px;
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.k {
  font-size: 12px;
  color: var(--muted);
}
.v {
  font-size: 16px;
  font-weight: 700;
}
.msg {
  margin: 0;
  font-size: 16px;
  line-height: 1.55;
  background: #fafaf8;
  border: 1px solid var(--line-2);
  border-radius: 10px;
  padding: 14px;
  white-space: pre-line;
}
@media (max-width: 860px) {
  .detail {
    padding: 16px;
  }
}
</style>
