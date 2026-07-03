<script setup>
import { computed } from 'vue';
import { useSectionTheme } from '@/composables/useSectionTheme';

const props = defineProps({
    title: {
        type: String,
        required: true,
    },
    subtitle: {
        type: String,
        default: null,
    },
    eyebrow: {
        type: String,
        default: null,
    },
});

const { theme } = useSectionTheme();

const eyebrowText = computed(() => props.eyebrow ?? theme.value.label);
</script>

<template>
    <header class="relative mb-8 overflow-hidden rounded-2xl border bg-card px-6 py-6 shadow-sm sm:px-8 sm:py-7">
        <!-- Section-tinted gradient wash -->
        <div
            class="pointer-events-none absolute inset-0 -z-0 bg-gradient-to-br opacity-[0.08]"
            :class="theme.gradient"
        />
        <div class="pointer-events-none absolute inset-y-0 left-0 w-1.5" :class="theme.accent" />

        <div class="relative flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div class="min-w-0">
                <p class="text-xs font-semibold uppercase tracking-[0.14em]" :class="theme.text">
                    {{ eyebrowText }}
                </p>
                <h1 class="mt-1.5 font-display text-3xl font-bold leading-tight tracking-tight text-foreground sm:text-4xl">
                    {{ title }}
                </h1>
                <p v-if="subtitle" class="mt-2 max-w-2xl text-sm text-muted-foreground">
                    {{ subtitle }}
                </p>
            </div>

            <div v-if="$slots.actions" class="flex shrink-0 flex-wrap items-center gap-2.5">
                <slot name="actions" />
            </div>
        </div>
    </header>
</template>
