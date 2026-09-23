<script setup lang="ts">
import type { AlertDialogTitleProps } from 'reka-ui'
import { AlertDialogTitle } from 'reka-ui'

const props = defineProps<AlertDialogTitleProps>()
</script>

<template><AlertDialogTitle v-bind="props" class="text-lg font-semibold"><slot /></AlertDialogTitle></template>