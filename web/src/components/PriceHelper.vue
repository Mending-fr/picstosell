<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { euros, num } from '../labels'
import AppModal from './AppModal.vue'
import { carKey, priceJobs, startPriceJob } from '../priceJobs'

const props = defineProps<{ car: Record<string, unknown> }>()
const emit = defineEmits<{ use: [price: number] }>()

const localError = ref('')
const details = ref(false)
const key = computed(() => carKey(props.car))
const job = computed(() => priceJobs.get(key.value) ?? null)
const loading = computed(() => job.value?.status === 'running')
const advice = computed(() => (job.value?.status === 'done' ? job.value.advice : null))
const error = computed(() => localError.value || (job.value?.status === 'error' ? job.value.error : ''))

// Cet écran « regarde » la recherche : tant qu'il est affiché, pas de message en fin de recherche.
let watched: string | null = null
function watchKey(k: string | null) {
  if (watched) {
    const j = priceJobs.get(watched)
    if (j) j.watchers = Math.max(0, j.watchers - 1)
  }
  watched = k
  if (k) {
    const j = priceJobs.get(k)
    if (j) j.watchers++
  }
}
watch([key, () => priceJobs.has(key.value)], () => watchKey(priceJobs.has(key.value) ? key.value : null), { immediate: true })
onBeforeUnmount(() => watchKey(null))

const CONFIDENCE: Record<string, string> = { low: "peu d'annonces trouvées", medium: 'quelques annonces proches', high: "beaucoup d'annonces proches" }

function run(force = false) {
  localError.value = ''
  if (!props.car.make || !props.car.model) {
    localError.value = 'Indiquez au moins la marque et le modèle.'
    return
  }
  if (force) priceJobs.delete(key.value)
  startPriceJob(props.car)
}

/** Résumé des sources sur une ligne : « Web : 13 200 à 14 300 € · Auto-ways : 13 400 € ». */
const summary = computed<string[]>(() => {
  const a = advice.value
  if (!a) return []
  return a.sources.map((s) => (s.provider === 'web' ? `Web : ${euros(s.low)} à ${euros(s.high)}` : `Auto-ways : ${euros(s.price)}`))
})

const web = () => advice.value?.sources.find((s) => s.provider === 'web')
const aw = () => advice.value?.sources.find((s) => s.provider === 'autoways')
</script>

<template>
  <div class="pricehelp">
    <button v-if="!advice && !loading" type="button" class="btn ph-btn" @click="run()">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7" /><path d="M20 20l-4-4" /></svg>
      M'aider à fixer le prix
    </button>
    <div v-if="loading" class="ph-card" role="status">
      <span class="ph-spin" aria-hidden="true" />
      <span>Recherche d'annonces comparables, environ une minute. Vous pouvez continuer, le résultat vous attendra.</span>
    </div>
    <p v-if="error" class="err">{{ error }}</p>

    <div v-if="advice && !loading" class="ph-card col">
      <template v-if="advice.available">
        <div class="col" style="gap: 2px">
          <span class="ph-k">Fourchette estimée</span>
          <span class="ph-range">{{ euros(advice.low) }} à {{ euros(advice.high) }}</span>
          <span v-if="summary.length" class="ph-sum"><span v-for="(x, i) in summary" :key="x" class="nw">{{ i ? ' · ' : '' }}{{ x }}</span></span>
        </div>
        <div class="ph-acts">
          <button type="button" class="btn btn-primary btn-sm" @click="emit('use', advice.suggested!)">Utiliser {{ euros(advice.suggested) }}</button>
          <button type="button" class="btn-link" @click="details = true">Voir le détail</button>
        </div>
      </template>
      <template v-else>
        <p class="muted" style="margin: 0">Pas assez d'informations pour proposer une fourchette.</p>
        <div class="ph-acts">
          <button type="button" class="btn-link" @click="run(true)">Relancer</button>
          <button v-if="advice.notes.length" type="button" class="btn-link" @click="details = true">Voir le détail</button>
        </div>
      </template>
    </div>

    <AppModal v-if="details && advice" title="D'où vient cette fourchette" wide @close="details = false">
      <div class="col" style="gap: 18px">
        <div v-if="advice.available" class="d-top">
          <div class="col" style="gap: 2px">
            <span class="ph-k">Fourchette estimée</span>
            <span class="ph-range big">{{ euros(advice.low) }} à {{ euros(advice.high) }}</span>
          </div>
          <span class="muted" style="font-size: 13px">Prix conseillé : <strong style="color: var(--ink)">{{ euros(advice.suggested) }}</strong></span>
        </div>

        <ul class="d-src">
          <li v-if="web()">
            <span class="dot web" aria-hidden="true" />
            <span class="col" style="gap: 2px">
              <span class="d-src-t">Annonces sur le web</span>
              <span class="muted">{{ euros(web()!.low) }} à {{ euros(web()!.high) }}<template v-if="web()!.confidence">, {{ CONFIDENCE[web()!.confidence!] }}</template></span>
            </span>
          </li>
          <li v-if="aw()">
            <span class="dot aw" aria-hidden="true" />
            <span class="col" style="gap: 2px">
              <span class="d-src-t">{{ aw()!.label }}</span>
              <span class="muted">{{ euros(aw()!.price) }}</span>
            </span>
          </li>
        </ul>

        <p v-if="web()?.comment" class="d-comment">{{ web()!.comment }}</p>

        <div v-if="web()?.comparables?.length" class="col" style="gap: 8px">
          <span class="ph-k">Annonces comparables ({{ web()!.comparables!.length }})</span>
          <table class="t d-table">
            <thead>
              <tr><th>Annonce</th><th>Site</th><th>Année</th><th class="r">Km</th><th class="r">Prix</th></tr>
            </thead>
            <tbody>
              <tr v-for="c in web()!.comparables" :key="c.url">
                <td class="d-title"><a :href="c.url" target="_blank" rel="noopener noreferrer">{{ c.title }}</a></td>
                <td class="muted">{{ c.site }}</td>
                <td>{{ c.year ?? '–' }}</td>
                <td class="r">{{ c.mileage != null ? num(c.mileage) : '–' }}</td>
                <td class="r" style="font-weight: 500">{{ euros(c.price) }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <p v-for="n in advice.notes" :key="n" class="muted ph-note">{{ n }}</p>
        <span class="muted" style="font-size: 12px">Estimation indicative, à confronter à votre connaissance du marché.</span>
      </div>
      <template #footer>
        <button type="button" class="btn" @click="(details = false), run(true)">Relancer la recherche</button>
        <button v-if="advice.available" type="button" class="btn btn-primary" @click="(details = false), emit('use', advice.suggested!)">Utiliser {{ euros(advice.suggested) }}</button>
      </template>
    </AppModal>
  </div>
</template>

<style scoped>
.pricehelp {
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
  border: 1px solid var(--accent-line, #D4D4D4);
  background: var(--accent-tint, #E0F0FF);
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
.ph-k {
  font-size: 12px;
  color: var(--muted, #7A8090);
}
.ph-range {
  font-size: 20px;
  font-weight: 500;
  color: var(--ink, #1C2230);
}
.ph-range.big {
  font-size: 26px;
}
.ph-sum {
  font-size: 13px;
  color: var(--muted);
}
.nw {
  white-space: nowrap;
}
.ph-acts {
  display: flex;
  align-items: center;
  gap: 14px;
  flex-wrap: wrap;
}
.ph-acts .btn-link {
  font-size: 14px;
  min-height: 0;
}
.d-top {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  gap: 12px;
  flex-wrap: wrap;
}
.d-src {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 10px;
}
.d-src li {
  display: flex;
  gap: 10px;
  align-items: flex-start;
  background: var(--ground);
  border-radius: 12px;
  padding: 12px 14px;
  font-size: 14px;
}
.d-src-t {
  font-weight: 500;
}
.dot {
  width: 8px;
  height: 8px;
  border-radius: 4px;
  flex-shrink: 0;
  margin-top: 6px;
}
.dot.web {
  background: var(--accent);
}
.dot.aw {
  background: #8C76E6;
}
.d-comment {
  margin: 0;
  font-size: 14px;
  line-height: 1.55;
  color: var(--ink-2);
}
.d-table {
  border: 1px solid var(--line);
  border-radius: 12px;
  border-collapse: separate;
  border-spacing: 0;
  overflow: hidden;
}
.d-table th,
.d-table td {
  padding: 9px 12px;
}
.d-title {
  max-width: 280px;
  overflow: hidden;
  text-overflow: ellipsis;
}
.r {
  text-align: right;
}
.ph-note {
  margin: 0;
  font-size: 13px;
}
.ph-spin {
  width: 16px;
  height: 16px;
  border-radius: 8px;
  border: 2px solid var(--accent-line, #D4D4D4);
  border-top-color: var(--accent, #005BD3);
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
