<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    conversations: { type: Array, required: true },
});
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Messages" />
        <h1>Messages</h1>
    </AuthenticatedLayout>
</template>
