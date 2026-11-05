<script setup lang="ts">
// Barre du haut (56 px, comme Shopylike) : logo, recherche, sélecteur de garage, compte.
// Quand une page a des changements, la barre devient « Modifications non enregistrées ».
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AppIcon from './AppIcon.vue'
import GarageSwitcher from './GarageSwitcher.vue'
import { session, isManager, logout, clearSession, toast } from '../session'
import { modals, typingIn } from '../modals'
import { saveBar } from '../saveBar'
import { errorMessage } from '../api'

const props = defineProps<{ variant: 'garage' | 'mending' }>()
const emit = defineEmits<{ menu: [] }>()
const route = useRoute()
const router = useRouter()
const meOpen = ref(false)
const q = ref('')
const searchEl = ref<HTMLInputElement | null>(null)
const meRoot = ref<HTMLElement | null>(null)

watch(
  () => route.fullPath,
  () => (meOpen.value = false),
)

const initials = computed(() =>
  (session.user?.name ?? '?')
    .split(/\s+/)
    .map((p) => p[0])
    .join('')
    .slice(0, 2)
    .toUpperCase(),
)
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

async function save() {
  try {
    await saveBar.onSave?.()
  } catch (e) {
    toast(errorMessage(e), 'error')
  }
}

// « / » ou Ctrl K place le curseur dans la recherche, partout dans l'espace garage.
function onKey(e: KeyboardEvent) {
  if (props.variant !== 'garage') return
  const ctrlK = (e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k'
  if (!ctrlK && (e.key !== '/' || e.metaKey || e.ctrlKey || e.altKey || typingIn(e))) return
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
  <header class="top">
    <div class="brand">
      <button type="button" class="burger" aria-label="Ouvrir le menu" @click="emit('menu')"><AppIcon name="menu" /></button>
      <RouterLink :to="variant === 'mending' ? '/mending' : '/'" class="logo" aria-label="Accueil">
        <span class="mark">M</span><span class="word">Mending</span>
      </RouterLink>
    </div>

    <form v-if="variant === 'garage'" class="search" role="search" @submit.prevent="search">
      <AppIcon name="search" :size="16" :stroke="2" />
      <label for="bar-q" class="sr-only">Rechercher dans le parc</label>
      <input id="bar-q" ref="searchEl" v-model="q" type="search" placeholder="Rechercher une voiture, une plaque" aria-keyshortcuts="/ Control+K" />
      <kbd>Ctrl K</kbd>
    </form>
    <span v-else />

    <div class="right">
      <GarageSwitcher :mending="variant === 'mending'" />
      <div ref="meRoot" class="me">
        <button type="button" class="avatar" :title="session.user?.name" aria-haspopup="menu" :aria-expanded="meOpen" @click="meOpen = !meOpen">
          {{ initials }}<span class="sr-only">Mon compte</span>
        </button>
        <div v-if="meOpen" class="me-pop" role="menu">
          <div style="padding: 8px 10px 10px">
            <div class="strong">{{ session.user?.name }}</div>
            <div class="small muted">{{ session.user?.email }}</div>
            <div v-if="showUsage" class="small muted" style="margin-top: 4px">Ce mois : {{ session.usage!.vehicles_month }} / {{ session.usage!.quota }} voitures</div>
          </div>
          <div class="sep" />
          <button type="button" class="opt" role="menuitem" @click="doLogout"><AppIcon name="logout" :size="16" />Se déconnecter</button>
        </div>
      </div>
    </div>

    <div v-if="saveBar.dirty" class="savebar" role="region" aria-label="Modifications non enregistrées">
      <span class="sb-msg"><AppIcon name="alert" :size="18" :stroke="2" style="color: #ffd43b" />Modifications non enregistrées</span>
      <span class="sb-acts">
        <button type="button" class="sb-btn" :disabled="saveBar.saving" @click="saveBar.onDiscard?.()">Annuler</button>
        <button type="button" class="sb-btn sb-primary" :disabled="saveBar.saving" @click="save">{{ saveBar.saving ? 'Enregistrement…' : 'Enregistrer' }}</button>
      </span>
    </div>
  </header>
</template>

<style scoped>
.top {
  grid-column: 1 / -1;
  position: sticky;
  top: 0;
  height: 56px;
  background: #1a1a1a;
  display: grid;
  grid-template-columns: 228px minmax(0, 1fr) auto;
  align-items: center;
  padding: 0 12px 0 16px;
  gap: 12px;
  z-index: 40;
}
.brand {
  display: flex;
  align-items: center;
  gap: 6px;
}
.logo {
  display: flex;
  align-items: center;
  gap: 10px;
  text-decoration: none;
}
.mark {
  width: 28px;
  height: 28px;
  border-radius: 8px;
  background: #fff;
  color: #1a1a1a;
  display: grid;
  place-items: center;
  font-weight: 700;
  font-size: 15px;
}
.word {
  color: #fff;
  font-weight: 650;
  font-size: 15px;
  letter-spacing: -0.01em;
}
.burger {
  display: none;
  width: 40px;
  height: 40px;
  border-radius: 8px;
  border: 0;
  background: transparent;
  color: #fff;
  place-items: center;
  cursor: pointer;
}
.search {
  justify-self: center;
  width: 520px;
  max-width: 100%;
  height: 36px;
  border-radius: 8px;
  background: #2b2b2b;
  border: 1px solid #474747;
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 0 6px 0 12px;
  color: #b5b5b5;
}
.search:hover {
  border-color: #6b6b6b;
}
.search:focus-within {
  border-color: #8a8a8a;
  background: #333;
}
.search input {
  border: 0;
  outline: 0;
  flex: 1;
  min-width: 0;
  background: transparent;
  color: #fff;
  font: inherit;
  font-size: 14px;
}
.search input::placeholder {
  color: #b5b5b5;
}
kbd {
  font-family: inherit;
  font-size: 12px;
  font-weight: 500;
  color: #b5b5b5;
  border: 1px solid #474747;
  border-radius: 6px;
  padding: 0 6px;
  line-height: 20px;
  background: transparent;
}
.right {
  justify-self: end;
  display: flex;
  align-items: center;
  gap: 8px;
}
.me {
  position: relative;
}
.avatar {
  width: 32px;
  height: 32px;
  border-radius: 999px;
  background: #cdfee1;
  color: #0c5132;
  display: grid;
  place-items: center;
  font-size: 12px;
  font-weight: 600;
  border: 0;
  cursor: pointer;
}
.me-pop {
  position: absolute;
  top: 44px;
  right: 0;
  width: 260px;
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 12px 32px rgba(0, 0, 0, 0.18), 0 0 0 1px rgba(0, 0, 0, 0.06);
  padding: 6px;
  color: var(--ink);
  z-index: 60;
}
.strong {
  font-weight: 600;
}
.small {
  font-size: 12px;
}
.sep {
  height: 1px;
  background: #ebebeb;
  margin: 6px 4px;
}
.opt {
  width: 100%;
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 10px;
  border-radius: 8px;
  background: transparent;
  border: 0;
  text-align: left;
  color: var(--ink);
  font: inherit;
  font-size: 14px;
  cursor: pointer;
}
.opt:hover {
  background: #f7f7f7;
}
.savebar {
  position: absolute;
  inset: 0;
  background: #1a1a1a;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 16px 0 24px;
  border-bottom: 1px solid #303030;
}
.sb-msg {
  display: flex;
  align-items: center;
  gap: 10px;
  color: #fff;
  font-weight: 600;
}
.sb-acts {
  display: flex;
  gap: 8px;
}
.sb-btn {
  height: 32px;
  padding: 0 14px;
  border-radius: 8px;
  border: 1px solid #5c5c5c;
  background: #2b2b2b;
  color: #fff;
  font: inherit;
  font-size: 14px;
  font-weight: 550;
  cursor: pointer;
}
.sb-btn:hover {
  background: #383838;
}
.sb-primary {
  border-color: #fff;
  background: #fff;
  color: #1a1a1a;
  font-weight: 600;
}
.sb-primary:hover {
  background: #ebebeb;
}
@media (max-width: 1023px) {
  .top {
    grid-template-columns: auto minmax(0, 1fr) auto;
    padding: 0 8px;
  }
  .burger {
    display: grid;
  }
  .search {
    width: 100%;
  }
  kbd {
    display: none;
  }
}
@media (max-width: 640px) {
  .word {
    display: none;
  }
  .sb-msg {
    font-size: 13px;
  }
  .savebar {
    padding: 0 8px 0 12px;
  }
}
</style>
