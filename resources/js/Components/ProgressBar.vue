<script setup>
import { computed } from 'vue';
import { useSectionTheme } from '@/composables/useSectionTheme';

const props = defineProps({
    value: {
        type: Number,
        default: 0,
    },
    showLabel: {
        type: Boolean,
        default: true,
    },
});

const { theme } = useSectionTheme();

const clamped = computed(() => Math.max(0, Math.min(100, Math.round(props.value))));
</script>

<template>
    <div class="flex items-center gap-2.5">
        <div class="h-2 flex-1 overflow-hidden rounded-full bg-muted">
            <div
                class="h-full rounded-full transition-[width] duration-500 ease-out"
                :class="theme.accent"
                :style="{ width: `${clamped}%` }"
            />
        </div>
        <span v-if="showLabel" class="w-9 shrink-0 text-right text-xs font-semibold tabular-nums text-muted-foreground">
            {{ clamped }}%
        </span>
    </div>
</template>
