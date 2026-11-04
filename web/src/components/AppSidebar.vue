<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import GarageSwitcher from './GarageSwitcher.vue'
import { session, isSuper, isManager, logout, clearSession } from '../session'

const props = defineProps<{ variant: 'garage' | 'mending' }>()
const route = useRoute()
const router = useRouter()
const menuOpen = ref(false)
watch(
  () => route.fullPath,
  () => (menuOpen.value = false),
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
    { to: '/vehicles', label: 'Mon stock' },
  ]
  if (isManager.value) {
    l.push(
      { to: '/leads', label: 'Demandes reçues', badge: session.newLeads },
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

const usagePct = computed(() => {
  const u = session.usage
  if (!u || !u.quota) return 0
  return Math.min(100, Math.round((u.vehicles_month / u.quota) * 100))
})

async function doLogout() {
  await logout()
  await router.replace('/login')
  clearSession()
}
</script>

<template>
  <nav class="side" :class="variant" :aria-label="variant === 'mending' ? 'Navigation Mending' : 'Navigation principale'">
    <div class="top">
      <GarageSwitcher :mending="variant === 'mending'" class="grow" />
      <button type="button" class="menu-btn" :aria-expanded="menuOpen" aria-controls="side-links" @click="menuOpen = !menuOpen">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16" /></svg>
        <span class="sr-only">Menu</span>
      </button>
    </div>
    <div id="side-links" class="links" :class="{ open: menuOpen }">
      <RouterLink v-for="l in links" :key="l.to" :to="l.to" class="nl" :class="{ on: isOn(l) }" :aria-current="isOn(l) ? 'page' : undefined">
        {{ l.label }}<span v-if="l.badge" class="badge" :aria-label="`${l.badge} nouvelles`">{{ l.badge }}</span>
      </RouterLink>

      <div v-if="variant === 'garage' && session.usage && isManager" class="usage">
        <span class="u-sub">Ce mois-ci</span>
        <span class="u-big">{{ session.usage.vehicles_month }} {{ session.usage.vehicles_month > 1 ? 'voitures' : 'voiture' }}</span>
        <div class="bar" role="progressbar" :aria-valuenow="usagePct" aria-valuemin="0" aria-valuemax="100" aria-label="Voitures du mois">
          <div :style="{ width: usagePct + '%' }" />
        </div>
        <span class="u-sub">sur {{ session.usage.quota }} comprises dans l'abonnement</span>
      </div>

      <div class="bottom" :class="{ 'no-usage': variant === 'mending' || !session.usage }">
        <RouterLink v-if="variant === 'garage' && isSuper" to="/mending" class="nl small">Espace Mending</RouterLink>
        <RouterLink v-if="variant === 'mending'" to="/" class="nl small">‹ Retour à l'espace garage</RouterLink>
        <div class="me">
          <span class="me-name">{{ session.user?.name }}</span>
          <button type="button" class="out" @click="doLogout">Se déconnecter</button>
        </div>
      </div>
    </div>
  </nav>
</template>

<style scoped>
.side {
  flex: 0 0 240px;
  width: 240px;
  background: var(--side);
  color: #fff;
  padding: 24px 16px;
  display: flex;
  flex-direction: column;
  gap: 4px;
  position: sticky;
  top: 0;
  align-self: flex-start;
  height: 100vh;
  overflow-y: auto;
}
.side.mending {
  background: var(--side-mending);
}
.top {
  display: flex;
  gap: 8px;
  align-items: flex-start;
}
.menu-btn {
  display: none;
}
.links {
  display: flex;
  flex-direction: column;
  gap: 4px;
  flex: 1;
}
.nl {
  display: flex;
  justify-content: space-between;
  align-items: center;
  color: var(--side-text);
  text-decoration: none;
  border-radius: 8px;
  padding: 10px 12px;
  font-size: 14px;
  min-height: 44px;
}
.nl:hover {
  color: #fff;
  background: var(--side-hover);
}
.nl.on {
  background: var(--side-on);
  color: #fff;
  font-weight: 600;
}
.nl.small {
  font-size: 13px;
}
.usage {
  margin-top: auto;
  padding: 12px;
  border: 1px solid var(--side-line);
  border-radius: 10px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.u-sub {
  font-size: 12px;
  color: var(--side-muted);
}
.u-big {
  font-size: 20px;
  font-weight: 700;
}
.bar {
  height: 6px;
  background: var(--side-line);
  border-radius: 3px;
}
.bar div {
  height: 6px;
  background: #4c7bff;
  border-radius: 3px;
}
.bottom {
  display: flex;
  flex-direction: column;
  gap: 4px;
  margin-top: 8px;
}
.bottom.no-usage {
  margin-top: auto;
}
.me {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 8px;
  padding: 8px 12px 0;
  border-top: 1px solid var(--side-line);
  margin-top: 4px;
}
.me-name {
  font-size: 12px;
  color: var(--side-muted);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.out {
  background: none;
  border: 0;
  color: var(--side-text);
  font-size: 12px;
  min-height: 40px;
  padding: 0;
  text-decoration: underline;
}
.out:hover {
  color: #fff;
}
@media (max-width: 860px) {
  .side {
    width: 100%;
    flex: none;
    height: auto;
    position: static;
    padding: 12px 16px;
  }
  .top {
    align-items: center;
  }
  .top :deep(.sw) {
    padding-bottom: 0;
  }
  .menu-btn {
    display: inline-flex;
    width: 56px;
    height: 56px;
    border-radius: 10px;
    border: 1px solid var(--side-line);
    background: transparent;
    color: #fff;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
  }
  .links {
    display: none;
    padding-top: 12px;
  }
  .links.open {
    display: flex;
  }
  .usage {
    margin-top: 8px;
  }
}
</style>
