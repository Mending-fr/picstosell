<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { get, post, errorMessage } from '../api'
import { session, isManager, toast, refreshSidebar } from '../session'
import { PILL, todayLabel } from '../labels'
import PriceSoldModal from '../components/PriceSoldModal.vue'
import type { Dashboard, TodoItem } from '../types'

const router = useRouter()
const data = ref<Dashboard | null>(null)
const error = ref('')
const q = ref('')
const searchEl = ref<HTMLInputElement | null>(null)
const priceOpen = ref(false)
const busyRow = ref<string | null>(null)

async function load() {
  error.value = ''
  try {
    data.value = await get<Dashboard>('/dashboard')
    session.usage = data.value.usage
  } catch (e) {
    error.value = errorMessage(e)
  }
}

const firstName = computed(() => {
  const n = session.user?.name ?? ''
  const first = n.split(' ')[0]
  return /démo|demo/i.test(n) ? '' : first
})

const c = computed(() => data.value?.counts)
const firstOf = (action: string) => data.value?.todo.find((t) => t.action === action)

const reviewTo = computed(() => {
  const t = firstOf('review')
  return t ? `/vehicles/${t.id}` : '/vehicles?status=to_review'
})
const publishTo = computed(() => {
  const t = firstOf('publish')
  return t ? `/vehicles/${t.id}` : '/vehicles?status=ready'
})

const visibleTodo = computed(() => {
  const all = data.value?.todo ?? []
  return isManager.value ? all : all.filter((t) => t.action === 'request_photos')
})

const ROW: Record<string, { status: string; cta: string; pill: { fg: string; bg: string } }> = {
  review: { status: 'À vérifier', cta: 'Vérifier', pill: PILL.check },
  publish: { status: 'Prête', cta: 'Mettre en ligne', pill: PILL.ok },
  request_photos: { status: 'Sans photo', cta: 'Ajouter les photos', pill: PILL.grey },
  unpublish: { status: 'Vendue', cta: "Retirer l'annonce", pill: PILL.red },
  open_lead: { status: 'Demande', cta: 'Voir la demande', pill: PILL.blue },
}

function rowKey(t: TodoItem) {
  return `${t.type}-${t.id}-${t.action}`
}

async function act(t: TodoItem) {
  if (t.action === 'open_lead') return router.push({ path: '/leads', query: { id: String(t.id) } })
  if (t.action !== 'unpublish') return router.push(`/vehicles/${t.id}`)
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

function search() {
  const s = q.value.trim()
  router.push({ path: '/vehicles', query: s ? { q: s } : {} })
}

function onKey(e: KeyboardEvent) {
  if (e.metaKey || e.ctrlKey || e.altKey || priceOpen.value) return
  const t = e.target as HTMLElement
  if (t && (t.tagName === 'INPUT' || t.tagName === 'TEXTAREA' || t.tagName === 'SELECT' || t.isContentEditable)) return
  if (document.querySelector('[role="dialog"]')) return
  const k = e.key.toLowerCase()
  if (e.key === '/') {
    e.preventDefault()
    searchEl.value?.focus()
  } else if (k === 'n') router.push('/vehicles/new')
  else if (k === 'v' && isManager.value) router.push(reviewTo.value)
  else if (k === 'p' && isManager.value) router.push(publishTo.value)
  else if (k === 's' && isManager.value) {
    e.preventDefault()
    priceOpen.value = true
  }
}

function onChanged() {
  load()
}

onMounted(() => {
  load()
  refreshSidebar()
  document.addEventListener('keydown', onKey)
})
onBeforeUnmount(() => document.removeEventListener('keydown', onKey))

const plural = (n: number, one: string, many: string) => (n > 1 ? many : one)
</script>

<template>
  <div class="page-head">
    <div class="col" style="gap: 4px">
      <h1>Bonjour{{ firstName ? ` ${firstName}` : '' }}</h1>
      <p class="lead" style="margin: 0">
        {{ todayLabel() }}<template v-if="data">
          · {{ visibleTodo.length ? `${visibleTodo.length} ${plural(visibleTodo.length, 'chose', 'choses')} à faire aujourd'hui` : 'rien d\'urgent aujourd\'hui' }}</template
        >
      </p>
    </div>
    <form class="search" role="search" @submit.prevent="search">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#5B606B" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7" /><path d="M20 20l-4-4" /></svg>
      <label for="home-q" class="sr-only">Rechercher</label>
      <input id="home-q" ref="searchEl" v-model="q" type="search" placeholder="Chercher une plaque, un modèle" />
      <span class="kbd" aria-hidden="true">/</span>
    </form>
  </div>

  <p v-if="error" class="alert alert-error" role="alert">
    {{ error }} <button type="button" class="btn-link" @click="load">Réessayer</button>
  </p>

  <section aria-labelledby="wf" class="col" style="gap: 14px">
    <h2 id="wf" class="section-title">Que voulez-vous faire ?</h2>
    <div class="wf-grid">
      <RouterLink to="/vehicles/new" class="wf wf-blue">
        <div class="wf-top">
          <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" aria-hidden="true"><rect x="3" y="7" width="18" height="10" rx="2" /><path d="M7 12h10" /></svg>
          <span class="kbd-inv" aria-hidden="true">N</span>
        </div>
        <span class="wf-title">Ajouter une voiture</span>
        <span class="wf-text" style="color: #dce5ff">Tapez la plaque, on remplit le reste. Les photos se font ensuite au téléphone, sur le parking.</span>
      </RouterLink>

      <template v-if="isManager">
        <RouterLink :to="reviewTo" class="wf">
          <div class="wf-top">
            <span v-if="c" class="wf-n" style="color: #b45309">{{ c.to_review }}</span>
            <span v-else class="skel" style="width: 28px; height: 30px" />
            <span class="kbd" aria-hidden="true">V</span>
          </div>
          <span class="wf-title">Vérifier les photos</span>
          <span class="wf-text">
            <template v-if="!c">Chargement…</template>
            <template v-else-if="c.to_review">{{ c.to_review }} {{ plural(c.to_review, 'voiture a ses', 'voitures ont leurs') }} photos de showroom prêtes. Un coup d'œil avant la mise en ligne.</template>
            <template v-else>Rien à vérifier pour l'instant. Les photos arrivent ici dès qu'elles sont prêtes.</template>
          </span>
        </RouterLink>

        <RouterLink :to="publishTo" class="wf">
          <div class="wf-top">
            <span v-if="c" class="wf-n" style="color: #0f7a4a">{{ c.ready }}</span>
            <span v-else class="skel" style="width: 28px; height: 30px" />
            <span class="kbd" aria-hidden="true">P</span>
          </div>
          <span class="wf-title">Mettre en ligne</span>
          <span class="wf-text">
            <template v-if="!c">Chargement…</template>
            <template v-else-if="c.ready">{{ c.ready }} {{ plural(c.ready, 'annonce est prête', 'annonces sont prêtes') }} pour votre site, Leboncoin et La Centrale.</template>
            <template v-else>Aucune annonce en attente. Vérifiez d'abord les photos.</template>
          </span>
        </RouterLink>

        <button type="button" class="wf" @click="priceOpen = true">
          <div class="wf-top">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#15171C" stroke-width="2" aria-hidden="true"><path d="M20 12l-8 8-9-9V3h8z" /><circle cx="7.5" cy="7.5" r="1.5" /></svg>
            <span class="kbd" aria-hidden="true">S</span>
          </div>
          <span class="wf-title">Changer un prix ou marquer vendue</span>
          <span class="wf-text">Tapez la plaque et c'est fait, l'annonce suit sur votre site.</span>
        </button>
      </template>
      <RouterLink v-else to="/vehicles?status=to_photograph" class="wf">
        <div class="wf-top">
          <span v-if="c" class="wf-n">{{ c.to_photograph }}</span>
          <span v-else class="skel" style="width: 28px; height: 30px" />
        </div>
        <span class="wf-title">Voitures à photographier</span>
        <span class="wf-text">Les voitures arrivées sur le parking qui attendent leurs photos.</span>
      </RouterLink>
    </div>
  </section>

  <section aria-labelledby="flow" class="col" style="gap: 14px">
    <h2 id="flow" class="section-title">Votre stock en ce moment</h2>
    <div v-if="!c" class="tiles">
      <div v-for="i in 6" :key="i" class="skel" style="height: 92px; border-radius: 12px" />
    </div>
    <div v-else class="tiles">
      <RouterLink to="/vehicles?status=to_photograph" class="stat">
        <span class="stat-label">À photographier</span><span class="stat-value">{{ c.to_photograph }}</span>
        <span class="stat-sub">arrivées sur le parking</span>
      </RouterLink>
      <RouterLink to="/vehicles?status=to_photograph" class="stat">
        <span class="stat-label">Photos en préparation</span><span class="stat-value">{{ c.processing }}</span>
        <span class="stat-sub" :style="c.processing ? { color: '#1F4FD8' } : undefined">{{ c.processing ? 'prêtes dans quelques minutes' : 'aucune en cours' }}</span>
      </RouterLink>
      <RouterLink to="/vehicles?status=to_review" class="stat" :class="{ warm: c.to_review > 0 }">
        <span class="stat-label">À vérifier</span><span class="stat-value">{{ c.to_review }}</span>
        <span class="stat-sub">{{ c.to_review ? "c'est à vous" : 'tout est vérifié' }}</span>
      </RouterLink>
      <RouterLink to="/vehicles?status=ready" class="stat">
        <span class="stat-label">Prêtes</span><span class="stat-value">{{ c.ready }}</span>
        <span class="stat-sub">à mettre en ligne</span>
      </RouterLink>
      <RouterLink to="/vehicles?status=online" class="stat">
        <span class="stat-label">En ligne</span><span class="stat-value">{{ c.online }}</span>
        <span class="stat-sub">{{ c.online_without_leads }} sans aucune demande</span>
      </RouterLink>
      <RouterLink to="/vehicles?status=sold" class="stat">
        <span class="stat-label">Vendues ce mois</span><span class="stat-value">{{ c.sold_month }}</span>
        <span class="stat-sub">{{ c.sold_still_online ? `${c.sold_still_online} encore en ligne` : 'toutes retirées du site' }}</span>
      </RouterLink>
    </div>
  </section>

  <section aria-labelledby="todo" class="col" style="gap: 14px">
    <div class="row between" style="align-items: baseline">
      <h2 id="todo" class="section-title">À faire aujourd'hui</h2>
      <span class="muted" style="font-size: 13px">Le plus urgent en haut</span>
    </div>
    <div class="table-wrap">
      <template v-if="!data && !error">
        <div v-for="i in 4" :key="i" class="todo-row">
          <span class="skel" style="width: 92px; height: 26px" />
          <span class="col grow" style="gap: 6px"><span class="skel" style="width: 50%; height: 16px" /><span class="skel" style="width: 70%; height: 13px" /></span>
        </div>
      </template>
      <div v-else-if="data && !visibleTodo.length" class="empty">
        <strong>Tout est à jour</strong>
        <span>Rien ne vous attend. Ajoutez une voiture dès qu'elle arrive sur le parking.</span>
        <RouterLink to="/vehicles/new" class="btn btn-primary">Ajouter une voiture</RouterLink>
      </div>
      <div v-for="t in visibleTodo" :key="rowKey(t)" class="todo-row">
        <span v-if="t.plate" class="plate">{{ t.plate }}</span>
        <span v-else class="plate" style="border-style: dashed; color: var(--muted)">SITE</span>
        <div class="col grow" style="gap: 2px; min-width: 0">
          <span style="font-size: 15px; font-weight: 600">{{ t.title }}</span>
          <span class="muted" style="font-size: 13px">{{ t.detail }}</span>
        </div>
        <span class="pill" :style="{ color: ROW[t.action].pill.fg, background: ROW[t.action].pill.bg }">{{ ROW[t.action].status }}</span>
        <button type="button" class="btn btn-dark btn-sm" style="min-height: 40px" :disabled="busyRow === rowKey(t)" @click="act(t)">
          {{ ROW[t.action].cta }}
        </button>
      </div>
    </div>
  </section>

  <PriceSoldModal v-if="priceOpen" @close="priceOpen = false" @changed="onChanged" />
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
  flex: 0 1 380px;
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
.wf-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
  gap: 14px;
}
.wf {
  text-decoration: none;
  color: var(--ink);
  background: #fff;
  border: 1px solid var(--line);
  border-radius: 14px;
  padding: 20px;
  display: flex;
  flex-direction: column;
  gap: 14px;
  min-height: 150px;
  text-align: left;
}
.wf:hover {
  border-color: #c4c4bd;
  color: var(--ink);
}
.wf-blue {
  background: var(--accent);
  border-color: var(--accent);
  color: #fff;
}
.wf-blue:hover {
  background: var(--accent-dark);
  color: #fff;
}
.wf-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
  width: 100%;
}
.wf-n {
  font-size: 26px;
  font-weight: 700;
}
.wf-title {
  font-size: 19px;
  font-weight: 700;
}
.wf-text {
  font-size: 14px;
  color: var(--muted);
}
.tiles {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
  gap: 10px;
}
.stat.warm {
  background: #fff7ed;
  border-color: #f3d9b5;
}
.stat.warm .stat-label,
.stat.warm .stat-sub {
  color: var(--orange-ink);
}
.todo-row {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 14px 18px;
  border-bottom: 1px solid var(--line-2);
}
.todo-row:last-child {
  border-bottom: 0;
}
@media (max-width: 640px) {
  .todo-row {
    flex-wrap: wrap;
    gap: 10px;
  }
  .todo-row .grow {
    flex-basis: calc(100% - 120px);
  }
}
</style>
