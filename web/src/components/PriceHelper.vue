<script setup lang="ts">
import { ref } from 'vue'
import { post, errorMessage } from '../api'
import { euros, num } from '../labels'

interface Comparable { title: string; url: string; price: number; year: number | null; mileage: number | null; site: string }
interface Source {
  provider: 'web' | 'autoways'
  label: string
  low?: number
  high?: number
  suggested?: number
  price?: number
  confidence?: 'low' | 'medium' | 'high'
  comment?: string
  comparables?: Comparable[]
}
interface Advice { available: boolean; low: number | null; high: number | null; suggested: number | null; sources: Source[]; notes: string[] }

const props = defineProps<{ car: Record<string, unknown> }>()
const emit = defineEmits<{ use: [price: number] }>()

const loading = ref(false)
const error = ref('')
const advice = ref<Advice | null>(null)
const showComps = ref(false)

const CONFIDENCE: Record<string, string> = { low: 'peu d\'annonces trouvées', medium: 'quelques annonces proches', high: 'beaucoup d\'annonces proches' }

async function run() {
  error.value = ''
  if (!props.car.make || !props.car.model) {
    error.value = 'Indiquez au moins la marque et le modèle.'
    return
  }
  loading.value = true
  try {
    advice.value = await post<Advice>('/pricing/advice', props.car)
  } catch (e) {
    error.value = errorMessage(e)
  } finally {
    loading.value = false
  }
}

const web = () => advice.value?.sources.find((s) => s.provider === 'web')
const aw = () => advice.value?.sources.find((s) => s.provider === 'autoways')
</script>

<template>
  <div class="ph">
    <button v-if="!advice && !loading" type="button" class="btn ph-btn" @click="run">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7" /><path d="M20 20l-4-4" /></svg>
      M'aider à fixer le prix
    </button>
    <div v-if="loading" class="ph-card" role="status">
      <span class="ph-spin" aria-hidden="true" />
      <span>Recherche d'annonces comparables… cela peut prendre une minute.</span>
    </div>
    <p v-if="error" class="err">{{ error }}</p>

    <div v-if="advice && !loading" class="ph-card col">
      <template v-if="advice.available">
        <div class="ph-top">
          <div class="col" style="gap: 2px">
            <span class="ph-k">Fourchette estimée</span>
            <span class="ph-range">{{ euros(advice.low) }} à {{ euros(advice.high) }}</span>
          </div>
          <button type="button" class="btn btn-primary" @click="emit('use', advice.suggested!)">Utiliser {{ euros(advice.suggested) }}</button>
        </div>
        <ul class="ph-src">
          <li v-if="web()">
            <span class="dot web" aria-hidden="true" />
            Annonces sur le web : {{ euros(web()!.low) }} à {{ euros(web()!.high) }}<template v-if="web()!.confidence">, {{ CONFIDENCE[web()!.confidence!] }}</template>.
            <button v-if="web()!.comparables?.length" type="button" class="btn-link" @click="showComps = !showComps">
              {{ showComps ? 'Masquer' : `Voir les ${web()!.comparables!.length} annonces` }}
            </button>
          </li>
          <li v-if="aw()"><span class="dot aw" aria-hidden="true" />{{ aw()!.label }} : {{ euros(aw()!.price) }}</li>
        </ul>
        <p v-if="web()?.comment" class="ph-comment">{{ web()!.comment }}</p>
        <ul v-if="showComps" class="ph-comps">
          <li v-for="c in web()!.comparables" :key="c.url">
            <a :href="c.url" target="_blank" rel="noopener noreferrer">{{ c.title }}</a>
            <span class="muted">{{ [c.site, c.year, c.mileage != null ? `${num(c.mileage)} km` : null].filter(Boolean).join(' · ') }}</span>
            <strong>{{ euros(c.price) }}</strong>
          </li>
        </ul>
      </template>
      <p v-else class="muted" style="margin: 0">Pas assez d'informations pour proposer une fourchette.</p>
      <p v-for="n in advice.notes" :key="n" class="muted ph-note">{{ n }}</p>
      <div class="ph-foot">
        <span class="muted">Estimation indicative, à confronter à votre connaissance du marché.</span>
        <button type="button" class="btn-link" @click="run">Relancer</button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.ph {
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.ph-btn {
  align-self: flex-start;
  display: inline-flex;
  align-items: center;
  gap: 8px;
}
.ph-card {
  border: 1px solid var(--accent-line, #B9E0E3);
  background: var(--accent-tint, #EAF6F7);
  border-radius: 16px;
  padding: 16px 18px;
  display: flex;
  gap: 12px;
  align-items: center;
  font-size: 14px;
  color: var(--ink-2, #3D414A);
}
.ph-card.col {
  flex-direction: column;
  align-items: stretch;
}
.ph-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}
.ph-k {
  font-size: 12px;
  color: var(--muted, #7A8090);
}
.ph-range {
  font-size: 20px;
  font-weight: 500;
  color: var(--ink, #1C2230);
}
.ph-src {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.ph-src li {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}
.dot {
  width: 8px;
  height: 8px;
  border-radius: 4px;
  flex-shrink: 0;
}
.dot.web {
  background: var(--accent, #0E7C86);
}
.dot.aw {
  background: #8C76E6;
}
.ph-comment {
  margin: 0;
  color: var(--ink-2, #3D414A);
}
.ph-comps {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 8px;
  background: #fff;
  border-radius: 12px;
  padding: 10px 12px;
}
.ph-comps li {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  gap: 2px 12px;
}
.ph-comps li a {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.ph-comps li .muted {
  grid-column: 1;
  font-size: 12px;
}
.ph-comps li strong {
  grid-column: 2;
  grid-row: 1 / span 2;
  align-self: center;
  font-weight: 500;
}
.ph-note {
  margin: 0;
  font-size: 13px;
}
.ph-foot {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
  font-size: 12px;
}
.ph-spin {
  width: 16px;
  height: 16px;
  border-radius: 8px;
  border: 2px solid var(--accent-line, #B9E0E3);
  border-top-color: var(--accent, #0E7C86);
  animation: ph-rot 0.9s linear infinite;
  flex-shrink: 0;
}
@keyframes ph-rot {
  to {
    transform: rotate(360deg);
  }
}
.muted {
  color: var(--muted, #7A8090);
}
</style>
