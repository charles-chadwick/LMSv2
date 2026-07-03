<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    enrollments: {
        type: Array,
        required: true,
    },
});
</script>

<template>
    <AuthenticatedLayout>
        <Head title="My courses" />

        <h1 class="mb-6 text-2xl font-semibold">My courses</h1>

        <div v-if="enrollments.length === 0" class="rounded border border-dashed p-8 text-center text-gray-500">
            You haven't enrolled in any courses yet.
        </div>

        <table v-else class="w-full border-collapse text-left text-sm">
            <thead>
                <tr class="border-b text-gray-500">
                    <th class="py-2">Course</th>
                    <th class="py-2">Status</th>
                    <th class="py-2">Progress</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="enrollment in enrollments" :key="enrollment.course_slug" class="border-b">
                    <td class="py-3 font-medium">
                        <Link :href="route('catalog.show', enrollment.course_slug)" class="text-blue-600 hover:underline">
                            {{ enrollment.course_title }}
                        </Link>
                    </td>
                    <td class="py-3">
                        <span class="rounded-full bg-gray-100 px-2 py-1 text-xs">{{ enrollment.status }}</span>
                    </td>
                    <td class="py-3">{{ enrollment.progress_percentage }}%</td>
                </tr>
            </tbody>
        </table>
    </AuthenticatedLayout>
</template>
