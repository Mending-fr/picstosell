<script setup lang="ts">
import { computed } from 'vue'
import { STATUS } from '../labels'
import type { VehicleStatus } from '../types'

const props = defineProps<{ status: VehicleStatus }>()
const s = computed(() => STATUS[props.status] ?? STATUS.archived)
</script>

<template>
  <span class="pill" :style="{ color: s.fg, background: s.bg }">{{ s.label }}</span>
</template>
