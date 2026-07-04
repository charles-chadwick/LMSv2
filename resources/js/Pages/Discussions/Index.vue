<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    course: { type: Object, required: true },
    discussions: { type: Object, required: true },
});
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Discussions" />
        <h1>Discussions</h1>
    </AuthenticatedLayout>
</template>
