<script setup lang="ts">
// Inventaires du parc faits avec l'appli : la liste, du plus récent au plus ancien.
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { get, errorMessage } from '../api'
import type { InventorySummary } from '../types'

const router = useRouter()
const list = ref<InventorySummary[] | null>(null)
const error = ref('')

async function load() {
  error.value = ''
  try {
    list.value = (await get<{ data: InventorySummary[] }>('/inventories')).data
  } catch (e) {
    error.value = errorMessage(e)
  }
}
onMounted(load)

const day = (iso: string | null) => {
  if (!iso) return '–'
  const s = new Date(iso).toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' })
  return s.charAt(0).toUpperCase() + s.slice(1)
}
const time = (iso: string | null) => (iso ? new Date(iso).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' }) : '')
</script>

<template>
  <div class="page">
    <div class="page-head">
      <div class="col" style="gap: 2px">
        <h1>Inventaires du parc</h1>
        <span class="muted" style="font-size: 13px">Faits avec l'appli mobile : on marche dans le parc, l'appli lit les plaques et les compare à vos voitures.</span>
      </div>
    </div>

    <p v-if="error" class="alert alert-error" role="alert">{{ error }} <button type="button" class="btn-link" @click="load">Réessayer</button></p>

    <div class="table-wrap">
      <table class="t">
        <thead>
          <tr><th>Date</th><th>Fait par</th><th class="r">Retrouvées</th><th class="r">Pas vues</th><th class="r">Hors parc</th><th class="r hide-s">Images</th></tr>
        </thead>
        <tbody>
          <template v-if="!list && !error">
            <tr v-for="i in 3" :key="i"><td colspan="6"><span class="skel" style="display: block; width: 260px; height: 14px" /></td></tr>
          </template>
          <tr v-for="inv in list ?? []" :key="inv.id" class="clickable" @click="router.push(`/vehicles/inventaires/${inv.id}`)">
            <td>
              <RouterLink :to="`/vehicles/inventaires/${inv.id}`" class="day" @click.stop>{{ day(inv.created_at) }}</RouterLink>
              <span class="muted" style="font-size: 12px; margin-left: 6px">{{ time(inv.created_at) }}</span>
              <span v-if="inv.status === 'running'" class="pill" style="margin-left: 6px; background: #e0f0ff; color: #00428a">En cours</span>
            </td>
            <td class="soft">{{ inv.user ?? '–' }}</td>
            <td class="r">{{ inv.found }}</td>
            <td class="r" :style="{ color: inv.missing ? 'var(--amber-ink)' : undefined, fontWeight: inv.missing ? 600 : undefined }">{{ inv.missing }}</td>
            <td class="r">{{ inv.unknown }}</td>
            <td class="r soft hide-s">{{ inv.frames }}</td>
          </tr>
        </tbody>
      </table>
      <div v-if="list && !list.length" class="empty">
        <strong>Aucun inventaire pour l'instant</strong>
        <span>Dans l'appli mobile, touchez « Faire l'inventaire du parc » et faites le tour du parking.</span>
      </div>
    </div>
  </div>
</template>

<style scoped>
.page {
  display: flex;
  flex-direction: column;
  gap: 16px;
}
.day {
  color: var(--ink);
  font-weight: 550;
  text-decoration: none;
}
.day:hover {
  text-decoration: underline;
  color: var(--ink);
}
</style>
