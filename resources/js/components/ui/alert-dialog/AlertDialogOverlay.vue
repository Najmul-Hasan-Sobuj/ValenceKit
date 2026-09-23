<script setup lang="ts">
import type { AlertDialogOverlayProps } from 'reka-ui'
import { AlertDialogOverlay } from 'reka-ui'

const props = defineProps<AlertDialogOverlayProps>()
</script>

<template>
  <AlertDialogOverlay v-bind="props" class="fixed inset-0 z-50 bg-black/50 data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0" />
</template>