<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { get, post, errorMessage } from '../api'
import { session, isManager, toast, refreshSidebar } from '../session'
import { todayLabel, vehicleTitle } from '../labels'
import { modals, openAdd, openPrice, typingIn } from '../modals'
import type { Dashboard, TodoAction, TodoItem, Vehicle } from '../types'

const router = useRouter()
const data = ref<Dashboard | null>(null)
const names = ref<Record<number, string>>({})
const error = ref('')
const busyRow = ref<string | null>(null)

async function load() {
  error.value = ''
  try {
    const [d, list] = await Promise.all([get<Dashboard>('/dashboard'), get<{ data: Vehicle[] }>('/vehicles').catch(() => null)])
    data.value = d
    session.usage = d.usage
    if (list) names.value = Object.fromEntries(list.data.map((v) => [v.id, v.model || vehicleTitle(v, false)]))
  } catch (e) {
    error.value = errorMessage(e)
  }
}

const firstName = computed(() => {
  const n = session.user?.name ?? ''
  return /démo|demo/i.test(n) ? '' : n.split(' ')[0]
})

const c = computed(() => data.value?.counts)
const todoOf = (action: TodoAction) => (data.value?.todo ?? []).filter((t) => t.action === action)

/** Noms courts des voitures concernées, trois au plus, séparés par des virgules. */
function carNames(action: TodoAction): string {
  return todoOf(action)
    .slice(0, 3)
    .map((t) => names.value[t.id] ?? t.title)
    .join(', ')
}

const reviewTo = computed(() => {
  const t = todoOf('review')[0]
  return t ? `/vehicles/${t.id}` : '/vehicles?status=to_review'
})
const publishTo = computed(() => {
  const t = todoOf('publish')[0]
  return t ? `/vehicles/${t.id}` : '/vehicles?status=ready'
})

const visibleTodo = computed(() => {
  const all = data.value?.todo ?? []
  return isManager.value ? all : all.filter((t) => t.action === 'request_photos')
})

// ---------- phrase de résumé, construite à partir des compteurs ----------
const n = (k: number) => (k === 1 ? 'une' : String(k))
const pl = (k: number, one: string, many: string) => (k > 1 ? many : one)

const summary = computed(() => {
  const d = data.value
  if (!d) return ''
  const k = d.counts
  const parts: string[] = []
  if (isManager.value) {
    if (k.to_review) parts.push(`${n(k.to_review)} ${pl(k.to_review, 'voiture', 'voitures')} à vérifier`)
    if (k.ready) parts.push(`${n(k.ready)} ${pl(k.ready, 'voiture prête', 'voitures prêtes')} à mettre en ligne`)
    const leads = todoOf('open_lead').length
    if (leads) parts.push(`${n(leads)} ${pl(leads, 'demande', 'demandes')} à traiter`)
    if (k.sold_still_online) parts.push(`${n(k.sold_still_online)} ${pl(k.sold_still_online, 'annonce', 'annonces')} de voiture vendue à retirer`)
  }
  if (k.to_photograph) parts.push(`${n(k.to_photograph)} ${pl(k.to_photograph, 'voiture', 'voitures')} à photographier`)
  if (!parts.length) return "Rien d'urgent aujourd'hui."
  const s = parts.length === 1 ? parts[0] : `${parts.slice(0, -1).join(', ')} et ${parts[parts.length - 1]}`
  return `${s.charAt(0).toUpperCase()}${s.slice(1)}.`
})

// ---------- liste « À faire aujourd'hui » ----------
interface Tone {
  tag: string
  cta: string
  tone: 'amber' | 'green' | 'slate' | 'red' | 'violet'
}
const ROW: Record<TodoAction, Tone> = {
  review: { tag: 'À vérifier', cta: 'Vérifier', tone: 'amber' },
  publish: { tag: 'Prête', cta: 'Mettre en ligne', tone: 'green' },
  request_photos: { tag: 'Sans photo', cta: 'Ajouter les photos', tone: 'slate' },
  unpublish: { tag: 'Vendue', cta: "Retirer l'annonce", tone: 'red' },
  open_lead: { tag: 'Demande', cta: 'Voir la demande', tone: 'violet' },
}

function rowKey(t: TodoItem) {
  return `${t.type}-${t.id}-${t.action}`
}
function rowTo(t: TodoItem) {
  return t.action === 'open_lead' ? { path: '/leads', query: { id: String(t.id) } } : `/vehicles/${t.id}`
}

async function unpublish(t: TodoItem) {
  busyRow.value = rowKey(t)
  try {
    await post(`/vehicles/${t.id}/publish`, { published: false })
    if (data.value) {
      data.value.todo = data.value.todo.filter((x) => x !== t)
      data.value.counts.sold_still_online = Math.max(0, data.value.counts.sold_still_online - 1)
    }
    toast("L'annonce est retirée de votre site.")
  } catch (e) {
    toast(errorMessage(e), 'error')
  } finally {
    busyRow.value = null
  }
}

// ---------- ligne discrète du stock ----------
const stockLine = computed(() => {
  const k = c.value
  if (!k) return []
  const items = [
    { n: k.to_photograph, label: 'à photographier', dot: 'var(--slate-dot)', to: '/vehicles?status=to_photograph', title: '' },
    { n: k.processing, label: 'en préparation', dot: 'var(--accent-line)', to: '/vehicles?status=to_photograph', title: 'Photos showroom prêtes dans quelques minutes', hide: !k.processing },
    { n: k.to_review, label: 'à vérifier', dot: 'var(--amber-dot)', to: '/vehicles?status=to_review', title: '' },
    { n: k.ready, label: pl(k.ready, 'prête', 'prêtes'), dot: 'var(--green-dot)', to: '/vehicles?status=ready', title: '' },
    { n: k.online, label: 'en ligne', dot: 'var(--accent)', to: '/vehicles?status=online', title: `${k.online_without_leads} sans aucune demande` },
    { n: k.sold_month, label: `${pl(k.sold_month, 'vendue', 'vendues')} ce mois`, dot: 'var(--violet-dot)', to: '/vehicles?status=sold', title: k.sold_still_online ? `${k.sold_still_online} encore en ligne` : 'Toutes retirées du site' },
  ]
  return items.filter((i) => !i.hide)
})

// ---------- raccourcis (la recherche « / » est dans la barre du haut) ----------
function onKey(e: KeyboardEvent) {
  if (e.metaKey || e.ctrlKey || e.altKey || modals.add || modals.price || typingIn(e)) return
  if (document.querySelector('[role="dialog"]')) return
  const k = e.key.toLowerCase()
  if (k === 'v' && isManager.value) router.push(reviewTo.value)
  else if (k === 'p' && isManager.value) router.push(publishTo.value)
}

watch(
  () => modals.version,
  () => load(),
)
onMounted(() => {
  load()
  refreshSidebar()
  document.addEventListener('keydown', onKey)
})
onBeforeUnmount(() => document.removeEventListener('keydown', onKey))
</script>

<template>
  <div class="home">
    <header class="hello">
      <div class="col hello-t">
        <span class="date">{{ todayLabel() }}</span>
        <h1>
          Bonjour{{ firstName ? ` ${firstName}` : '' }}.
          <span v-if="data" class="sum">{{ summary }}</span>
          <span v-else-if="!error" class="skel sum-skel" aria-hidden="true" />
        </h1>
      </div>
    </header>

    <p v-if="error" class="alert alert-error" role="alert" style="margin: 0">
      {{ error }} <button type="button" class="btn-link" style="min-height: 0" @click="load">Réessayer</button>
    </p>

    <section aria-label="Actions principales" class="cards" :class="{ few: !isManager }">
      <button type="button" class="ac ac-add" aria-keyshortcuts="N" @click="openAdd">
        <span class="ac-top">
          <span class="ac-ico" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.2"><path d="M12 5v14M5 12h14" /></svg></span>
          <span class="kbd-inv" aria-hidden="true">N</span>
        </span>
        <span class="ac-t big">Ajouter une voiture</span>
        <span class="ac-x">Par la plaque ou le numéro de série</span>
      </button>

      <template v-if="isManager">
        <RouterLink :to="reviewTo" class="ac amber" aria-keyshortcuts="V">
          <span class="ac-top">
            <span class="ac-n">{{ c ? c.to_review : '' }}<span v-if="!c" class="skel" style="width: 20px; height: 20px" /></span>
            <span class="ac-k" aria-hidden="true">V</span>
          </span>
          <span class="ac-t">Vérifier les photos</span>
          <span class="ac-x">{{ !c ? 'Chargement…' : c.to_review ? carNames('review') : 'Rien à vérifier pour le moment' }}</span>
        </RouterLink>

        <RouterLink :to="publishTo" class="ac green" aria-keyshortcuts="P">
          <span class="ac-top">
            <span class="ac-n">{{ c ? c.ready : '' }}<span v-if="!c" class="skel" style="width: 20px; height: 20px" /></span>
            <span class="ac-k" aria-hidden="true">P</span>
          </span>
          <span class="ac-t">Mettre en ligne</span>
          <span class="ac-x">{{ !c ? 'Chargement…' : c.ready ? carNames('publish') : 'Aucune voiture prête' }}</span>
        </RouterLink>

        <button type="button" class="ac slate" aria-keyshortcuts="S" @click="openPrice">
          <span class="ac-top">
            <span class="ac-n">€</span>
            <span class="ac-k" aria-hidden="true">S</span>
          </span>
          <span class="ac-t">Prix ou vendue</span>
          <span class="ac-x">Par la plaque ou le modèle</span>
        </button>
      </template>
      <RouterLink v-else to="/vehicles?status=to_photograph" class="ac slate">
        <span class="ac-top">
          <span class="ac-n">{{ c ? c.to_photograph : '' }}<span v-if="!c" class="skel" style="width: 20px; height: 20px" /></span>
        </span>
        <span class="ac-t">À photographier</span>
        <span class="ac-x">{{ !c ? 'Chargement…' : c.to_photograph ? carNames('request_photos') : 'Aucune voiture en attente' }}</span>
      </RouterLink>
    </section>

    <section aria-labelledby="todo" class="todo">
      <div class="todo-head">
        <h2 id="todo" class="section-title">À faire aujourd'hui</h2>
        <RouterLink to="/vehicles" class="all">Tout voir</RouterLink>
      </div>
      <template v-if="!data && !error">
        <div v-for="i in 3" :key="i" class="tr">
          <span class="skel" style="width: 96px; height: 24px; border-radius: 999px" />
          <span class="col grow" style="gap: 6px"><span class="skel" style="width: 40%; height: 15px" /><span class="skel" style="width: 60%; height: 13px" /></span>
        </div>
      </template>
      <div v-else-if="data && !visibleTodo.length" class="none">
        <strong>Tout est à jour.</strong> Ajoutez une voiture dès qu'elle arrive sur le parking.
      </div>
      <template v-for="t in visibleTodo" :key="rowKey(t)">
        <button v-if="t.action === 'unpublish'" type="button" class="tr" :class="ROW[t.action].tone" :disabled="busyRow === rowKey(t)" @click="unpublish(t)">
          <span class="tag">{{ ROW[t.action].tag }}</span>
          <span class="tx">
            <span class="tt">{{ t.title }}</span>
            <span class="td">{{ t.detail }}</span>
          </span>
          <span v-if="t.plate" class="plate">{{ t.plate }}</span>
          <span class="cta">{{ ROW[t.action].cta }}</span>
        </button>
        <RouterLink v-else :to="rowTo(t)" class="tr" :class="ROW[t.action].tone">
          <span class="tag">{{ ROW[t.action].tag }}</span>
          <span class="tx">
            <span class="tt">{{ t.title }}</span>
            <span class="td">{{ t.detail }}</span>
          </span>
          <span v-if="t.plate" class="plate">{{ t.plate }}</span>
          <span class="cta">{{ ROW[t.action].cta }}</span>
        </RouterLink>
      </template>
    </section>

    <section aria-label="Votre stock" class="stockline">
      <template v-if="c">
        <RouterLink v-for="s in stockLine" :key="s.label" :to="s.to" class="sl" :title="s.title || undefined">
          <span class="dot" :style="{ background: s.dot }" /><strong>{{ s.n }}</strong> {{ s.label }}
        </RouterLink>
      </template>
      <span v-else class="skel" style="width: 60%; height: 16px" />
    </section>
  </div>
</template>

<style scoped>
.home {
  width: 100%;
  max-width: 960px;
  margin: 0 auto;
  display: flex;
  flex-direction: column;
  gap: 40px;
  padding-top: 12px;
}
.hello {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  gap: 16px;
  flex-wrap: wrap;
}
.hello-t {
  gap: 10px;
  min-width: 0;
  flex: 1 1 440px;
}
.date {
  font-size: 14px;
  color: var(--muted-3);
}
.sum {
  color: var(--muted-2);
}
.sum-skel {
  display: inline-block;
  width: 280px;
  height: 22px;
  vertical-align: middle;
}

/* cartes d'action */
.cards {
  display: grid;
  grid-template-columns: 1.25fr repeat(3, minmax(0, 1fr));
  gap: 14px;
}
.cards.few {
  grid-template-columns: repeat(2, minmax(0, 1fr));
}
.ac {
  font: inherit;
  text-align: left;
  text-decoration: none;
  border: 1px solid var(--line);
  border-radius: 20px;
  padding: 22px;
  background: #fff;
  color: var(--ink);
  display: flex;
  flex-direction: column;
  gap: 14px;
  min-height: 184px;
  transition: transform 0.12s ease, box-shadow 0.12s ease;
}
.ac:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 20px rgba(28, 34, 48, 0.08);
  color: var(--ink);
}
@media (prefers-reduced-motion: reduce) {
  .ac:hover {
    transform: none;
  }
}
.ac-add {
  position: relative;
  overflow: hidden;
  border: 0;
  background: var(--accent);
  color: #fff;
  box-shadow: 0 8px 22px rgba(46, 90, 136, 0.24);
}
.ac-add::before {
  content: '';
  position: absolute;
  inset: 0 0 auto 0;
  height: 4px;
  background: var(--signal);
}
.ac-add:hover {
  color: #fff;
  background: #285079;
  box-shadow: 0 10px 26px rgba(46, 90, 136, 0.3);
}
.ac-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
  width: 100%;
}
.ac-ico {
  width: 44px;
  height: 44px;
  border-radius: 14px;
  background: rgba(255, 255, 255, 0.16);
  display: flex;
  align-items: center;
  justify-content: center;
}
.ac-n {
  min-width: 44px;
  height: 44px;
  padding: 0 10px;
  border-radius: 14px;
  background: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 20px;
  font-weight: 500;
}
.ac-k {
  font-family: var(--mono);
  font-size: 11px;
  border-radius: 5px;
  padding: 1px 6px;
  background: #fff;
  line-height: 1.5;
}
.ac-t {
  font-size: 17px;
  font-weight: 500;
  margin-top: auto;
}
.ac-t.big {
  font-size: 19px;
}
.ac-x {
  font-size: 14px;
  line-height: 1.4;
  color: var(--muted);
}
.ac-add .ac-x {
  color: #ccdaea;
}
.amber {
  --t-ink: var(--amber-ink);
  --t-tint: var(--amber-tint);
  --t-line: var(--amber-line);
}
.green {
  --t-ink: var(--green-ink);
  --t-tint: var(--green-tint);
  --t-line: var(--green-line);
}
.slate {
  --t-ink: var(--slate-ink);
  --t-tint: var(--slate-tint);
  --t-line: var(--slate-line);
}
.violet {
  --t-ink: var(--violet-ink);
  --t-tint: var(--violet-tint);
  --t-line: var(--violet-line);
}
.red {
  --t-ink: var(--red-ink);
  --t-tint: var(--red-tint);
  --t-line: var(--red-line);
}
.ac.amber,
.ac.green,
.ac.slate {
  background: var(--t-tint);
  border-color: var(--t-line);
}
.ac.amber .ac-n,
.ac.green .ac-n,
.ac.slate .ac-n,
.ac.amber .ac-k,
.ac.green .ac-k,
.ac.slate .ac-k {
  color: var(--t-ink);
}

/* à faire aujourd'hui */
.todo {
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.todo-head {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  margin-bottom: 10px;
}
.all {
  font-size: 13px;
  text-decoration: none;
}
.tr {
  font: inherit;
  text-align: left;
  width: calc(100% + 32px);
  background: transparent;
  border: 0;
  text-decoration: none;
  color: var(--ink);
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 14px 16px;
  margin: 0 -16px;
  border-radius: 14px;
}
.tr:hover {
  background: #fff;
  box-shadow: 0 1px 2px rgba(28, 34, 48, 0.05);
  color: var(--ink);
}
.tag {
  font-size: 12px;
  font-weight: 500;
  color: var(--t-ink);
  background: var(--t-tint);
  border-radius: 999px;
  padding: 4px 10px;
  width: 96px;
  text-align: center;
  flex-shrink: 0;
}
.tx {
  display: flex;
  flex-direction: column;
  gap: 3px;
  flex: 1;
  min-width: 0;
}
.tt {
  font-size: 15px;
  font-weight: 500;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.td {
  font-size: 14px;
  color: var(--muted-2);
}
.cta {
  font-size: 14px;
  font-weight: 500;
  color: var(--t-ink);
  width: 130px;
  text-align: right;
  flex-shrink: 0;
}
.tr:hover .cta {
  text-decoration: underline;
}
.none {
  font-size: 15px;
  color: var(--muted-2);
  padding: 8px 0;
}
.none strong {
  color: var(--ink);
}

/* ligne du stock */
.stockline {
  display: flex;
  flex-wrap: wrap;
  gap: 8px 24px;
  font-size: 14px;
  color: var(--muted-2);
  border-top: 1px solid var(--line-2);
  padding-top: 20px;
}
.sl {
  text-decoration: none;
  color: var(--muted-2);
  display: flex;
  align-items: center;
  gap: 8px;
  min-height: 28px;
}
.sl strong {
  color: var(--ink);
  font-weight: 500;
}
.sl:hover {
  color: var(--ink);
}

@media (max-width: 1100px) {
  .cards {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
@media (max-width: 640px) {
  .home {
    gap: 28px;
    padding-top: 0;
  }
  .ac {
    min-height: 150px;
    padding: 18px;
  }
  .cards:not(.few) .ac-add,
  .cards:not(.few) .ac:last-child {
    grid-column: 1 / -1;
    min-height: 0;
  }
  .cards:not(.few) .ac:last-child .ac-t {
    margin-top: 0;
  }
  .ac-k,
  .kbd,
  .kbd-inv {
    display: none;
  }
  .ac-add .ac-t {
    margin-top: 0;
  }
  .tr {
    flex-wrap: wrap;
    gap: 6px 12px;
    padding: 14px 16px;
  }
  .tx {
    order: 3;
    flex-basis: 100%;
  }
  .tr .plate {
    order: 2;
    margin-left: auto;
  }
  .cta {
    order: 4;
    width: auto;
    text-align: left;
  }
}
</style>
