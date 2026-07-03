<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    course: { type: Object, required: true },
    lesson: { type: Object, required: true },
    prev: { type: Object, default: null },
    next: { type: Object, default: null },
    is_complete: { type: Boolean, required: true },
    can_complete: { type: Boolean, required: true },
    progress_percentage: { type: Number, required: true },
});

const completing = ref(false);

const markComplete = () => {
    completing.value = true;
    router.post(route('lessons.complete', [props.course.slug, props.lesson.slug]), {}, {
        preserveScroll: true,
        onFinish: () => {
            completing.value = false;
        },
    });
};
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

        <div class="mb-8 whitespace-pre-line text-gray-700">{{ lesson.content }}</div>

        <div v-if="can_complete" class="mb-8">
            <span v-if="is_complete" class="rounded bg-green-100 px-4 py-2 text-sm font-medium text-green-700">
                Completed &check;
            </span>
            <button
                v-else
                type="button"
                :disabled="completing"
                class="rounded bg-gray-900 px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
                @click="markComplete"
            >
                Mark as complete
            </button>
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
    </AuthenticatedLayout>
</template>
