<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { session, isSuper, currentGarage, switchGarage, refreshGarages } from '../session'
import { garageStatusLabel } from '../labels'

const props = defineProps<{ mending: boolean }>()
const router = useRouter()
const route = useRoute()

const open = ref(false)
const q = ref('')
const active = ref(0)
const root = ref<HTMLElement | null>(null)
const search = ref<HTMLInputElement | null>(null)

interface Item {
  id: number | 'all'
  ini: string
  name: string
  sub: string
  color: string
  todo: number
}

const all = computed<Item[]>(() => [
  { id: 'all', ini: 'M', name: 'Tous les garages', sub: 'Espace Mending', color: '#3B4A6B', todo: 0 },
  ...session.garages.map((g) => ({ id: g.id, ini: g.initials, name: g.name, sub: garageStatusLabel(g.status), color: g.color, todo: g.todo_count })),
])

const items = computed(() => {
  const s = q.value.trim().toLowerCase()
  return all.value.filter((g) => !s || `${g.name} ${g.sub}`.toLowerCase().includes(s))
})

const cur = computed<Item>(() => {
  if (props.mending || !currentGarage.value) return all.value[0]
  return all.value.find((g) => g.id === currentGarage.value?.id) ?? all.value[0]
})

function isCurrent(g: Item) {
  return g.id === cur.value.id
}

async function toggle() {
  open.value = !open.value
  q.value = ''
  if (open.value) {
    active.value = Math.max(0, items.value.findIndex(isCurrent))
    refreshGarages()
    await nextTick()
    search.value?.focus()
  }
}

async function pick(g: Item) {
  open.value = false
  q.value = ''
  if (g.id === 'all') {
    if (route.path !== '/mending') router.push('/mending')
    return
  }
  const leaving = props.mending || Object.keys(route.params).length > 0
  await switchGarage(g.id)
  if (leaving) router.push('/')
}

function onKey(e: KeyboardEvent) {
  if (e.key === 'Escape') {
    open.value = false
    return
  }
  if (e.key === 'ArrowDown') {
    e.preventDefault()
    active.value = Math.min(items.value.length - 1, active.value + 1)
  } else if (e.key === 'ArrowUp') {
    e.preventDefault()
    active.value = Math.max(0, active.value - 1)
  } else if (e.key === 'Enter') {
    e.preventDefault()
    const g = items.value[active.value]
    if (g) pick(g)
  }
}

function onDocClick(e: MouseEvent) {
  if (open.value && root.value && !root.value.contains(e.target as Node)) open.value = false
}
function onGlobalKey(e: KeyboardEvent) {
  if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
    e.preventDefault()
    if (!open.value) toggle()
  }
}
onMounted(() => {
  document.addEventListener('click', onDocClick)
  if (isSuper.value) document.addEventListener('keydown', onGlobalKey)
})
onBeforeUnmount(() => {
  document.removeEventListener('click', onDocClick)
  document.removeEventListener('keydown', onGlobalKey)
})

function addGarage() {
  open.value = false
  router.push({ path: '/mending', query: { ajouter: '1' } })
}
</script>

<template>
  <div ref="root" class="sw">
    <template v-if="isSuper">
      <button
        type="button"
        class="sw-btn"
        :class="{ open }"
        aria-haspopup="listbox"
        :aria-expanded="open"
        aria-label="Changer de garage"
        @click="toggle"
      >
        <span class="ini" :style="{ background: cur.color }">{{ cur.ini }}</span>
        <span class="txt">
          <span class="name">{{ cur.name }}</span>
          <span class="sub">{{ cur.sub }}</span>
        </span>
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#A9AEB8" stroke-width="2" aria-hidden="true"><path d="M8 9l4-4 4 4M8 15l4 4 4-4" /></svg>
      </button>
      <div v-if="open" class="sw-pop" @keydown="onKey">
        <label class="sw-search">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#A9AEB8" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7" /><path d="M20 20l-4-4" /></svg>
          <span class="sr-only">Chercher un garage</span>
          <input ref="search" v-model="q" type="search" placeholder="Rechercher" @input="active = 0" />
          <span class="k">⌘K</span>
        </label>
        <div role="listbox" aria-label="Dossiers clients" class="sw-list">
          <button
            v-for="(g, i) in items"
            :key="g.id"
            type="button"
            role="option"
            :aria-selected="isCurrent(g)"
            class="sw-opt"
            :class="{ on: isCurrent(g), hl: i === active }"
            @click="pick(g)"
            @mouseenter="active = i"
          >
            <span class="ini sm" :style="{ background: g.color }">{{ g.ini }}</span>
            <span class="txt">
              <span class="name sm">{{ g.name }}</span>
              <span class="sub sm">{{ g.sub }}</span>
            </span>
            <span v-if="g.todo > 0" class="todo" :aria-label="`${g.todo} choses à faire`">{{ g.todo }}</span>
          </button>
          <span v-if="!items.length" class="none">Aucun garage trouvé</span>
        </div>
        <div class="sw-foot">
          <button type="button" @click="addGarage">+ Ajouter un garage</button>
        </div>
      </div>
    </template>
    <div v-else-if="currentGarage" class="sw-btn static">
      <span class="ini" :style="{ background: currentGarage.color }">{{ currentGarage.initials }}</span>
      <span class="txt">
        <span class="name">{{ currentGarage.name }}</span>
        <span class="sub">{{ garageStatusLabel(currentGarage.status) }}</span>
      </span>
    </div>
  </div>
</template>

<style scoped>
.sw {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding-bottom: 16px;
  position: relative;
  flex: 1 1 auto;
  min-width: 0;
  width: 100%;
}
.sw-btn {
  color: #fff;
  background: transparent;
  border: 1px solid var(--side-line);
  border-radius: 10px;
  padding: 8px 10px;
  min-height: 56px;
  display: flex;
  align-items: center;
  gap: 10px;
  text-align: left;
  width: 100%;
}
.sw-btn.open,
.sw-btn:not(.static):hover {
  background: var(--side-hover);
}
.ini {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 13px;
  flex-shrink: 0;
  color: #fff;
}
.ini.sm {
  width: 26px;
  height: 26px;
  border-radius: 6px;
  font-size: 11px;
}
.txt {
  display: flex;
  flex-direction: column;
  flex: 1;
  min-width: 0;
}
.name {
  font-weight: 600;
  font-size: 14px;
  line-height: 1.25;
  overflow: hidden;
  display: -webkit-box;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
  overflow-wrap: anywhere;
}
.name.sm {
  font-size: 13px;
}
.sub {
  font-size: 12px;
  color: var(--side-muted);
}
.sub.sm {
  font-size: 11px;
}
.sw-pop {
  background: var(--side-hover);
  border: 1px solid var(--side-line);
  border-radius: 10px;
  padding: 8px;
  display: flex;
  flex-direction: column;
  gap: 4px;
  position: absolute;
  top: calc(100% - 8px);
  left: 0;
  right: 0;
  z-index: 30;
  box-shadow: 0 12px 30px rgba(0, 0, 0, 0.35);
}
.sw-search {
  display: flex;
  align-items: center;
  gap: 8px;
  background: #15171c;
  border: 1px solid #3a3d44;
  border-radius: 8px;
  padding: 0 10px;
  height: 40px;
}
.sw-search input {
  border: 0;
  outline: 0;
  flex: 1;
  min-width: 0;
  background: transparent;
  color: #fff;
  font: inherit;
  font-size: 14px;
}
.k {
  font-family: var(--mono);
  font-size: 11px;
  color: var(--side-muted);
  border: 1px solid #3a3d44;
  border-radius: 4px;
  padding: 0 5px;
}
.sw-list {
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding-top: 4px;
  max-height: 360px;
  overflow-y: auto;
}
.sw-opt {
  color: #fff;
  background: transparent;
  border: 0;
  border-radius: 8px;
  padding: 8px;
  min-height: 44px;
  display: flex;
  align-items: center;
  gap: 10px;
  text-align: left;
  width: 100%;
}
.sw-opt.hl {
  background: #2a2d35;
}
.sw-opt.on {
  background: var(--side-line);
}
.todo {
  font-size: 11px;
  font-weight: 700;
  background: var(--orange);
  color: #fff;
  border-radius: 999px;
  padding: 1px 7px;
}
.none {
  font-size: 13px;
  color: var(--side-muted);
  padding: 8px;
}
.sw-foot {
  border-top: 1px solid var(--side-line);
  margin-top: 4px;
  padding-top: 6px;
}
.sw-foot button {
  color: var(--side-text);
  background: none;
  border: 0;
  font-size: 13px;
  padding: 10px 8px;
  width: 100%;
  text-align: left;
  min-height: 40px;
}
.sw-foot button:hover {
  color: #fff;
}
</style>
