<script setup lang="ts">
// Bouton « Plus d'actions » avec son menu, pour ranger les actions secondaires d'une page.
import { onBeforeUnmount, ref } from 'vue'

export interface MenuItem {
  label: string
  action: () => void
  danger?: boolean
  disabled?: boolean
}
defineProps<{ items: MenuItem[]; label?: string }>()

const open = ref(false)
const root = ref<HTMLElement | null>(null)
function onDoc(e: MouseEvent) {
  if (root.value && !root.value.contains(e.target as Node)) open.value = false
}
function onKey(e: KeyboardEvent) {
  if (e.key === 'Escape') open.value = false
}
function toggle() {
  open.value = !open.value
  if (open.value) {
    document.addEventListener('mousedown', onDoc)
    document.addEventListener('keydown', onKey)
  } else stop()
}
function stop() {
  document.removeEventListener('mousedown', onDoc)
  document.removeEventListener('keydown', onKey)
}
function run(i: MenuItem) {
  open.value = false
  stop()
  i.action()
}
onBeforeUnmount(stop)
</script>

<template>
  <div ref="root" class="mm">
    <button type="button" class="btn" :aria-expanded="open" aria-haspopup="menu" @click="toggle">
      {{ label ?? "Plus d'actions" }}
      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path d="M6 9l6 6 6-6" /></svg>
    </button>
    <div v-if="open" class="mm-pop" role="menu">
      <button v-for="i in items" :key="i.label" type="button" role="menuitem" class="mm-i" :class="{ danger: i.danger }" :disabled="i.disabled" @click="run(i)">{{ i.label }}</button>
    </div>
  </div>
</template>

<style scoped>
.mm {
  position: relative;
}
.mm-pop {
  position: absolute;
  right: 0;
  top: calc(100% + 4px);
  z-index: 30;
  min-width: 220px;
  background: #fff;
  border-radius: 12px;
  padding: 6px;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.16), 0 0 0 1px rgba(0, 0, 0, 0.06);
  display: flex;
  flex-direction: column;
}
.mm-i {
  text-align: left;
  border: 0;
  background: none;
  border-radius: 8px;
  padding: 7px 10px;
  font-size: 13px;
  color: var(--ink);
}
.mm-i:hover:not(:disabled) {
  background: #f1f1f1;
}
.mm-i.danger {
  color: var(--red-ink);
}
</style>
