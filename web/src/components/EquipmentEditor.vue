<script setup lang="ts">
import { ref } from 'vue'
import type { EquipmentGroup } from '../types'

const props = defineProps<{ modelValue: EquipmentGroup[] }>()
const emit = defineEmits<{ 'update:modelValue': [v: EquipmentGroup[]] }>()
const drafts = ref<Record<number, string>>({})
const SUGGESTED = ['Confort', 'Sécurité', 'Multimédia', 'Extérieur', 'Intérieur']

function update(list: EquipmentGroup[]) {
  emit('update:modelValue', list)
}
function rename(i: number, name: string) {
  update(props.modelValue.map((g, j) => (j === i ? { ...g, group: name } : g)))
}
function addItem(i: number) {
  const text = (drafts.value[i] ?? '').trim()
  if (!text) return
  const parts = text.split(/[,;]\s*/).map((x) => x.trim()).filter(Boolean)
  update(props.modelValue.map((g, j) => (j === i ? { ...g, items: [...g.items, ...parts.filter((p) => !g.items.includes(p))] } : g)))
  drafts.value[i] = ''
}
function removeItem(i: number, item: string) {
  update(props.modelValue.map((g, j) => (j === i ? { ...g, items: g.items.filter((x) => x !== item) } : g)))
}
function removeGroup(i: number) {
  update(props.modelValue.filter((_, j) => j !== i))
}
function addGroup(name = '') {
  update([...props.modelValue, { group: name, items: [] }])
}
const unused = () => SUGGESTED.filter((s) => !props.modelValue.some((g) => g.group.toLowerCase() === s.toLowerCase()))
</script>

<template>
  <div class="eq">
    <div v-for="(g, i) in modelValue" :key="i" class="grp">
      <div class="row" style="flex-wrap: nowrap">
        <label class="lbl grow">
          <span class="sr-only">Nom du groupe</span>
          <input class="f" style="min-height: 40px; font-weight: 600" :value="g.group" placeholder="Nom du groupe, ex. Confort" @input="rename(i, ($event.target as HTMLInputElement).value)" />
        </label>
        <button type="button" class="btn btn-sm btn-danger" :aria-label="`Supprimer le groupe ${g.group}`" @click="removeGroup(i)">Supprimer</button>
      </div>
      <ul v-if="g.items.length" class="chips">
        <li v-for="it in g.items" :key="it" class="chip">
          {{ it }}
          <button type="button" :aria-label="`Retirer ${it}`" @click="removeItem(i, it)">×</button>
        </li>
      </ul>
      <form class="row" style="flex-wrap: nowrap" @submit.prevent="addItem(i)">
        <label class="lbl grow">
          <span class="sr-only">Ajouter un équipement au groupe {{ g.group }}</span>
          <input v-model="drafts[i]" class="f" style="min-height: 40px" placeholder="Ajouter, ex. Climatisation" />
        </label>
        <button type="submit" class="btn btn-sm">Ajouter</button>
      </form>
    </div>
    <div class="row">
      <button v-for="s in unused()" :key="s" type="button" class="btn btn-sm" @click="addGroup(s)">+ {{ s }}</button>
      <button type="button" class="btn btn-sm" @click="addGroup()">+ Autre groupe</button>
    </div>
  </div>
</template>

<style scoped>
.eq {
  display: flex;
  flex-direction: column;
  gap: 12px;
  background: var(--ground);
  border-radius: 12px;
  padding: 12px;
}
.grp {
  display: flex;
  flex-direction: column;
  gap: 8px;
  background: #fff;
  border: 1px solid var(--line);
  border-radius: 10px;
  padding: 10px;
}
.chips {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}
.chip {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  background: #eeeeea;
  border-radius: 999px;
  padding: 2px 4px 2px 12px;
  font-size: 13px;
}
.chip button {
  border: 0;
  background: none;
  width: 28px;
  height: 28px;
  border-radius: 14px;
  font-size: 16px;
  color: var(--ink-2);
}
.chip button:hover {
  background: #dcdcd6;
}
</style>
