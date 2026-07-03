<script setup>
import { HoverCardTrigger } from "reka-ui";

const props = defineProps({
  asChild: { type: Boolean, required: false },
  as: { type: null, required: false, default: "span" },
});
</script>

<template>
  <HoverCardTrigger data-slot="hover-card-trigger" v-bind="props">
    <slot />
  </HoverCardTrigger>
</template>
