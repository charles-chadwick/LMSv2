<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    discussion: { type: Object, required: true },
});
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Discussion" />
        <h1>{{ discussion.title }}</h1>
    </AuthenticatedLayout>
</template>
