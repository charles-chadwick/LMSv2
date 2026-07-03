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
        <Head title="Browse courses" />

        <h1 class="mb-6 text-2xl font-semibold">Browse courses</h1>

        <div v-if="courses.length === 0" class="rounded border border-dashed p-8 text-center text-gray-500">
            No published courses yet.
        </div>

        <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <Link
                v-for="course in courses"
                :key="course.id"
                :href="route('catalog.show', course.slug)"
                class="block rounded-lg border p-5 hover:shadow"
            >
                <div class="mb-2 flex items-center justify-between">
                    <span class="rounded-full bg-gray-100 px-2 py-1 text-xs">{{ course.level }}</span>
                    <span v-if="course.is_enrolled" class="text-xs font-medium text-green-600">Enrolled</span>
                </div>
                <h2 class="font-semibold">{{ course.title }}</h2>
                <p class="mt-1 text-sm text-gray-600">{{ course.summary }}</p>
                <p class="mt-3 text-xs text-gray-500">{{ course.instructor }}</p>
            </Link>
        </div>
    </AuthenticatedLayout>
</template>
