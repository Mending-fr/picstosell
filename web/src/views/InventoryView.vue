<script setup lang="ts">
// Résultat d'un inventaire : voitures pas vues, plaques hors parc, voitures retrouvées.
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { get, patch, post, errorMessage } from '../api'
import { isManager, toast, refreshSidebar } from '../session'
import { openAdd } from '../modals'
import ImageViewer from '../components/ImageViewer.vue'
import type { Inventory, InventoryCar, InventoryPlate } from '../types'

const route = useRoute()
const id = Number(route.params.id)
const inv = ref<Inventory | null>(null)
const error = ref('')
const busy = ref<string | null>(null)
const showFound = ref(false)
const viewer = ref<{ url: string; label: string } | null>(null)
let timer: ReturnType<typeof setTimeout> | undefined

async function load() {
  try {
    inv.value = await get<Inventory>(`/inventories/${id}`)
    error.value = ''
  } catch (e) {
    error.value = errorMessage(e)
  }
}
load()
// Tant que des images restent à lire, on rafraîchit.
watch(inv, (v) => {
  clearTimeout(timer)
  if (v?.pending_frames) timer = setTimeout(load, 3000)
})
onBeforeUnmount(() => clearTimeout(timer))

const when = computed(() => {
  if (!inv.value?.created_at) return ''
  const s = new Date(inv.value.created_at).toLocaleString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', hour: '2-digit', minute: '2-digit' })
  return s.charAt(0).toUpperCase() + s.slice(1)
})

async function setPlate(p: InventoryPlate, data: { dismissed?: boolean; vehicle_id?: number | null }) {
  busy.value = `p${p.id}`
  try {
    inv.value = await patch<Inventory>(`/inventories/${id}/plates/${p.id}`, data)
  } catch (e) {
    toast(errorMessage(e), 'error')
  } finally {
    busy.value = null
  }
}

async function sold(c: InventoryCar) {
  if (!window.confirm(`Marquer ${c.label} (${c.plate}) comme vendue ? L'annonce sera retirée de votre site.`)) return
  busy.value = `v${c.id}`
  try {
    await post(`/vehicles/${c.id}/sold`)
    toast(`${c.label} est marquée vendue.`)
    refreshSidebar()
    await load()
  } catch (e) {
    toast(errorMessage(e), 'error')
  } finally {
    busy.value = null
  }
}

/** Rattacher une plaque lue à une voiture pas vue (lecture trop différente pour être rapprochée seule). */
const linkFor = ref<number | null>(null)
async function link(p: InventoryPlate, vehicleId: string) {
  linkFor.value = null
  if (vehicleId) await setPlate(p, { vehicle_id: Number(vehicleId) })
}
</script>

<template>
  <div class="page">
    <header class="head">
      <RouterLink to="/vehicles/inventaires" class="icon-btn" aria-label="Retour aux inventaires">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M15 6l-6 6 6 6" /></svg>
      </RouterLink>
      <div class="col" style="gap: 2px; min-width: 0">
        <h1>Inventaire du parc</h1>
        <span v-if="inv" class="muted small">{{ when }}{{ inv.user ? ` · ${inv.user}` : '' }} · {{ inv.frames }} image{{ inv.frames > 1 ? 's' : '' }}</span>
      </div>
    </header>

    <p v-if="error" class="alert alert-error" role="alert">{{ error }}</p>
    <div v-if="!inv && !error" class="skel" style="height: 240px; border-radius: 12px" />

    <template v-if="inv">
      <div class="stats">
        <div class="stat"><span class="n ok">{{ inv.found }}</span><span class="muted small">voitures retrouvées</span></div>
        <div class="stat"><span class="n warn">{{ inv.missing }}</span><span class="muted small">pas vues pendant le tour</span></div>
        <div class="stat"><span class="n info">{{ inv.unknown }}</span><span class="muted small">plaques hors parc</span></div>
      </div>
      <p v-if="inv.pending_frames" class="alert alert-info" role="status" style="margin: 0">
        Lecture en cours : encore {{ inv.pending_frames }} image{{ inv.pending_frames > 1 ? 's' : '' }}. La page se met à jour toute seule.
      </p>

      <section class="col" style="gap: 8px" aria-labelledby="mi-t">
        <h2 id="mi-t">Pas vues pendant le tour</h2>
        <span class="muted small">Dans votre parc, mais aucune plaque lue. Vendue sans être marquée, chez le carrossier, ou cachée par une autre voiture ?</span>
        <div class="table-wrap">
          <table class="t">
            <tbody>
              <tr v-for="c in inv.missing_list" :key="c.id">
                <td>
                  <div class="row" style="flex-wrap: nowrap; gap: 10px">
                    <img v-if="c.cover_url" :src="c.cover_url" alt="" class="thumb" />
                    <span v-else class="thumb ph" />
                    <RouterLink :to="`/vehicles/${c.id}`" class="car">{{ c.label }}</RouterLink>
                  </div>
                </td>
                <td><span class="plate">{{ c.plate }}</span></td>
                <td style="text-align: right">
                  <button v-if="isManager" type="button" class="btn btn-sm" :disabled="busy === `v${c.id}`" @click="sold(c)">Marquer vendue</button>
                </td>
              </tr>
            </tbody>
          </table>
          <div v-if="!inv.missing_list.length" class="empty">Toutes les voitures du parc ont été vues.</div>
        </div>
      </section>

      <section class="col" style="gap: 8px" aria-labelledby="un-t">
        <h2 id="un-t">Vues mais pas dans le parc</h2>
        <span class="muted small">Plaques lues qui ne correspondent à aucune voiture enregistrée. Vérifiez la photo : une plaque peut avoir été mal lue.</span>
        <div class="table-wrap">
          <table class="t">
            <tbody>
              <tr v-for="p in inv.unknown_list" :key="p.id">
                <td style="width: 1%">
                  <button v-if="p.image_url" type="button" class="shot" aria-label="Voir la photo en grand" @click="viewer = { url: p.image_url, label: p.plate }"><img :src="p.image_url" alt="" /></button>
                </td>
                <td>
                  <span class="plate">{{ p.plate }}</span>
                  <span class="muted small" style="margin-left: 8px">{{ p.sure ? 'Lecture nette' : 'Lecture à vérifier' }} · vue {{ p.seen }} fois</span>
                </td>
                <td class="acts">
                  <template v-if="linkFor === p.id">
                    <select class="f f-sm" style="width: auto" @change="link(p, ($event.target as HTMLSelectElement).value)">
                      <option value="">Quelle voiture ?</option>
                      <option v-for="c in inv.missing_list" :key="c.id" :value="c.id">{{ c.plate }} · {{ c.label }}</option>
                    </select>
                  </template>
                  <template v-else>
                    <button type="button" class="btn btn-sm btn-primary" @click="openAdd(p.plate)">Ajouter au parc</button>
                    <button v-if="inv.missing_list.length" type="button" class="btn-link" @click="linkFor = p.id">C'est une voiture du parc</button>
                    <button type="button" class="btn-link danger" :disabled="busy === `p${p.id}`" @click="setPlate(p, { dismissed: true })">Écarter</button>
                  </template>
                </td>
              </tr>
            </tbody>
          </table>
          <div v-if="!inv.unknown_list.length" class="empty">Aucune voiture inconnue sur le parking.</div>
        </div>
      </section>

      <button type="button" class="btn-link" style="align-self: flex-start" @click="showFound = !showFound">
        {{ showFound ? 'Masquer' : 'Voir' }} les voitures retrouvées ({{ inv.found_list.length }})
      </button>
      <div v-if="showFound" class="table-wrap">
        <table class="t">
          <tbody>
            <tr v-for="p in inv.found_list" :key="p.id">
              <td><RouterLink v-if="p.vehicle" :to="`/vehicles/${p.vehicle.id}`" class="car">{{ p.vehicle.label }}</RouterLink></td>
              <td><span class="plate">{{ p.vehicle?.plate ?? p.plate }}</span></td>
              <td class="soft">{{ p.corrected ? `Lue « ${p.plate} », rapprochée de la plaque enregistrée` : `Vue ${p.seen} fois` }}</td>
              <td style="text-align: right"><button type="button" class="btn-link" :disabled="busy === `p${p.id}`" @click="setPlate(p, { vehicle_id: null })">Ce n'est pas elle</button></td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>
  </div>
  <ImageViewer v-if="viewer" :items="[viewer]" :start="0" @close="viewer = null" />
</template>

<style scoped>
.page {
  max-width: 1000px;
  margin: 0 auto;
  width: 100%;
  display: flex;
  flex-direction: column;
  gap: 18px;
}
.head {
  display: flex;
  align-items: center;
  gap: 12px;
}
.small {
  font-size: 12.5px;
}
.stats {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 12px;
}
.stat {
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 1px 0 rgba(0, 0, 0, 0.07), 0 0 0 1px rgba(0, 0, 0, 0.06);
  padding: 14px 16px;
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.n {
  font-size: 24px;
  font-weight: 700;
}
.n.ok {
  color: var(--green-ink);
}
.n.warn {
  color: var(--amber-ink);
}
.n.info {
  color: #00428a;
}
.car {
  color: var(--ink);
  font-weight: 550;
  text-decoration: none;
}
.car:hover {
  text-decoration: underline;
  color: var(--ink);
}
.shot {
  border: 0;
  padding: 0;
  background: none;
  cursor: zoom-in;
}
.shot img {
  width: 88px;
  height: 58px;
  object-fit: cover;
  border-radius: 6px;
  display: block;
}
.acts {
  text-align: right;
}
.acts .btn-link {
  margin-left: 10px;
}
.danger {
  color: var(--red-ink);
}
@media (max-width: 640px) {
  .stats {
    grid-template-columns: minmax(0, 1fr);
  }
  .acts {
    white-space: normal;
  }
}
</style>
