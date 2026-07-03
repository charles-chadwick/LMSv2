<script setup>
import { computed } from 'vue';

const props = defineProps({
    level: {
        type: String,
        required: true,
    },
});

// Full static class strings per level so Tailwind v4 keeps them.
const STYLES = {
    Beginner: 'bg-emerald-500/12 text-emerald-700 ring-emerald-500/25',
    Intermediate: 'bg-sky-500/12 text-sky-700 ring-sky-500/25',
    Advanced: 'bg-violet-500/12 text-violet-700 ring-violet-500/25',
};

const fallback = 'bg-zinc-500/12 text-zinc-600 ring-zinc-500/20';

const classes = computed(() => STYLES[props.level] ?? fallback);
</script>

<template>
    <span
        :class="classes"
        class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset"
    >
        <span class="size-1.5 rounded-full bg-current opacity-70" />
        {{ level }}
    </span>
</template>
