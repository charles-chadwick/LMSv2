<script setup>
import { computed } from 'vue';

const props = defineProps({
    status: {
        type: String,
        required: true,
    },
});

// Full static class strings per status so Tailwind v4 keeps them.
const STYLES = {
    Active: 'bg-emerald-500/12 text-emerald-700 ring-emerald-500/25',
    Completed: 'bg-violet-500/12 text-violet-700 ring-violet-500/25',
    Dropped: 'bg-zinc-500/12 text-zinc-600 ring-zinc-500/20',
    Suspended: 'bg-rose-500/12 text-rose-700 ring-rose-500/25',
    Draft: 'bg-amber-500/15 text-amber-700 ring-amber-500/30',
    Published: 'bg-emerald-500/12 text-emerald-700 ring-emerald-500/25',
    Archived: 'bg-zinc-500/12 text-zinc-600 ring-zinc-500/20',
};

const fallback = 'bg-zinc-500/12 text-zinc-600 ring-zinc-500/20';

const classes = computed(() => STYLES[props.status] ?? fallback);
</script>

<template>
    <span
        :class="classes"
        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset"
    >
        {{ status }}
    </span>
</template>
