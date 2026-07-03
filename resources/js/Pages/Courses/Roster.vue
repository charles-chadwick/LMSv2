<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    course: { type: Object, required: true },
    students: { type: Array, required: true },
});
</script>

<template>
    <AuthenticatedLayout>
        <Head :title="`Roster — ${course.title}`" />
        <h1 class="text-2xl font-semibold">Roster</h1>
    </AuthenticatedLayout>
</template>
