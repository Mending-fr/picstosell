<script setup lang="ts">
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue'

const props = defineProps<{ title: string; wide?: boolean }>()
const emit = defineEmits<{ close: [] }>()
const box = ref<HTMLElement | null>(null)
const titleId = `m-${Math.random().toString(36).slice(2, 8)}`
let previous: HTMLElement | null = null

function focusables(): HTMLElement[] {
  if (!box.value) return []
  return Array.from(
    box.value.querySelectorAll<HTMLElement>('a[href],button:not([disabled]),input:not([disabled]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])'),
  )
}

function onKey(e: KeyboardEvent) {
  if (e.key === 'Escape') {
    e.stopPropagation()
    emit('close')
  } else if (e.key === 'Tab') {
    const f = focusables()
    if (!f.length) return
    const first = f[0]
    const last = f[f.length - 1]
    if (e.shiftKey && document.activeElement === first) {
      e.preventDefault()
      last.focus()
    } else if (!e.shiftKey && document.activeElement === last) {
      e.preventDefault()
      first.focus()
    }
  }
}

onMounted(async () => {
  previous = document.activeElement as HTMLElement | null
  await nextTick()
  const f = focusables()
  const target = f.find((el) => el.tagName === 'INPUT' || el.tagName === 'SELECT' || el.tagName === 'TEXTAREA') ?? f[1] ?? f[0]
  target?.focus()
})
onBeforeUnmount(() => previous?.focus?.())
void props
</script>

<template>
  <Teleport to="body">
    <div class="overlay" @mousedown.self="emit('close')" @keydown="onKey">
      <div ref="box" class="dialog" :style="wide ? { maxWidth: '720px' } : undefined" role="dialog" aria-modal="true" :aria-labelledby="titleId">
        <div class="row between" style="flex-wrap: nowrap">
          <h2 :id="titleId">{{ title }}</h2>
          <button type="button" class="icon-btn" style="border: 0; background: #f0f0ec" aria-label="Fermer" @click="emit('close')">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#15171C" stroke-width="2" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" /></svg>
          </button>
        </div>
        <slot />
      </div>
    </div>
  </Teleport>
</template>
