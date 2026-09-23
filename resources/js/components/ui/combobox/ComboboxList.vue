<script setup lang="ts">
import type { ComboboxContentProps } from 'reka-ui'
import { ComboboxContent, ComboboxViewport } from 'reka-ui'

const props = defineProps<ComboboxContentProps>()
</script>

<template>
  <ComboboxContent v-bind="props" class="bg-popover text-popover-foreground absolute inset-x-0 top-full z-50 mt-1 max-h-60 min-w-[8rem] overflow-hidden rounded-md border shadow-md">
    <ComboboxViewport class="p-1"><slot /></ComboboxViewport>
  </ComboboxContent>
</template>