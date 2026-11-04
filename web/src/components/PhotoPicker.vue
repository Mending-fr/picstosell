<script setup lang="ts">
import { onBeforeUnmount, ref } from 'vue'
import { SHOTS } from '../labels'
import type { ShotKey } from '../types'

export interface PickedPhoto {
  id: number
  file: File
  preview: string
  /** « auto » : l'IA dit ce que montre la photo à l'envoi. */
  slot: ShotKey | 'interior' | 'detail' | 'auto'
  state?: 'waiting' | 'sending' | 'done' | 'error'
  error?: string
}

const props = defineProps<{ modelValue: PickedPhoto[]; taken?: string[]; disabled?: boolean }>()
const emit = defineEmits<{ 'update:modelValue': [v: PickedPhoto[]] }>()

const input = ref<HTMLInputElement | null>(null)
const over = ref(false)
const rejected = ref('')
let nextId = 1


function add(files: FileList | File[]) {
  const list = [...props.modelValue]
  let skipped = 0
  for (const f of Array.from(files)) {
    if (!f.type.startsWith('image/')) {
      skipped++
      continue
    }
    list.push({ id: nextId++, file: f, preview: URL.createObjectURL(f), slot: 'auto', state: 'waiting' })
  }
  rejected.value = skipped ? `${skipped} fichier${skipped > 1 ? 's' : ''} ignoré${skipped > 1 ? 's' : ''} : seules les photos sont acceptées.` : ''
  emit('update:modelValue', list)
}

function onPick(e: Event) {
  const t = e.target as HTMLInputElement
  if (t.files) add(t.files)
  t.value = ''
}
function onDrop(e: DragEvent) {
  over.value = false
  if (props.disabled) return
  if (e.dataTransfer?.files) add(e.dataTransfer.files)
}
function remove(p: PickedPhoto) {
  URL.revokeObjectURL(p.preview)
  emit(
    'update:modelValue',
    props.modelValue.filter((x) => x !== p),
  )
}
function setSlot(p: PickedPhoto, slot: string) {
  emit(
    'update:modelValue',
    props.modelValue.map((x) => (x === p ? { ...x, slot: slot as PickedPhoto['slot'] } : x)),
  )
}
onBeforeUnmount(() => props.modelValue.forEach((p) => URL.revokeObjectURL(p.preview)))
</script>

<template>
  <div class="col" style="gap: 12px">
    <div
      class="drop"
      :class="{ over }"
      @dragover.prevent="over = true"
      @dragleave.prevent="over = false"
      @drop.prevent="onDrop"
    >
      Glissez les photos ici ou
      <button type="button" class="pick" :disabled="disabled" @click="input?.click()">choisissez-les</button>
      <input ref="input" type="file" accept="image/*" multiple class="sr-only" tabindex="-1" aria-hidden="true" @change="onPick" />
      <span class="muted" style="display: block; font-size: 13px; margin-top: 6px">
        Toutes les photos, dans n'importe quel ordre : l'extérieur va dans le showroom, l'intérieur et les détails restent tels quels.
      </span>
    </div>
    <p v-if="rejected" class="err" style="margin: 0">{{ rejected }}</p>
    <ul v-if="modelValue.length" class="list" aria-label="Photos choisies">
      <li v-for="(p, i) in modelValue" :key="p.id" class="item" :class="p.state">
        <div class="img-wrap">
          <img :src="p.preview" :alt="`Photo ${i + 1}`" />
          <span v-if="p.state === 'sending'" class="st"><span class="spinner" aria-hidden="true" /> Envoi…</span>
          <span v-else-if="p.state === 'done'" class="st ok">Envoyée</span>
          <span v-else-if="p.state === 'error'" class="st ko">Échec</span>
        </div>
        <label class="lbl" style="font-size: 12px">
          <span class="sr-only">Angle de la photo {{ i + 1 }}</span>
          <select class="f f-sm" :value="p.slot" :disabled="disabled || p.state === 'done'" @change="setSlot(p, ($event.target as HTMLSelectElement).value)">
            <option value="auto">L'IA reconnaît la photo</option>
            <optgroup label="Extérieur (placée dans le showroom)">
              <option v-for="s in SHOTS" :key="s.key" :value="s.key">{{ s.label }}</option>
            </optgroup>
            <optgroup label="Telle quelle">
              <option value="interior">Intérieur</option>
              <option value="detail">Détail (compteur, jantes…)</option>
            </optgroup>
          </select>
        </label>
        <span v-if="p.error" class="err" style="font-size: 12px">{{ p.error }}</span>
        <button v-if="p.state !== 'done'" type="button" class="btn-link" style="min-height: 36px; font-size: 13px; color: var(--red-ink)" :disabled="disabled" @click="remove(p)">
          Retirer
        </button>
      </li>
    </ul>
  </div>
</template>

<style scoped>
.drop {
  border: 1.5px dashed var(--accent-line);
  border-radius: 16px;
  padding: 26px;
  text-align: center;
  color: var(--ink-2);
  font-size: 15px;
  background: var(--accent-band);
}
.drop.over {
  border-color: var(--accent);
  background: var(--accent-tint);
}
.pick {
  font-weight: 500;
  color: var(--accent-ink);
  background: none;
  border: 0;
  text-decoration: underline;
  padding: 0;
  min-height: 0;
  font-size: 15px;
}
.list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
  gap: 12px;
}
.item {
  display: flex;
  flex-direction: column;
  gap: 6px;
  background: #fff;
  border: 1px solid var(--line);
  border-radius: 14px;
  padding: 6px;
}
.item.error {
  border-color: var(--red-ink);
}
.img-wrap {
  position: relative;
}
.img-wrap img {
  width: 100%;
  aspect-ratio: 4/3;
  object-fit: cover;
  border-radius: 10px;
}
.st {
  position: absolute;
  left: 6px;
  top: 6px;
  font-size: 12px;
  font-weight: 500;
  background: rgba(255, 255, 255, 0.92);
  border-radius: 6px;
  padding: 3px 8px;
  display: flex;
  gap: 6px;
  align-items: center;
}
.st.ok {
  color: var(--green-ink);
}
.st.ko {
  color: var(--red-ink);
}
</style>
