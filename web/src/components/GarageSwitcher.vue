<script setup lang="ts">
// Sélecteur de garage dans la barre du haut (comme le sélecteur de boutique de Shopylike).
// Super-admin : liste des garages et « Tous les garages » (espace Mending). Gérant : nom du garage, sans menu.
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AppIcon from './AppIcon.vue'
import { session, isSuper, currentGarage, switchGarage, refreshGarages } from '../session'
import { garageStatusShort } from '../labels'

const props = defineProps<{ mending: boolean }>()
const router = useRouter()
const route = useRoute()

const open = ref(false)
const q = ref('')
const active = ref(0)
const root = ref<HTMLElement | null>(null)
const search = ref<HTMLInputElement | null>(null)

const DEMO_HINT = "La recherche par plaque renvoie des données d'exemple. Les photos passent par la vraie IA."

const items = computed(() => {
  const s = q.value.trim().toLowerCase()
  return session.garages.filter((g) => !s || `${g.name} ${g.slug}`.toLowerCase().includes(s))
})
// Index 0 : « Tous les garages », puis les garages filtrés.
const count = computed(() => items.value.length + 1)

async function toggle() {
  open.value = !open.value
  q.value = ''
  if (open.value) {
    const i = items.value.findIndex((g) => !props.mending && g.id === currentGarage.value?.id)
    active.value = i >= 0 ? i + 1 : 0
    refreshGarages()
    await nextTick()
    search.value?.focus()
  }
}

async function pickAll() {
  open.value = false
  if (route.path !== '/mending' && !route.path.startsWith('/mending/')) router.push('/mending')
}

async function pick(id: number) {
  open.value = false
  q.value = ''
  const leaving = props.mending || Object.keys(route.params).length > 0
  await switchGarage(id)
  if (leaving) router.push('/')
}

function onKey(e: KeyboardEvent) {
  if (e.key === 'Escape') open.value = false
  else if (e.key === 'ArrowDown') {
    e.preventDefault()
    active.value = Math.min(count.value - 1, active.value + 1)
  } else if (e.key === 'ArrowUp') {
    e.preventDefault()
    active.value = Math.max(0, active.value - 1)
  } else if (e.key === 'Enter') {
    e.preventDefault()
    if (active.value === 0) pickAll()
    else {
      const g = items.value[active.value - 1]
      if (g) pick(g.id)
    }
  }
}

function onDocClick(e: MouseEvent) {
  if (open.value && root.value && !root.value.contains(e.target as Node)) open.value = false
}
onMounted(() => document.addEventListener('click', onDocClick))
onBeforeUnmount(() => document.removeEventListener('click', onDocClick))

function addGarage() {
  open.value = false
  router.push({ path: '/mending', query: { ajouter: '1' } })
}
</script>

<template>
  <div ref="root" class="sw">
    <template v-if="isSuper">
      <button type="button" class="sel" :class="{ open }" aria-haspopup="listbox" :aria-expanded="open" aria-label="Changer de garage" @click="toggle">
        <AppIcon v-if="mending || !currentGarage" name="store" :size="16" />
        <span v-else class="dot ring" :style="{ background: currentGarage.color }" />
        <span class="ellipsis">{{ mending || !currentGarage ? 'Tous les garages' : currentGarage.name }}</span>
        <AppIcon name="chevrons" :size="16" :stroke="2" class="chev" />
      </button>
      <div v-if="open" class="scrim" @click="open = false" />
      <div v-if="open" class="pop" role="listbox" aria-label="Garages" @keydown="onKey">
        <div class="handle" />
        <label v-if="session.garages.length >= 6" class="find">
          <AppIcon name="search" :size="14" :stroke="2" />
          <span class="sr-only">Rechercher un garage</span>
          <input ref="search" v-model="q" type="search" placeholder="Rechercher un garage" @input="active = 0" />
        </label>
        <button type="button" role="option" class="opt" :class="{ hl: active === 0 }" :aria-selected="mending" @click="pickAll" @mouseenter="active = 0">
          <span class="opt-ic all"><AppIcon name="store" :size="16" /></span>
          <span class="grow">
            <span class="strong">Tous les garages</span>
            <span class="small muted">Espace Mending · {{ session.garages.length }} garage{{ session.garages.length > 1 ? 's' : '' }}</span>
          </span>
          <AppIcon v-if="mending" name="check" :stroke="2" />
        </button>
        <div class="sep" />
        <div class="opts">
          <button
            v-for="(g, i) in items"
            :key="g.id"
            type="button"
            role="option"
            class="opt"
            :class="{ hl: active === i + 1 }"
            :aria-selected="!mending && g.id === currentGarage?.id"
            @click="pick(g.id)"
            @mouseenter="active = i + 1"
          >
            <span class="opt-ic"><span class="dot" :style="{ background: g.color }" /></span>
            <span class="grow" style="min-width: 0">
              <span class="row" style="gap: 8px; flex-wrap: nowrap">
                <span class="strong ellipsis">{{ g.name }}</span>
                <span class="st" :title="g.is_demo ? DEMO_HINT : undefined">{{ garageStatusShort(g.status) }}</span>
              </span>
              <span v-if="g.todo_count" class="small muted">{{ g.todo_count }} à faire</span>
            </span>
            <AppIcon v-if="!mending && g.id === currentGarage?.id" name="check" :stroke="2" />
          </button>
          <p v-if="!items.length" class="small muted" style="padding: 8px 10px; margin: 0">Aucun garage ne correspond.</p>
        </div>
        <div class="sep" />
        <button type="button" class="opt create" @click="addGarage"><span class="opt-ic"><AppIcon name="plus" :size="18" :stroke="2" /></span>Ajouter un garage</button>
      </div>
    </template>
    <div v-else-if="currentGarage" class="sel static" :title="currentGarage.is_demo ? DEMO_HINT : undefined">
      <span class="dot ring" :style="{ background: currentGarage.color }" />
      <span class="ellipsis">{{ currentGarage.name }}</span>
    </div>
  </div>
</template>

<style scoped>
.sw {
  position: relative;
}
.sel {
  height: 36px;
  max-width: 260px;
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 0 8px 0 10px;
  border-radius: 8px;
  border: 1px solid #474747;
  background: #2b2b2b;
  color: #fff;
  font: inherit;
  font-size: 14px;
  font-weight: 550;
  cursor: pointer;
}
.sel:hover,
.sel.open {
  background: #383838;
}
.sel.static {
  cursor: default;
  padding-right: 12px;
}
.chev {
  color: #b5b5b5;
}
.ellipsis {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.dot {
  width: 12px;
  height: 12px;
  border-radius: 6px;
  flex: none;
  display: inline-block;
}
.dot.ring {
  box-shadow: 0 0 0 2px #2b2b2b, 0 0 0 3px #555;
}
.pop {
  position: absolute;
  top: 44px;
  right: 0;
  width: 340px;
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 12px 32px rgba(0, 0, 0, 0.18), 0 0 0 1px rgba(0, 0, 0, 0.06);
  padding: 6px;
  color: var(--ink);
  z-index: 60;
}
.find {
  display: flex;
  align-items: center;
  gap: 8px;
  height: 34px;
  margin: 2px 4px 6px;
  padding: 0 10px;
  border: 1px solid #8a8a8a;
  border-radius: 8px;
  color: #8a8a8a;
}
.find input {
  border: 0;
  outline: 0;
  flex: 1;
  min-width: 0;
  font: inherit;
  font-size: 13px;
  background: transparent;
  color: var(--ink);
}
.opts {
  max-height: 360px;
  overflow: auto;
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
.opt.hl {
  background: #f7f7f7;
}
.opt-ic {
  width: 28px;
  height: 28px;
  border-radius: 8px;
  display: grid;
  place-items: center;
  flex: none;
}
.opt-ic.all {
  background: #f1f1f1;
  color: #616161;
}
.grow {
  flex: 1;
  display: flex;
  flex-direction: column;
}
.strong {
  font-weight: 600;
}
.small {
  font-size: 12px;
}
.st {
  font-size: 12px;
  font-weight: 550;
  color: #616161;
  background: #ebebeb;
  border-radius: 8px;
  padding: 1px 7px;
  white-space: nowrap;
}
.create {
  color: var(--accent-ink);
  font-weight: 550;
}
.sep {
  height: 1px;
  background: #ebebeb;
  margin: 6px 4px;
}
.handle,
.scrim {
  display: none;
}
@media (max-width: 640px) {
  .sel {
    max-width: 170px;
  }
  .scrim {
    display: block;
    position: fixed;
    inset: 0;
    background: rgba(26, 26, 26, 0.4);
    z-index: 55;
  }
  .pop {
    position: fixed;
    left: 0;
    right: 0;
    bottom: 0;
    top: auto;
    width: auto;
    border-radius: 16px 16px 0 0;
    padding: 8px 8px 24px;
    max-height: 80vh;
    overflow: auto;
  }
  .opt {
    min-height: 52px;
  }
  .handle {
    display: block;
    width: 36px;
    height: 4px;
    border-radius: 2px;
    background: #d4d4d4;
    margin: 4px auto 10px;
  }
}
</style>
