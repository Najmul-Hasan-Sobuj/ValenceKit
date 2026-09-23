<script setup lang="ts">
import type { DialogTitleProps } from 'reka-ui'
import { DialogTitle } from 'reka-ui'

const props = defineProps<DialogTitleProps>()
</script>

<template>
  <DialogTitle v-bind="props" class="text-foreground text-lg font-semibold">
    <slot />
  </DialogTitle>
</template>