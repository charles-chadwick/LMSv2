<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import LessonDiscussions from '@/Components/LessonDiscussions.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    course: { type: Object, required: true },
    lesson: { type: Object, required: true },
    prev: { type: Object, default: null },
    next: { type: Object, default: null },
    is_complete: { type: Boolean, required: true },
    progress_percentage: { type: Number, required: true },
    lessonDiscussions: { type: Array, default: () => [] },
});
</script>

<template>
    <AuthenticatedLayout>
        <Head :title="lesson.title" />

        <div class="mb-4">
            <Link :href="route('catalog.show', course.slug)" class="text-sm text-blue-600 hover:underline">
                &larr; {{ course.title }}
            </Link>
        </div>

        <div class="mb-6">
            <div class="mb-1 flex items-center justify-between text-xs text-gray-500">
                <span>Progress</span>
                <span>{{ progress_percentage }}%</span>
            </div>
            <div class="h-2 w-full rounded-full bg-gray-200">
                <div class="h-2 rounded-full bg-green-500" :style="{ width: progress_percentage + '%' }" />
            </div>
        </div>

        <h1 class="mb-4 text-2xl font-semibold">{{ lesson.title }}</h1>

        <div class="prose mb-8 max-w-none text-gray-700" v-html="lesson.content" />

        <div v-if="is_complete" class="mb-8">
            <span class="rounded bg-green-100 px-4 py-2 text-sm font-medium text-green-700">
                Completed &check;
            </span>
        </div>

        <div class="flex items-center justify-between border-t pt-4 text-sm">
            <Link
                v-if="prev"
                :href="route('lessons.show', [course.slug, prev.slug])"
                class="text-blue-600 hover:underline"
            >
                &larr; {{ prev.title }}
            </Link>
            <span v-else />
            <Link
                v-if="next"
                :href="route('lessons.show', [course.slug, next.slug])"
                class="text-blue-600 hover:underline"
            >
                {{ next.title }} &rarr;
            </Link>
            <span v-else />
        </div>

        <LessonDiscussions :course="course" :lesson="lesson" :discussions="lessonDiscussions" />
    </AuthenticatedLayout>
</template>
