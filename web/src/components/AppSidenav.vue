<script setup lang="ts">
// Menu latéral (240 px, comme Shopylike). Sous 1024 px, il devient un tiroir ouvert par le bouton menu.
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import AppIcon from './AppIcon.vue'
import { session, isManager } from '../session'

const props = defineProps<{ variant: 'garage' | 'mending'; open: boolean }>()
const emit = defineEmits<{ close: [] }>()
const route = useRoute()

interface Sub {
  to: string | { path: string; query: Record<string, string> }
  label: string
  on: () => boolean
  badge?: number
}
interface Item {
  to: string
  label: string
  icon: string
  match: RegExp
  badge?: number
  title?: string
  subs?: Sub[]
}

const status = computed(() => (typeof route.query.status === 'string' ? route.query.status : ''))

const items = computed<Item[]>(() => {
  if (props.variant === 'mending') {
    return [
      { to: '/mending', label: "Vue d'ensemble", icon: 'chart', match: /^\/mending\/?$/ },
      { to: '/mending/comparisons', label: 'Comparer les modèles', icon: 'flask', match: /^\/mending\/comparisons/ },
      { to: '/mending/models', label: 'Réglages photo IA', icon: 'sliders', match: /^\/mending\/models/ },
      { to: '/mending/backdrops', label: 'Décors', icon: 'image', match: /^\/mending\/backdrops/ },
      { to: '/mending/users', label: 'Utilisateurs', icon: 'users', match: /^\/mending\/users/ },
    ]
  }
  const list: Item[] = [
    { to: '/', label: 'Accueil', icon: 'home', match: /^\/$/ },
    {
      to: '/vehicles',
      label: 'Parc',
      icon: 'car',
      match: /^\/vehicles/,
      badge: isManager.value ? session.toReview : undefined,
      title: 'Voitures à vérifier',
      subs: isManager.value
        ? [
            { to: { path: '/vehicles', query: { status: 'to_review' } }, label: 'À vérifier', on: () => route.path === '/vehicles' && status.value === 'to_review', badge: session.toReview },
            { to: { path: '/vehicles', query: { status: 'sold' } }, label: 'Vendues', on: () => route.path === '/vehicles' && status.value === 'sold' },
          ]
        : undefined,
    },
  ]
  if (isManager.value) {
    list.push(
      { to: '/leads', label: 'Demandes', icon: 'inbox', match: /^\/leads/, badge: session.newLeads, title: 'Nouvelles demandes' },
      { to: '/showroom', label: 'Showroom', icon: 'image', match: /^\/showroom/ },
      { to: '/site', label: 'Site', icon: 'globe', match: /^\/site/ },
    )
  }
  return list
})

function isOn(i: Item) {
  return i.match.test(route.path)
}
</script>

<template>
  <div v-if="open" class="scrim" @click="emit('close')" />
  <nav class="side" :class="{ open }" :aria-label="variant === 'mending' ? 'Menu Mending' : 'Menu principal'">
    <template v-for="i in items" :key="i.to">
      <RouterLink :to="i.to" class="ni" :class="{ on: isOn(i) && !i.subs?.some((s) => s.on()) }" :aria-current="isOn(i) ? 'page' : undefined">
        <AppIcon :name="i.icon" class="ic" />
        <span class="grow">{{ i.label }}</span>
        <span v-if="i.badge" class="cnt" :title="i.title">{{ i.badge }}</span>
      </RouterLink>
      <template v-if="i.subs && isOn(i)">
        <RouterLink v-for="s in i.subs" :key="s.label" :to="s.to" class="ns" :class="{ on: s.on() }">
          <span class="grow">{{ s.label }}</span>
          <span v-if="s.badge" class="cnt">{{ s.badge }}</span>
        </RouterLink>
      </template>
    </template>
    <div class="grow" />
    <RouterLink v-if="variant === 'garage' && isManager" to="/settings" class="ni" :class="{ on: route.path.startsWith('/settings') }">
      <AppIcon name="settings" class="ic" /><span>Réglages</span>
    </RouterLink>
  </nav>
</template>

<style scoped>
.side {
  background: #ebebeb;
  padding: 12px;
  display: flex;
  flex-direction: column;
  gap: 2px;
  border-right: 1px solid #e3e3e3;
  position: sticky;
  top: 56px;
  height: calc(100vh - 56px);
  overflow: auto;
}
.ni {
  height: 36px;
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 0 10px;
  border-radius: 8px;
  color: #303030;
  text-decoration: none;
  font-size: 14px;
}
.ni:hover {
  background: #f7f7f7;
  color: #303030;
}
.ni.on {
  background: #fff;
  font-weight: 600;
  box-shadow: 0 1px 0 rgba(0, 0, 0, 0.08);
}
.ic {
  color: #4a4a4a;
}
.ns {
  height: 32px;
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 0 10px 0 40px;
  border-radius: 8px;
  color: #616161;
  text-decoration: none;
  font-size: 14px;
}
.ns:hover {
  background: #f7f7f7;
  color: #303030;
}
.ns.on {
  color: #303030;
  font-weight: 600;
  background: #fff;
}
.grow {
  flex: 1;
}
.cnt {
  min-width: 24px;
  height: 20px;
  padding: 0 7px;
  border-radius: 999px;
  background: #fff;
  border: 1px solid #d4d4d4;
  font-size: 12px;
  font-weight: 600;
  display: grid;
  place-items: center;
  color: #303030;
}
.scrim {
  display: none;
}
@media (max-width: 1023px) {
  .side {
    position: fixed;
    left: 0;
    top: 56px;
    bottom: 0;
    width: 280px;
    height: auto;
    z-index: 45;
    transform: translateX(-100%);
    transition: transform 200ms;
  }
  .side.open {
    transform: none;
    box-shadow: 12px 0 32px rgba(0, 0, 0, 0.18);
  }
  .scrim {
    display: block;
    position: fixed;
    inset: 56px 0 0 0;
    background: rgba(26, 26, 26, 0.4);
    z-index: 44;
  }
  .ni,
  .ns {
    height: 44px;
  }
}
@media (prefers-reduced-motion: reduce) {
  .side {
    transition: none;
  }
}
</style>
