<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue'

export interface ViewerItem {
  url: string
  label: string
  /** Deuxième ligne de la légende (état, modèle, coût…). */
  sub?: string
}

const props = defineProps<{ items: ViewerItem[]; start?: number }>()
const emit = defineEmits<{ close: [] }>()

const i = ref(Math.min(Math.max(props.start ?? 0, 0), Math.max(props.items.length - 1, 0)))
const cur = computed(() => props.items[i.value] ?? null)
const closeBtn = ref<HTMLButtonElement | null>(null)
let previous: HTMLElement | null = null

function go(step: number) {
  const n = props.items.length
  if (n) i.value = (i.value + step + n) % n
}

// Capture sur window : les flèches et Échap ne vont pas aux raccourcis de la page dessous.
function onKey(e: KeyboardEvent) {
  if (e.key === 'Escape') emit('close')
  else if (e.key === 'ArrowRight') go(1)
  else if (e.key === 'ArrowLeft') go(-1)
  else if (e.key !== 'Tab') return
  if (e.key !== 'Tab') {
    e.preventDefault()
    e.stopPropagation()
  }
}

onMounted(async () => {
  previous = document.activeElement as HTMLElement | null
  window.addEventListener('keydown', onKey, true)
  document.body.style.overflow = 'hidden'
  await nextTick()
  closeBtn.value?.focus()
})
onBeforeUnmount(() => {
  window.removeEventListener('keydown', onKey, true)
  document.body.style.overflow = ''
  previous?.focus?.()
})
</script>

<template>
  <Teleport to="body">
    <div class="iv" role="dialog" aria-modal="true" aria-label="Photo en grand" @click.self="emit('close')">
      <button ref="closeBtn" type="button" class="iv-btn iv-x" aria-label="Fermer" @click="emit('close')">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" /></svg>
      </button>
      <button v-if="items.length > 1" type="button" class="iv-btn iv-prev" aria-label="Photo précédente" @click="go(-1)">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M15 6l-6 6 6 6" /></svg>
      </button>
      <figure v-if="cur" class="iv-fig" @click.self="emit('close')">
        <img :key="cur.url" :src="cur.url" :alt="cur.label" class="iv-img" />
        <figcaption class="iv-cap">
          <span class="iv-label">{{ cur.label }}</span>
          <span v-if="cur.sub" class="iv-sub">{{ cur.sub }}</span>
          <span v-if="items.length > 1" class="iv-n">{{ i + 1 }} / {{ items.length }}</span>
        </figcaption>
      </figure>
      <button v-if="items.length > 1" type="button" class="iv-btn iv-next" aria-label="Photo suivante" @click="go(1)">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 6l6 6-6 6" /></svg>
      </button>
    </div>
  </Teleport>
</template>

<style scoped>
.iv {
  position: fixed;
  inset: 0;
  z-index: 80;
  background: rgba(10, 11, 13, 0.94);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px 88px;
}
.iv-fig {
  margin: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  max-width: 100%;
  max-height: 100%;
}
.iv-img {
  max-width: 100%;
  max-height: calc(100vh - 120px);
  object-fit: contain;
  border-radius: 10px;
  display: block;
}
.iv-cap {
  display: flex;
  gap: 14px;
  align-items: baseline;
  flex-wrap: wrap;
  justify-content: center;
  color: #d9dbe0;
  font-size: 14px;
}
.iv-label {
  color: #fff;
  font-weight: 500;
}
.iv-sub,
.iv-n {
  color: #a8acb5;
}
.iv-btn {
  position: absolute;
  width: 48px;
  height: 48px;
  border-radius: 24px;
  border: 0;
  background: rgba(255, 255, 255, 0.12);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}
.iv-btn:hover {
  background: rgba(255, 255, 255, 0.22);
}
.iv-x {
  top: 16px;
  right: 16px;
}
.iv-prev {
  left: 20px;
  top: 50%;
  transform: translateY(-50%);
}
.iv-next {
  right: 20px;
  top: 50%;
  transform: translateY(-50%);
}
@media (max-width: 640px) {
  .iv {
    padding: 64px 8px 24px;
  }
  .iv-prev,
  .iv-next {
    top: auto;
    bottom: 16px;
    transform: none;
  }
}
</style>
