<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'

const props = defineProps<{
  title: string
  wide?: boolean
  /** Très large (comparaison de photos). */
  xl?: boolean
  /** Libellés des étapes : affiche les pastilles numérotées sous le titre. */
  steps?: string[]
  /** Étape courante, à partir de 1. Au-delà du nombre d'étapes (écran final), les pastilles sont masquées. */
  step?: number
}>()
const emit = defineEmits<{ close: [] }>()
const box = ref<HTMLElement | null>(null)
const body = ref<HTMLElement | null>(null)
const titleId = `m-${Math.random().toString(36).slice(2, 8)}`
let previous: HTMLElement | null = null

const showSteps = computed(() => !!props.steps?.length && (props.step ?? 1) <= props.steps.length)

function focusables(root: HTMLElement | null = box.value): HTMLElement[] {
  if (!root) return []
  return Array.from(
    root.querySelectorAll<HTMLElement>('a[href],button:not([disabled]),input:not([disabled]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])'),
  ).filter((el) => el.offsetParent !== null || el === document.activeElement)
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
    if (e.shiftKey && (document.activeElement === first || !box.value?.contains(document.activeElement))) {
      e.preventDefault()
      last.focus()
    } else if (!e.shiftKey && (document.activeElement === last || !box.value?.contains(document.activeElement))) {
      e.preventDefault()
      first.focus()
    }
  }
}

/** Place le focus sur le premier champ du corps, sinon sur le premier bouton utile. */
async function focusFirst() {
  await nextTick()
  const inBody = focusables(body.value)
  const primary = box.value?.querySelector<HTMLElement>('.dialog-foot .btn-primary:not([disabled])')
  const target =
    inBody.find((el) => el.tagName === 'INPUT' || el.tagName === 'SELECT' || el.tagName === 'TEXTAREA') ??
    inBody.find((el) => el.getAttribute('aria-checked') === 'true') ??
    primary ??
    inBody[0] ??
    focusables().slice(-1)[0]
  target?.focus()
}

watch(
  () => props.step,
  () => focusFirst(),
)

onMounted(() => {
  previous = document.activeElement as HTMLElement | null
  document.body.style.overflow = 'hidden'
  focusFirst()
})
onBeforeUnmount(() => {
  document.body.style.overflow = ''
  previous?.focus?.()
})
defineExpose({ focusFirst })
</script>

<template>
  <Teleport to="body">
    <div class="overlay" @mousedown.self="emit('close')" @keydown="onKey">
      <div ref="box" class="dialog" :style="xl ? { maxWidth: '1120px' } : wide ? { maxWidth: '720px' } : undefined" role="dialog" aria-modal="true" :aria-labelledby="titleId">
        <div class="dialog-head">
          <div class="row between" style="flex-wrap: nowrap">
            <h2 :id="titleId">{{ title }}</h2>
            <button type="button" class="dialog-close" aria-label="Fermer" @click="emit('close')">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#5A6172" stroke-width="2" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" /></svg>
            </button>
          </div>
          <ol v-if="showSteps && steps" class="steps" aria-label="Étapes" :style="{ gridTemplateColumns: `repeat(${steps.length}, minmax(0, 1fr))` }">
            <li
              v-for="(s, i) in steps"
              :key="s"
              :class="{ cur: i + 1 === (step ?? 1), done: i + 1 < (step ?? 1) }"
              :aria-current="i + 1 === (step ?? 1) ? 'step' : undefined"
            >
              <span class="mark" aria-hidden="true">
                <svg v-if="i + 1 < (step ?? 1)" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M5 12l5 5 9-10" /></svg>
                <template v-else>{{ i + 1 }}</template>
              </span>
              <span class="lab">{{ s }}<span v-if="i + 1 < (step ?? 1)" class="sr-only"> (fait)</span></span>
            </li>
          </ol>
        </div>
        <div ref="body" class="dialog-body">
          <slot />
        </div>
        <div v-if="$slots.footer" class="dialog-foot">
          <slot name="footer" />
        </div>
      </div>
    </div>
  </Teleport>
</template>
