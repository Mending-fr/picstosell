<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { ApiError, get, patch, post, errorMessage } from '../../api'
import { METHODS, PROVIDERS, usd } from '../../labels'
import { toast } from '../../session'
import type { AiConfig } from '../../types'

const list = ref<AiConfig[] | null>(null)
const error = ref('')
const editing = ref<number | 'new' | null>(null)
const form = reactive({ name: '', provider: 'fake', model: '', method: 'B', quality: '2K', prompt: '', unit_cost_usd: '0.000', active: true })
const errors = ref<Record<string, string>>({})
const saving = ref(false)

async function load() {
  error.value = ''
  try {
    list.value = (await get<{ data: AiConfig[] }>('/admin/ai-configs')).data
  } catch (e) {
    error.value = errorMessage(e)
  }
}
onMounted(load)

function edit(c: AiConfig | null) {
  errors.value = {}
  if (c) {
    editing.value = c.id
    Object.assign(form, { name: c.name, provider: c.provider, model: c.model, method: c.method, quality: c.quality, prompt: c.prompt ?? '', unit_cost_usd: c.unit_cost_usd, active: c.active })
  } else {
    editing.value = 'new'
    Object.assign(form, { name: '', provider: 'openai', model: '', method: 'B', quality: '2K', prompt: '', unit_cost_usd: '0.000', active: true })
  }
  setTimeout(() => document.getElementById('cfg-name')?.focus(), 0)
}

async function save() {
  errors.value = {}
  if (!form.name.trim()) errors.value.name = 'Donnez un nom à ce réglage.'
  if (!form.model.trim()) errors.value.model = "Indiquez l'identifiant du modèle."
  const cost = Number(String(form.unit_cost_usd).replace(',', '.'))
  if (Number.isNaN(cost) || cost < 0) errors.value.unit_cost_usd = 'Coût en dollars, par exemple 0,014.'
  if (Object.keys(errors.value).length) return
  saving.value = true
  const body = { ...form, name: form.name.trim(), model: form.model.trim(), unit_cost_usd: cost }
  try {
    if (editing.value === 'new') {
      const c = await post<AiConfig>('/admin/ai-configs', body)
      list.value = [...(list.value ?? []), c]
      toast('Réglage créé.')
    } else if (editing.value) {
      const c = await patch<AiConfig>(`/admin/ai-configs/${editing.value}`, body)
      list.value = (list.value ?? []).map((x) => (x.id === c.id ? c : x))
      toast('Réglage enregistré.')
    }
    editing.value = null
  } catch (e) {
    if (e instanceof ApiError && e.status === 422) for (const [k, m] of Object.entries(e.errors)) errors.value[k] = m[0]
    else toast(errorMessage(e), 'error')
  } finally {
    saving.value = false
  }
}

async function toggleActive(c: AiConfig) {
  c.active = !c.active
  try {
    await patch(`/admin/ai-configs/${c.id}`, { active: c.active })
  } catch (e) {
    c.active = !c.active
    toast(errorMessage(e), 'error')
  }
}
const providerLabel = (p: string) => PROVIDERS.find((x) => x.value === p)?.label ?? p
</script>

<template>
  <div class="page-head">
    <div class="col" style="gap: 4px">
      <h1>Réglages photo IA</h1>
      <p class="lead" style="margin: 0">Chaque réglage associe un fournisseur, un modèle, une méthode et une consigne. On l'attribue ensuite à un garage.</p>
    </div>
    <button type="button" class="btn btn-primary" @click="edit(null)">Nouveau réglage</button>
  </div>

  <p v-if="error" class="alert alert-error" role="alert">{{ error }} <button type="button" class="btn-link" @click="load">Réessayer</button></p>

  <div class="split">
    <div class="table-wrap" style="flex: 999 1 560px; min-width: 0">
      <table class="t" style="min-width: 680px">
        <thead>
          <tr><th>Nom</th><th>Fournisseur</th><th>Méthode</th><th>Qualité</th><th>Prix / image</th><th>Actif</th><th><span class="sr-only">Modifier</span></th></tr>
        </thead>
        <tbody v-if="list">
          <tr v-for="c in list" :key="c.id" :style="{ opacity: c.active ? 1 : 0.6 }">
            <td>
              <div class="col" style="gap: 2px">
                <span style="font-weight: 500">{{ c.name }}</span>
                <span class="mono muted">{{ c.model }}</span>
              </div>
            </td>
            <td>{{ providerLabel(c.provider) }}</td>
            <td>{{ c.method }}</td>
            <td>{{ c.quality }}</td>
            <td class="mono" style="font-size: 13px">{{ usd(c.unit_cost_usd, 3) }}</td>
            <td>
              <label class="row" style="gap: 6px; cursor: pointer">
                <input type="checkbox" :checked="c.active" style="width: 20px; height: 20px" @change="toggleActive(c)" />
                <span class="sr-only">Actif</span>
              </label>
            </td>
            <td style="text-align: right"><button type="button" class="btn-link" @click="edit(c)">Modifier</button></td>
          </tr>
        </tbody>
        <tbody v-else-if="!error">
          <tr v-for="i in 3" :key="i"><td colspan="7"><span class="skel" style="display: block; height: 20px" /></td></tr>
        </tbody>
      </table>
      <div v-if="list && !list.length" class="empty">
        <strong>Aucun réglage</strong>
        <button type="button" class="btn btn-primary" @click="edit(null)">Créer le premier réglage</button>
      </div>
    </div>

    <form v-if="editing !== null" class="card" style="flex: 1 1 360px; min-width: 0" novalidate @submit.prevent="save">
      <div class="row between">
        <h2 style="font-size: 18px">{{ editing === 'new' ? 'Nouveau réglage' : 'Modifier le réglage' }}</h2>
        <button type="button" class="btn-link" @click="editing = null">Fermer</button>
      </div>
      <label class="lbl">Nom<input id="cfg-name" v-model="form.name" class="f" placeholder="Ex. GPT Image, méthode B" :aria-invalid="!!errors.name" /><span v-if="errors.name" class="err">{{ errors.name }}</span></label>
      <div class="grid-fields">
        <label class="lbl">
          Fournisseur
          <select v-model="form.provider" class="f">
            <option v-for="p in PROVIDERS" :key="p.value" :value="p.value">{{ p.label }}</option>
          </select>
        </label>
        <label class="lbl">Identifiant du modèle<input v-model="form.model" class="f mono" style="font-size: 14px" placeholder="gpt-image-1" :aria-invalid="!!errors.model" /><span v-if="errors.model" class="err">{{ errors.model }}</span></label>
        <label class="lbl">
          Méthode
          <select v-model="form.method" class="f">
            <option v-for="m in METHODS" :key="m.value" :value="m.value">{{ m.label }}</option>
          </select>
        </label>
        <label class="lbl">
          Qualité
          <select v-model="form.quality" class="f">
            <option value="1K">1K</option>
            <option value="2K">2K</option>
          </select>
        </label>
        <label class="lbl">Prix par image ($)<input v-model="form.unit_cost_usd" class="f" inputmode="decimal" :aria-invalid="!!errors.unit_cost_usd" /><span v-if="errors.unit_cost_usd" class="err">{{ errors.unit_cost_usd }}</span></label>
      </div>
      <label class="lbl">Consigne envoyée au modèle<textarea v-model="form.prompt" class="f" rows="6" style="font-size: 14px" /></label>
      <label class="check" style="border: 0; padding: 0">
        <input v-model="form.active" type="checkbox" />
        <span>Actif (peut être choisi pour un garage ou un comparatif)</span>
      </label>
      <button type="submit" class="btn btn-primary btn-lg" :disabled="saving"><span v-if="saving" class="spinner" aria-hidden="true" />Enregistrer</button>
    </form>
  </div>
</template>

<style scoped>
.split {
  display: flex;
  flex-wrap: wrap;
  gap: 20px;
  align-items: flex-start;
}
</style>
