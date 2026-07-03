<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    course: {
        type: Object,
        required: true,
    },
    levels: {
        type: Array,
        required: true,
    },
});
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Edit Course" />

        <h1 class="text-2xl font-semibold">Edit {{ course.title }}</h1>
    </AuthenticatedLayout>
</template>
