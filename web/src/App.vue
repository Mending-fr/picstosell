<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import AppSidebar from './components/AppSidebar.vue'
import AddVehicleModal from './components/AddVehicleModal.vue'
import PriceModal from './components/PriceModal.vue'
import { session, isManager } from './session'
import { modals, openAdd, openPrice, typingIn } from './modals'

const route = useRoute()
const layout = computed(() => route.meta.layout ?? 'bare')
const showSidebar = computed(() => (layout.value === 'garage' || layout.value === 'mending') && !!session.user)
const garageSpace = computed(() => layout.value === 'garage' && !!session.user)

// Les modales se ferment quand on quitte l'espace garage (changement de garage, déconnexion…).
watch(garageSpace, (on) => {
  if (!on) {
    modals.add = false
    modals.price = false
  }
})

// Raccourcis de l'espace garage : N ajoute une voiture, S change un prix ou marque vendue.
function onKey(e: KeyboardEvent) {
  if (!garageSpace.value || e.metaKey || e.ctrlKey || e.altKey || typingIn(e)) return
  if (modals.add || modals.price || document.querySelector('[role="dialog"]')) return
  const k = e.key.toLowerCase()
  if (k === 'n') {
    e.preventDefault()
    openAdd()
  } else if (k === 's' && isManager.value) {
    e.preventDefault()
    openPrice()
  }
}
onMounted(() => document.addEventListener('keydown', onKey))
onBeforeUnmount(() => document.removeEventListener('keydown', onKey))
</script>

<template>
  <a href="#contenu" class="skip">Aller au contenu</a>
  <div v-if="showSidebar" class="shell">
    <AppSidebar :variant="layout === 'mending' ? 'mending' : 'garage'" />
    <main id="contenu" class="main" :class="{ narrow: route.path === '/settings' }">
      <RouterView :key="`${session.viewKey}-${route.path}`" />
    </main>
  </div>
  <main v-else id="contenu">
    <RouterView :key="`${session.viewKey}-${route.path}`" />
  </main>
  <AddVehicleModal v-if="garageSpace && modals.add" @close="modals.add = false" />
  <PriceModal v-if="garageSpace && modals.price && isManager" @close="modals.price = false" />
  <div v-if="session.toast" :key="session.toast.id" class="toast" :class="session.toast.kind" role="status" aria-live="polite">
    {{ session.toast.text }}
  </div>
</template>

<style>
.skip {
  position: absolute;
  left: -9999px;
  top: 8px;
  z-index: 100;
  background: #fff;
  padding: 8px 12px;
  border-radius: 8px;
}
.skip:focus {
  left: 8px;
}
</style>
