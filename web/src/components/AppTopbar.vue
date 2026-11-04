<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import GarageSwitcher from './GarageSwitcher.vue'
import { session, isManager, logout, clearSession } from '../session'
import { modals, typingIn } from '../modals'

const props = defineProps<{ variant: 'garage' | 'mending' }>()
const route = useRoute()
const router = useRouter()
const menuOpen = ref(false)
const meOpen = ref(false)
const q = ref('')
const searchEl = ref<HTMLInputElement | null>(null)
const meRoot = ref<HTMLElement | null>(null)

watch(
  () => route.fullPath,
  () => {
    menuOpen.value = false
    meOpen.value = false
  },
)

interface Link {
  to: string
  label: string
  badge?: number
  exact?: boolean
}

const links = computed<Link[]>(() => {
  if (props.variant === 'mending') {
    return [
      { to: '/mending', label: "Vue d'ensemble", exact: true },
      { to: '/mending/comparisons', label: 'Comparer les modèles' },
      { to: '/mending/models', label: 'Réglages photo IA' },
    ]
  }
  const l: Link[] = [
    { to: '/', label: 'Accueil', exact: true },
    { to: '/vehicles', label: 'Mon parc' },
  ]
  if (isManager.value) {
    l.push(
      { to: '/leads', label: 'Demandes', badge: session.newLeads },
      { to: '/showroom', label: 'Mon showroom' },
      { to: '/site', label: 'Mon site' },
      { to: '/settings', label: 'Réglages' },
    )
  }
  return l
})

function isOn(l: Link) {
  if (l.exact) return route.path === l.to
  return route.path === l.to || route.path.startsWith(l.to + '/')
}

const initial = computed(() => (session.user?.name ?? '?').trim().charAt(0).toUpperCase())
const showUsage = computed(() => props.variant === 'garage' && !!session.usage?.visible && !!session.usage.quota && isManager.value)

function search() {
  const s = q.value.trim()
  router.push({ path: '/vehicles', query: s ? { q: s } : {} })
  q.value = ''
  searchEl.value?.blur()
}

async function doLogout() {
  await logout()
  await router.replace('/login')
  clearSession()
}

// « / » place le curseur dans la recherche, partout dans l'espace garage.
function onKey(e: KeyboardEvent) {
  if (props.variant !== 'garage' || e.key !== '/' || e.metaKey || e.ctrlKey || e.altKey || typingIn(e)) return
  if (modals.add || modals.price || document.querySelector('[role="dialog"]')) return
  e.preventDefault()
  searchEl.value?.focus()
}
function onDocClick(e: MouseEvent) {
  if (meOpen.value && meRoot.value && !meRoot.value.contains(e.target as Node)) meOpen.value = false
}
onMounted(() => {
  document.addEventListener('keydown', onKey)
  document.addEventListener('click', onDocClick)
})
onBeforeUnmount(() => {
  document.removeEventListener('keydown', onKey)
  document.removeEventListener('click', onDocClick)
})
</script>

<template>
  <header class="bar" :class="variant">
    <div class="bar-in">
      <GarageSwitcher :mending="variant === 'mending'" class="bar-sw" />

      <button type="button" class="menu-btn" :aria-expanded="menuOpen" aria-controls="bar-links" @click="menuOpen = !menuOpen">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16" /></svg>
        <span class="sr-only">Menu</span>
      </button>

      <nav id="bar-links" class="links" :class="{ open: menuOpen }" :aria-label="variant === 'mending' ? 'Navigation Mending' : 'Navigation principale'">
        <RouterLink v-for="l in links" :key="l.to" :to="l.to" class="tl" :class="{ on: isOn(l) }" :aria-current="isOn(l) ? 'page' : undefined">
          {{ l.label }}<span v-if="l.badge" class="badge" :aria-label="`${l.badge} nouvelles`">{{ l.badge }}</span>
        </RouterLink>
        <RouterLink v-if="variant === 'mending'" to="/" class="tl small">‹ Espace garage</RouterLink>
      </nav>

      <form v-if="variant === 'garage'" class="bs" role="search" @submit.prevent="search">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7" /><path d="M20 20l-4-4" /></svg>
        <label for="bar-q" class="sr-only">Rechercher dans le stock</label>
        <input id="bar-q" ref="searchEl" v-model="q" type="search" placeholder="Plaque ou modèle" aria-keyshortcuts="/" />
        <span class="kbd" aria-hidden="true">/</span>
      </form>

      <div ref="meRoot" class="me">
        <button type="button" class="me-btn" :aria-expanded="meOpen" aria-haspopup="true" :title="session.user?.name" @click="meOpen = !meOpen">
          {{ initial }}<span class="sr-only">Mon compte</span>
        </button>
        <div v-if="meOpen" class="me-pop">
          <span class="me-name">{{ session.user?.name }}</span>
          <span v-if="showUsage" class="me-usage">
            Ce mois : {{ session.usage!.vehicles_month }} / {{ session.usage!.quota }} voitures
          </span>
          <button type="button" class="me-out" @click="doLogout">Se déconnecter</button>
        </div>
      </div>
    </div>
  </header>
</template>

<style scoped>
.bar {
  --b-bg: var(--bar);
  --b-mark: var(--bar-mark);
  background: var(--b-bg);
  color: var(--bar-text);
  position: sticky;
  top: 0;
  z-index: 40;
}
.bar.mending {
  --b-bg: var(--bar-mending);
  --b-mark: var(--bar-mending-mark);
}
.bar-in {
  height: 64px;
  padding: 0 28px;
  display: flex;
  align-items: center;
  gap: 28px;
}

/* sélecteur de garage, version barre sombre */
.bar-sw {
  flex: 0 1 250px !important;
  width: auto !important;
  padding-bottom: 0 !important;
}
.bar-sw :deep(.sw-btn) {
  min-height: 44px;
  padding: 6px 10px;
  color: var(--bar-strong);
}
.bar-sw :deep(.sw-btn:not(.static)) {
  background: rgba(255, 255, 255, 0.05);
  border-color: var(--bar-line);
}
.bar-sw :deep(.sw-btn.open),
.bar-sw :deep(.sw-btn:not(.static):hover) {
  background: rgba(255, 255, 255, 0.1);
  border-color: rgba(255, 255, 255, 0.22);
}
.bar-sw :deep(.sw-btn .name) {
  -webkit-line-clamp: 1;
}
.bar-sw :deep(.sw-btn .sub) {
  color: var(--bar-muted);
}
.bar-sw :deep(.sw-pop) {
  top: calc(100% + 8px);
  min-width: 300px;
}

.links {
  display: flex;
  align-items: stretch;
  align-self: stretch;
  gap: 22px;
  flex: 1;
  min-width: 0;
}
.tl {
  display: flex;
  align-items: center;
  gap: 6px;
  color: var(--bar-text);
  text-decoration: none;
  font-size: 14px;
  white-space: nowrap;
  border-bottom: 3px solid transparent;
  border-top: 3px solid transparent;
}
.tl:hover {
  color: var(--bar-strong);
}
.tl.on {
  color: var(--bar-strong);
  font-weight: 500;
  border-bottom-color: var(--b-mark);
}
.tl.small {
  font-size: 13px;
  color: var(--bar-muted);
  margin-left: auto;
}
.tl.small:hover {
  color: var(--bar-strong);
}
.badge {
  font-size: 11px;
  font-weight: 600;
  color: var(--violet-ink);
  background: var(--violet-tint);
  border-radius: 999px;
  padding: 1px 7px;
}

.bs {
  display: flex;
  align-items: center;
  gap: 8px;
  height: 38px;
  width: 320px;
  flex-shrink: 1;
  min-width: 180px;
  padding: 0 14px;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.08);
  border: 1px solid var(--bar-line);
  color: var(--bar-muted);
  transition: border-color 0.12s ease, background 0.12s ease;
}
.bs:focus-within {
  background: rgba(255, 255, 255, 0.12);
  border-color: var(--signal);
}
.bs input {
  border: 0;
  outline: 0;
  flex: 1;
  min-width: 0;
  background: transparent;
  color: var(--bar-strong);
  font: inherit;
  font-size: 13px;
}
.bs input::placeholder {
  color: var(--bar-muted);
}
.bs .kbd {
  background: transparent;
  line-height: 1.4;
  font-family: var(--mono);
  font-size: 11px;
  border: 1px solid var(--bar-line);
  border-radius: 5px;
  padding: 0 5px;
  color: var(--bar-muted);
}

.me {
  position: relative;
  flex-shrink: 0;
}
.me-btn {
  width: 36px;
  height: 36px;
  border-radius: 18px;
  border: 0;
  background: var(--bar-on);
  color: var(--bar-strong);
  font-size: 13px;
  font-weight: 500;
}
.me-btn:hover,
.me-btn[aria-expanded='true'] {
  background: rgba(255, 255, 255, 0.18);
}
.me-pop {
  position: absolute;
  right: 0;
  top: calc(100% + 10px);
  min-width: 220px;
  background: #fff;
  border: 1px solid var(--line);
  border-radius: 14px;
  box-shadow: var(--shadow-pop);
  padding: 12px;
  display: flex;
  flex-direction: column;
  gap: 6px;
  color: var(--ink);
}
.me-name {
  font-size: 14px;
  font-weight: 500;
  padding: 2px 6px;
}
.me-usage {
  font-size: 12px;
  color: var(--muted-2);
  padding: 0 6px 4px;
}
.me-out {
  text-align: left;
  background: none;
  border: 0;
  border-top: 1px solid var(--line-2);
  color: var(--ink-2);
  font-size: 14px;
  padding: 10px 6px 4px;
  min-height: 40px;
}
.me-out:hover {
  color: var(--accent-ink);
}

.menu-btn {
  display: none;
}

@media (max-width: 1100px) {
  .bar-in {
    gap: 18px;
    padding: 0 20px;
  }
  .links {
    gap: 16px;
  }
  .bs {
    width: 180px;
  }
}
@media (max-width: 960px) {
  .bar-in {
    height: auto;
    min-height: 60px;
    flex-wrap: wrap;
    padding: 8px 16px;
    gap: 10px;
  }
  .bar-sw {
    flex: 1 1 auto !important;
    min-width: 0;
  }
  .menu-btn {
    display: inline-flex;
    order: 2;
    width: 44px;
    height: 44px;
    border-radius: 12px;
    border: 1px solid var(--bar-line);
    background: transparent;
    color: var(--bar-strong);
    align-items: center;
    justify-content: center;
  }
  .me {
    order: 3;
  }
  .bs {
    order: 4;
    width: 100%;
  }
  .links {
    order: 5;
    display: none;
    flex-basis: 100%;
    flex-direction: column;
    gap: 0;
    padding-bottom: 6px;
  }
  .links.open {
    display: flex;
  }
  .tl {
    min-height: 44px;
    border: 0;
    border-left: 3px solid transparent;
    padding-left: 10px;
  }
  .tl.on {
    border-left-color: var(--b-mark);
  }
  .tl.small {
    margin-left: 0;
  }
}
</style>
