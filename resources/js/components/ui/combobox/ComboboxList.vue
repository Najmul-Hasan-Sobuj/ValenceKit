<script setup lang="ts">
import type { ComboboxContentProps } from 'reka-ui'
import { ComboboxContent, ComboboxViewport, ComboboxPortal } from 'reka-ui'

const props = defineProps<ComboboxContentProps>()
</script>

<template>
  <ComboboxPortal>
    <ComboboxContent v-bind="{ ...props, ...$attrs }" :position="'popper'" class="bg-popover text-popover-foreground z-[100] mt-1 max-h-60 w-[var(--reka-combobox-trigger-width)] overflow-hidden rounded-md border shadow-md">
      <ComboboxViewport class="p-1"><slot /></ComboboxViewport>
    </ComboboxContent>
  </ComboboxPortal>
</template>