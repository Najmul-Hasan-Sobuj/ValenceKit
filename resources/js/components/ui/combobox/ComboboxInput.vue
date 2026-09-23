<script setup lang="ts">
import type { ComboboxInputProps } from 'reka-ui'
import { ComboboxCancel, ComboboxInput, injectComboboxRootContext } from 'reka-ui'
import { X } from '@lucide/vue'
import { nextTick } from 'vue'

const props = defineProps<ComboboxInputProps>()
const rootContext = injectComboboxRootContext()

function keepOptionsOpen(): void {
	void nextTick(() => {
		rootContext.open.value = true
	})
}
</script>

<template>
	<div class="relative w-full">
		<ComboboxInput v-bind="props" class="h-9 w-full rounded-md border border-input bg-transparent px-3 pr-9 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring/50" />
		<ComboboxCancel aria-label="Clear selection" title="Clear selection" class="text-muted-foreground hover:text-foreground absolute top-1/2 right-1 flex size-7 -translate-y-1/2 items-center justify-center rounded-sm transition-colors focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:outline-none" @click="keepOptionsOpen">
			<X class="size-4" />
		</ComboboxCancel>
	</div>
</template>