<script setup lang="ts">
import type { ComboboxItemProps } from 'reka-ui'
import { ComboboxItem } from 'reka-ui'

const props = defineProps<ComboboxItemProps>()
</script>

<template><ComboboxItem v-bind="props" class="relative flex cursor-default select-none items-center rounded-sm px-2 py-1.5 text-sm outline-none data-[highlighted]:bg-accent data-[highlighted]:text-accent-foreground"><slot /></ComboboxItem></template>