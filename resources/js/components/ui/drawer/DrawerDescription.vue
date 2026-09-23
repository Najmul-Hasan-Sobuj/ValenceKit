<script setup lang="ts">
import type { DialogDescriptionProps } from 'reka-ui'
import { DialogDescription } from 'reka-ui'

const props = defineProps<DialogDescriptionProps>()
</script>

<template>
  <DialogDescription v-bind="props" class="text-muted-foreground text-sm">
    <slot />
  </DialogDescription>
</template>