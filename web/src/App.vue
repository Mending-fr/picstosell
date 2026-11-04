<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import AppSidebar from './components/AppSidebar.vue'
import { session } from './session'

const route = useRoute()
const layout = computed(() => route.meta.layout ?? 'bare')
const showSidebar = computed(() => (layout.value === 'garage' || layout.value === 'mending') && !!session.user)
</script>

<template>
  <a href="#contenu" class="skip">Aller au contenu</a>
  <div v-if="showSidebar" class="shell">
    <AppSidebar :variant="layout === 'mending' ? 'mending' : 'garage'" />
    <main id="contenu" class="main" :class="{ narrow: route.path === '/vehicles/new' || route.path === '/settings' }">
      <RouterView :key="`${session.viewKey}-${route.path}`" />
    </main>
  </div>
  <main v-else id="contenu">
    <RouterView :key="`${session.viewKey}-${route.path}`" />
  </main>
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
