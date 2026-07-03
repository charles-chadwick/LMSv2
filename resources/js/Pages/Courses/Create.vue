<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    levels: {
        type: Array,
        required: true,
    },
});
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Create Course" />

        <h1 class="text-2xl font-semibold">Create Course</h1>
    </AuthenticatedLayout>
</template>
