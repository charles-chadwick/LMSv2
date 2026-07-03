<script setup>
import { useForwardPropsEmits } from "reka-ui";
import { HoverCardRoot } from "reka-ui";

const props = defineProps({
  defaultOpen: { type: Boolean, required: false },
  open: { type: Boolean, required: false },
  openDelay: { type: Number, required: false, default: 200 },
  closeDelay: { type: Number, required: false, default: 150 },
});
const emits = defineEmits(["update:open"]);

const forwarded = useForwardPropsEmits(props, emits);
</script>

<template>
  <HoverCardRoot data-slot="hover-card" v-bind="forwarded">
    <slot />
  </HoverCardRoot>
</template>
