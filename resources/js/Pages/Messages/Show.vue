<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    conversation: { type: Object, required: true },
});
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Conversation" />
        <h1>Conversation {{ conversation.id }}</h1>
    </AuthenticatedLayout>
</template>
