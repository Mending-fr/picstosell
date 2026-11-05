<script setup lang="ts">
// Une demande : le message et la note à gauche ; statut, contact et voiture à droite, comme la fiche voiture.
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ApiError, get, patch, errorMessage } from '../api'
import { relative, euros, num, days, fuelLabel, STATUS, copyText } from '../labels'
import { session, toast } from '../session'
import { LEAD_KIND, leadSource, leadSubject, telHref } from '../leadText'
import MoreMenu, { type MenuItem } from '../components/MoreMenu.vue'
import type { Lead } from '../types'

const route = useRoute()
const router = useRouter()
const id = computed(() => Number(route.params.id))
const leads = ref<Lead[] | null>(null)
const error = ref('')
const note = ref('')
const noteState = ref<'' | 'saving' | 'saved' | 'error'>('')
const saving = ref(false)

async function load() {
  error.value = ''
  try {
    const r = await get<{ data: Lead[]; new_count: number }>('/leads')
    leads.value = r.data
    session.newLeads = r.new_count
  } catch (e) {
    error.value = errorMessage(e)
  }
}
load()

const cur = computed(() => leads.value?.find((l) => l.id === id.value) ?? null)
const notFound = computed(() => !!leads.value && !cur.value)
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

/** Prochaine demande nouvelle, en suivant l'ordre de la liste. */
const nextNew = computed(() => {
  const list = leads.value ?? []
  const i = list.findIndex((l) => l.id === id.value)
  return [...list.slice(i + 1), ...list.slice(0, Math.max(i, 0))].find((l) => l.status === 'new' && l.id !== id.value) ?? null
})

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

async function setStatus(status: 'new' | 'done') {
  const l = cur.value
  if (!l || saving.value) return
  const before = l.status
  const next = nextNew.value
  l.status = status
  session.newLeads = newCount.value
  saving.value = true
  try {
    await patch<Lead>(`/leads/${l.id}`, { status, ...(note.value.trim() !== (l.note ?? '') ? { note: note.value.trim() || null } : {}) })
    if (status === 'done') {
      toast(next ? 'Demande traitée. Voici la suivante.' : 'Demande traitée. Plus aucune demande en attente.')
      if (next) router.push(`/leads/${next.id}`)
    } else toast('Demande remise en nouvelle.')
  } catch (e) {
    l.status = before
    session.newLeads = newCount.value
    toast(e instanceof ApiError ? errorMessage(e) : 'La demande n\'a pas été mise à jour.', 'error')
  } finally {
    saving.value = false
  }
}

async function copy(text: string, what: string) {
  const ok = await copyText(text)
  toast(ok ? `${what} copié.` : 'La copie a échoué.', ok ? 'ok' : 'error')
}
const moreItems = computed<MenuItem[]>(() => {
  const l = cur.value
  if (!l) return []
  return [
    ...(l.phone ? [{ label: 'Copier le téléphone', action: () => copy(l.phone, 'Téléphone') }] : []),
    ...(l.email ? [{ label: "Copier l'e-mail", action: () => copy(l.email!, 'E-mail') }] : []),
    ...(l.status === 'done' ? [{ label: 'Remettre en nouvelle', action: () => setStatus('new') }] : []),
  ]
})

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
const carState = computed(() => {
  const v = cur.value?.vehicle
  if (!v?.status) return ''
  if (v.status === 'online' && v.published_at) {
    const n = Math.max(0, Math.floor((Date.now() - new Date(v.published_at).getTime()) / 86400000))
    return n === 0 ? "En ligne depuis aujourd'hui" : `En ligne depuis ${days(n)}`
  }
  return STATUS[v.status]?.label ?? ''
})
</script>

<template>
  <div class="page">
    <div v-if="error" class="empty" style="min-height: 50vh; justify-content: center">
      <strong>Impossible d'afficher cette demande</strong>
      <span>{{ error }}</span>
      <button type="button" class="btn btn-primary" @click="load">Réessayer</button>
    </div>
    <div v-else-if="notFound" class="empty" style="min-height: 50vh; justify-content: center">
      <strong>Cette demande n'existe pas ou a été supprimée</strong>
      <RouterLink to="/leads" class="btn btn-primary">Retour aux demandes</RouterLink>
    </div>

    <template v-else>
      <header class="head">
        <RouterLink to="/leads" class="icon-btn" aria-label="Retour aux demandes">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M15 6l-6 6 6 6" /></svg>
        </RouterLink>
        <div v-if="cur" class="head-t">
          <h1 class="title">{{ cur.name }}</h1>
          <span class="pill" :class="cur.status === 'new' ? 'p-new' : 'p-done'">{{ cur.status === 'new' ? 'Nouvelle' : 'Traitée' }}</span>
        </div>
        <div v-else class="head-t"><span class="skel" style="width: 220px; height: 24px" /></div>
        <div v-if="cur" class="head-a">
          <RouterLink v-if="nextNew" :to="`/leads/${nextNew.id}`" class="btn">Suivante</RouterLink>
          <MoreMenu v-if="moreItems.length" :items="moreItems" />
        </div>
      </header>

      <div class="layout">
        <!-- colonne principale -->
        <div class="main-col">
          <section class="card o3" aria-labelledby="msg-t">
            <div class="card-h">
              <h2 id="msg-t">Message</h2>
              <span v-if="cur" class="muted small">{{ relative(cur.created_at) }}</span>
            </div>
            <template v-if="cur">
              <p class="msg">{{ cur.message || 'Pas de message. Rappelez simplement le client.' }}</p>
              <span class="muted small">{{ LEAD_KIND[cur.type] ?? 'Demande' }} · envoyée depuis {{ leadSource(cur) }}</span>
            </template>
            <span v-else class="skel" style="height: 60px" />
          </section>

          <section v-if="cur?.trade_in" class="card o4" aria-labelledby="ti-t">
            <h2 id="ti-t">Voiture à reprendre</h2>
            <dl class="facts">
              <dt>Plaque</dt>
              <dd><span class="plate">{{ cur.trade_in.plate }}</span></dd>
              <dt>Kilométrage</dt>
              <dd>{{ cur.trade_in.mileage ? `${num(cur.trade_in.mileage)} km` : 'Non indiqué' }}</dd>
            </dl>
          </section>

          <section class="card o5" aria-labelledby="no-t">
            <div class="card-h">
              <h2 id="no-t">Note pour l'équipe</h2>
              <span class="muted small" aria-live="polite">{{ noteState === 'saving' ? 'Enregistrement…' : noteState === 'saved' ? 'Enregistrée' : noteState === 'error' ? "Pas enregistrée" : '' }}</span>
            </div>
            <label class="sr-only" for="l-note">Note pour l'équipe</label>
            <textarea id="l-note" v-model="note" class="f" rows="4" :disabled="!cur" placeholder="Ex. rappelé, essai prévu samedi 10 h" @blur="saveNote" />
            <span class="muted small">Visible seulement par votre équipe. Enregistrée dès que vous quittez le champ.</span>
          </section>
        </div>

        <!-- colonne latérale -->
        <aside class="side-col">
          <section class="card o1" aria-labelledby="st-t">
            <h2 id="st-t">Suivi</h2>
            <template v-if="cur">
              <div class="state">
                <span class="dot" :class="cur.status === 'new' ? 'on' : 'off'" aria-hidden="true" />
                <span>{{ cur.status === 'new' ? 'À traiter' : 'Traitée' }}</span>
              </div>
              <button v-if="cur.status === 'new'" type="button" class="btn btn-primary full" :disabled="saving" @click="setStatus('done')">Marquer comme traitée</button>
              <button v-else type="button" class="btn full" :disabled="saving" @click="setStatus('new')">Remettre en nouvelle</button>
            </template>
            <span v-else class="skel" style="height: 32px" />
          </section>

          <section class="card o2" aria-labelledby="co-t">
            <h2 id="co-t">Contact</h2>
            <template v-if="cur">
              <dl class="facts">
                <dt>Téléphone</dt>
                <dd><a v-if="cur.phone" :href="telHref(cur.phone)">{{ cur.phone }}</a><span v-else class="muted">Non indiqué</span></dd>
                <dt>E-mail</dt>
                <dd><a v-if="cur.email" :href="`mailto:${cur.email}`" class="brk">{{ cur.email }}</a><span v-else class="muted">Non indiqué</span></dd>
              </dl>
              <div class="row" style="gap: 8px">
                <a v-if="cur.phone" :href="telHref(cur.phone)" class="btn btn-sm">Appeler</a>
                <a v-if="cur.email" :href="`mailto:${cur.email}?subject=${encodeURIComponent(leadSubject(cur))}`" class="btn btn-sm">Écrire</a>
              </div>
            </template>
          </section>

          <section v-if="cur?.vehicle" class="card o6" aria-labelledby="ca-t">
            <div class="card-h">
              <h2 id="ca-t">Voiture demandée</h2>
              <RouterLink :to="`/vehicles/${cur.vehicle.id}`" class="btn-link">Ouvrir</RouterLink>
            </div>
            <RouterLink :to="`/vehicles/${cur.vehicle.id}`" class="car">
              <img v-if="cur.vehicle.cover_url" :src="cur.vehicle.cover_url" alt="" />
              <span v-else class="car-ph" />
              <span class="col" style="gap: 2px; min-width: 0">
                <span class="car-n">{{ cur.vehicle.label }}</span>
                <span class="muted small">{{ euros(cur.vehicle.price) }}<template v-if="carFacts"> · {{ carFacts }}</template></span>
              </span>
            </RouterLink>
            <span v-if="carState" class="muted small">{{ carState }}</span>
          </section>

          <section v-if="cur?.vehicle" class="card o7" aria-labelledby="sa-t">
            <h2 id="sa-t">Autres demandes sur cette voiture</h2>
            <span v-if="!sameCar.length" class="muted small">Aucune. C'est la première.</span>
            <RouterLink v-for="o in sameCar" :key="o.id" :to="`/leads/${o.id}`" class="same">
              <span>{{ o.name }}</span>
              <span class="muted">{{ relative(o.created_at) }} · {{ o.status === 'new' ? 'Nouvelle' : 'Traitée' }}</span>
            </RouterLink>
          </section>
        </aside>
      </div>
    </template>
  </div>
</template>

<style scoped>
.page {
  max-width: 1000px;
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
  min-height: 24px;
}
.full {
  width: 100%;
}
.msg {
  margin: 0;
  font-size: 14px;
  line-height: 1.6;
  white-space: pre-line;
}
.facts {
  margin: 0;
  display: grid;
  grid-template-columns: max-content minmax(0, 1fr);
  gap: 6px 14px;
  font-size: 13px;
}
.facts dt {
  color: var(--muted);
}
.facts dd {
  margin: 0;
  min-width: 0;
}
.facts a {
  text-decoration: none;
}
.brk {
  word-break: break-all;
}
.state {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
}
.state .dot.on {
  background: #005bd3;
}
.state .dot.off {
  background: var(--green-dot);
}
.p-new {
  background: #e0f0ff;
  color: #00428a;
}
.p-done {
  background: #f1f1f1;
  color: var(--muted);
}
.car {
  display: flex;
  gap: 10px;
  align-items: center;
  text-decoration: none;
  color: var(--ink);
}
.car img,
.car-ph {
  width: 64px;
  height: 48px;
  border-radius: 6px;
  object-fit: cover;
  background: #f1f1f1;
  flex-shrink: 0;
}
.car-n {
  font-size: 13px;
  font-weight: 600;
}
.car:hover .car-n {
  text-decoration: underline;
}
.same {
  display: flex;
  flex-direction: column;
  gap: 1px;
  font-size: 13px;
  text-decoration: none;
  color: var(--ink);
  border-top: 1px solid #ebebeb;
  padding-top: 8px;
}
.same .muted {
  font-size: 12px;
}
.same:hover span:first-child {
  text-decoration: underline;
}
.card-h .btn-link {
  min-height: 0;
}
@media (max-width: 900px) {
  .layout {
    grid-template-columns: minmax(0, 1fr);
  }
}
@media (max-width: 640px) {
  /* sur téléphone : suivi et contact d'abord, puis le message, la note et la voiture */
  .main-col,
  .side-col {
    display: contents;
  }
  .o1 { order: 1; }
  .o2 { order: 2; }
  .o3 { order: 3; }
  .o4 { order: 4; }
  .o5 { order: 5; }
  .o6 { order: 6; }
  .o7 { order: 7; }
  .head {
    flex-wrap: wrap;
  }
  .head-t {
    flex-basis: calc(100% - 52px);
  }
  .head-a {
    width: 100%;
    justify-content: flex-end;
  }
}
</style>
