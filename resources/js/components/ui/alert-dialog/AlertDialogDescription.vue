<script setup lang="ts">
import type { AlertDialogDescriptionProps } from 'reka-ui'
import { AlertDialogDescription } from 'reka-ui'

const props = defineProps<AlertDialogDescriptionProps>()
</script>

<template><AlertDialogDescription v-bind="props" class="text-muted-foreground text-sm"><slot /></AlertDialogDescription></template>