<script setup>
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    name: {
        type: String,
        default: 'World',
    },
});
</script>

<template>
    <Head title="Welcome" />

    <main class="min-h-screen flex flex-col items-center justify-center gap-4 bg-gray-50 text-gray-900">
        <h1 class="text-3xl font-semibold">Hello, {{ name }} 👋</h1>
        <p class="text-gray-600">Inertia + Vue + Ziggy are wired up.</p>
        <Link :href="route('home')" class="text-blue-600 underline">route('home') via Ziggy</Link>
    </main>
</template>
