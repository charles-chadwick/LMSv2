<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    courses: {
        type: Array,
        required: true,
    },
});
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Courses" />

        <h1 class="text-2xl font-semibold">Courses</h1>

        <ul class="mt-4 space-y-2">
            <li v-for="course in courses" :key="course.id">
                <Link :href="route('courses.edit', course.slug)">{{ course.title }}</Link>
            </li>
        </ul>
    </AuthenticatedLayout>
</template>
