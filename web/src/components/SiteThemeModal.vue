<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { ApiError, get, put, errorMessage } from '../api'
import { toast } from '../session'
import AppModal from './AppModal.vue'
import type { SiteTheme, SiteThemes } from '../types'

const props = defineProps<{ garageId: number; garageName: string }>()
const emit = defineEmits<{ close: []; saved: [name: string] }>()

const data = ref<SiteThemes | null>(null)
const error = ref('')
const selected = ref('')
const values = reactive<Record<string, Record<string, string | number | boolean | null>>>({})
const saving = ref(false)
const errors = ref<Record<string, string>>({})

const cur = computed<SiteTheme | null>(() => data.value?.themes.find((t) => t.key === selected.value) ?? null)

function fill(d: SiteThemes) {
  data.value = d
  if (!selected.value) selected.value = d.current
  for (const t of d.themes) {
    const v: Record<string, string | number | boolean | null> = {}
    for (const o of t.options) {
      const saved = (t.values as Record<string, unknown>)[o.key]
      if (o.type === 'toggle') v[o.key] = saved === undefined || saved === null ? o.default !== false : !!saved
      else v[o.key] = saved === undefined || saved === null ? '' : String(saved).replace('.', o.type === 'number' ? ',' : '.')
    }
    values[t.key] = v
  }
}

onMounted(async () => {
  try {
    fill(await get<SiteThemes>(`/admin/garages/${props.garageId}/site-theme`))
  } catch (e) {
    error.value = errorMessage(e)
  }
})

async function save() {
  if (!cur.value) return
  saving.value = true
  errors.value = {}
  try {
    const d = await put<SiteThemes>(`/admin/garages/${props.garageId}/site-theme`, { theme: cur.value.key, options: values[cur.value.key] ?? {} })
    fill(d)
    toast(`Le site de ${props.garageName} utilise maintenant le thème ${cur.value.name}.`)
    emit('saved', cur.value.name)
    emit('close')
  } catch (e) {
    if (e instanceof ApiError && e.status === 422) {
      for (const [k, m] of Object.entries(e.errors)) errors.value[k.replace(/^options\./, '')] = m[0]
      toast('Certaines valeurs sont à corriger.', 'error')
    } else toast(errorMessage(e), 'error')
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <AppModal :title="`Le site de ${garageName}`" wide @close="emit('close')">
    <p v-if="error" class="alert alert-error" role="alert" style="margin: 0">{{ error }}</p>
    <div v-else-if="!data" class="col" style="gap: 10px">
      <span class="skel" style="height: 96px" />
      <span class="skel" style="height: 96px" />
    </div>
    <div v-else class="col" style="gap: 20px">
      <div role="radiogroup" aria-label="Présentation du site" class="themes">
        <label v-for="t in data.themes" :key="t.key" class="th" :class="{ on: selected === t.key }">
          <input v-model="selected" type="radio" name="theme" :value="t.key" class="sr-only" />
          <span class="th-top">
            <span class="th-name">{{ t.name }}</span>
            <span v-if="t.key === data.current" class="pill cur">En ligne</span>
            <span v-if="t.reserved" class="pill res">Sur mesure</span>
          </span>
          <span class="th-desc">{{ t.description }}</span>
          <a :href="t.preview_url" target="_blank" rel="noopener" class="th-prev" @click.stop>Aperçu ›</a>
        </label>
      </div>

      <template v-if="cur && cur.options.length">
        <div class="col" style="gap: 4px">
          <h3 style="font-size: 15px">Réglages du thème {{ cur.name }}</h3>
          <span class="muted" style="font-size: 13px">Un champ vide est masqué sur le site, ou remplacé par le texte par défaut quand il y en a un.</span>
        </div>
        <div class="opts">
          <template v-for="o in cur.options" :key="o.key">
            <label v-if="o.type === 'toggle'" class="tog">
              <input v-model="values[cur.key][o.key]" type="checkbox" />
              <span>{{ o.label }}</span>
            </label>
            <label v-else class="lbl" :class="{ wide: o.type === 'textarea' }">
              {{ o.label }}
              <textarea v-if="o.type === 'textarea'" v-model="values[cur.key][o.key] as string" class="f" rows="2" :placeholder="String(o.default ?? '')" />
              <input v-else v-model="values[cur.key][o.key] as string" class="f" :inputmode="o.type === 'number' ? 'decimal' : undefined" :placeholder="String(o.default ?? '')" :aria-invalid="!!errors[o.key]" />
              <span v-if="errors[o.key]" class="err">{{ errors[o.key] }}</span>
              <span v-else-if="o.help" class="muted hint">{{ o.help }}</span>
            </label>
          </template>
        </div>
      </template>
      <p v-else-if="cur" class="muted" style="margin: 0; font-size: 14px">Ce thème n'a pas de réglage. Il reprend les coordonnées, le logo et la couleur saisis par le garage dans « Mon site ».</p>
    </div>
    <template #footer>
      <span class="muted" style="font-size: 13px; margin-right: auto">Les visiteurs voient le changement tout de suite.</span>
      <button type="button" class="btn" @click="emit('close')">Annuler</button>
      <button type="button" class="btn btn-primary" :disabled="!cur || saving" @click="save">
        <span v-if="saving" class="spinner" aria-hidden="true" />{{ cur && data && cur.key === data.current ? 'Enregistrer les réglages' : 'Appliquer ce thème' }}
      </button>
    </template>
  </AppModal>
</template>

<style scoped>
.themes {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: 10px;
}
.th {
  border: 2px solid var(--line);
  border-radius: 14px;
  padding: 14px 16px;
  display: flex;
  flex-direction: column;
  gap: 6px;
  cursor: pointer;
  background: #fff;
}
.th:hover {
  border-color: var(--accent-line);
}
.th.on {
  border-color: var(--accent);
  background: var(--accent-tint);
}
.th:has(input:focus-visible) {
  outline: 2px solid var(--accent);
  outline-offset: 2px;
}
.th-top {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}
.th-name {
  font-size: 15px;
  font-weight: 500;
  margin-right: auto;
}
.pill.cur {
  color: var(--green-ink);
  background: var(--green-tint);
}
.pill.res {
  color: var(--violet-ink);
  background: var(--violet-tint);
}
.th-desc {
  font-size: 13px;
  color: var(--muted);
  line-height: 1.45;
}
.th-prev {
  font-size: 13px;
  font-weight: 500;
  text-decoration: none;
  margin-top: 2px;
  align-self: flex-start;
}
.opts {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px 14px;
}
.opts .wide {
  grid-column: 1 / -1;
}
.hint {
  font-size: 12px;
  font-weight: 400;
}
.tog {
  grid-column: 1 / -1;
  display: flex;
  gap: 10px;
  align-items: center;
  font-size: 14px;
}
.tog input {
  width: 18px;
  height: 18px;
  margin: 0;
  accent-color: var(--accent);
}
@media (max-width: 640px) {
  .opts {
    grid-template-columns: minmax(0, 1fr);
  }
}
</style>
