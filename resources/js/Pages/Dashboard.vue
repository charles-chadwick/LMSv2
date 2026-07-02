<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const user = computed(() => usePage().props.auth.user);
const roles = computed(() => user.value.roles ?? []);
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Dashboard" />

        <h1 class="text-2xl font-semibold">Welcome back, {{ user.name }}</h1>
        <p class="mt-2 text-gray-600">
            You are signed in as
            <span class="font-medium capitalize">{{ roles.join(', ') || 'no role' }}</span>.
        </p>
    </AuthenticatedLayout>
</template>
