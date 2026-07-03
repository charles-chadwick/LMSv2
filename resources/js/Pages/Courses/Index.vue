<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps({
    courses: {
        type: Array,
        required: true,
    },
});

const canCreate = computed(() => usePage().props.auth.user.can?.create_courses ?? false);

const destroy = (course) => {
    if (confirm(`Delete "${course.title}"?`)) {
        router.delete(route('courses.destroy', course.slug));
    }
};

const publish = (course) => {
    router.post(route('courses.publish', course.slug));
};

const archive = (course) => {
    router.post(route('courses.archive', course.slug));
};
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Courses" />

        <div class="mb-6 flex items-center justify-between">
            <h1 class="text-2xl font-semibold">Courses</h1>
            <Link
                v-if="canCreate"
                :href="route('courses.create')"
                class="rounded bg-gray-900 px-4 py-2 text-sm font-medium text-white"
            >
                New course
            </Link>
        </div>

        <div v-if="courses.length === 0" class="rounded border border-dashed p-8 text-center text-gray-500">
            No courses yet.
        </div>

        <table v-else class="w-full border-collapse text-left text-sm">
            <thead>
                <tr class="border-b text-gray-500">
                    <th class="py-2">Title</th>
                    <th class="py-2">Level</th>
                    <th class="py-2">Status</th>
                    <th class="py-2 text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="course in courses" :key="course.id" class="border-b">
                    <td class="py-3 font-medium">{{ course.title }}</td>
                    <td class="py-3">{{ course.level }}</td>
                    <td class="py-3">
                        <span class="rounded-full bg-gray-100 px-2 py-1 text-xs">{{ course.status }}</span>
                    </td>
                    <td class="py-3">
                        <div class="flex justify-end gap-3">
                            <Link :href="route('courses.edit', course.slug)" class="text-blue-600 hover:underline">Edit</Link>
                            <button type="button" class="text-green-600 hover:underline" @click="publish(course)">Publish</button>
                            <button type="button" class="text-amber-600 hover:underline" @click="archive(course)">Archive</button>
                            <button type="button" class="text-red-600 hover:underline" @click="destroy(course)">Delete</button>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </AuthenticatedLayout>
</template>
