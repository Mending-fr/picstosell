// Barre « Modifications non enregistrées » : elle remplace la barre du haut tant qu'une page a des changements.
import { onBeforeUnmount, reactive, watch, type Ref } from 'vue'

export const saveBar = reactive({
  dirty: false,
  saving: false,
  onSave: null as null | (() => Promise<unknown> | unknown),
  onDiscard: null as null | (() => void),
})

/** À appeler dans une page : la barre apparaît quand `dirty` passe à vrai. */
export function useSaveBar(opts: { dirty: Ref<boolean>; saving?: Ref<boolean>; save: () => Promise<unknown> | unknown; discard: () => void }) {
  const stop = watch(
    [opts.dirty, () => opts.saving?.value ?? false],
    ([d, s]) => {
      saveBar.dirty = d
      saveBar.saving = s
      saveBar.onSave = opts.save
      saveBar.onDiscard = opts.discard
    },
    { immediate: true },
  )
  onBeforeUnmount(() => {
    stop()
    saveBar.dirty = false
    saveBar.saving = false
    saveBar.onSave = null
    saveBar.onDiscard = null
  })
}
