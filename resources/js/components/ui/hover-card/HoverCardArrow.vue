<script setup lang="ts">
import type { HoverCardArrowProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import { reactiveOmit } from "@vueuse/core"
import { HoverCardArrow, useForwardProps } from "reka-ui"
import { cn } from "@/lib/utils"

const props = defineProps<HoverCardArrowProps & { class?: HTMLAttributes["class"] }>()
const delegatedProps = reactiveOmit(props, "class")
const forwarded = useForwardProps(delegatedProps)
</script>

<template>
  <HoverCardArrow
    data-slot="hover-card-arrow"
    v-bind="forwarded"
    :class="cn('fill-popover', props.class)"
  />
</template>
