<script setup>
import { reactiveOmit } from "@vueuse/core";
import { HoverCardContent, HoverCardPortal, useForwardProps } from "reka-ui";
import { cn } from "@/lib/utils";

defineOptions({
  inheritAttrs: false,
});

const props = defineProps({
  forceMount: { type: Boolean, required: false },
  side: { type: null, required: false },
  sideOffset: { type: Number, required: false, default: 8 },
  align: { type: null, required: false, default: "center" },
  alignOffset: { type: Number, required: false },
  avoidCollisions: { type: Boolean, required: false },
  collisionBoundary: { type: null, required: false },
  collisionPadding: { type: [Number, Object], required: false },
  sticky: { type: String, required: false },
  hideWhenDetached: { type: Boolean, required: false },
  asChild: { type: Boolean, required: false },
  as: { type: null, required: false },
  class: {
    type: [Boolean, null, String, Object, Array],
    required: false,
    skipCheck: true,
  },
});

const delegatedProps = reactiveOmit(props, "class");
const forwarded = useForwardProps(delegatedProps);
</script>

<template>
  <HoverCardPortal>
    <HoverCardContent
      data-slot="hover-card-content"
      v-bind="{ ...$attrs, ...forwarded }"
      :class="
        cn(
          'bg-popover text-popover-foreground data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 data-[side=bottom]:slide-in-from-top-2 data-[side=left]:slide-in-from-right-2 data-[side=right]:slide-in-from-left-2 data-[side=top]:slide-in-from-bottom-2 z-50 w-64 origin-(--reka-hover-card-content-transform-origin) rounded-xl border p-4 shadow-md outline-none',
          props.class,
        )
      "
    >
      <slot />
    </HoverCardContent>
  </HoverCardPortal>
</template>
