<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    course: {
        type: Object,
        required: true,
    },
    is_enrolled: {
        type: Boolean,
        required: true,
    },
});

const enrolling = ref(false);

const enroll = () => {
    enrolling.value = true;
    router.post(route('courses.enroll', props.course.slug), {}, {
        preserveScroll: true,
        onFinish: () => {
            enrolling.value = false;
        },
    });
};
</script>

<template>
    <AuthenticatedLayout>
        <Head :title="course.title" />

        <div class="mb-6 flex items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold">{{ course.title }}</h1>
                <p class="mt-1 text-sm text-gray-500">
                    {{ course.instructor }} · <span class="capitalize">{{ course.level }}</span>
                </p>
            </div>
            <span v-if="is_enrolled" class="rounded bg-green-100 px-4 py-2 text-sm font-medium text-green-700">
                Enrolled
            </span>
            <button
                v-else
                type="button"
                :disabled="enrolling"
                class="rounded bg-gray-900 px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
                @click="enroll"
            >
                Enroll
            </button>
        </div>

        <p v-if="course.summary" class="mb-4 text-gray-700">{{ course.summary }}</p>
        <p v-if="course.description" class="mb-8 whitespace-pre-line text-gray-600">{{ course.description }}</p>

        <h2 class="mb-3 text-lg font-semibold">Syllabus</h2>
        <div v-if="course.modules.length === 0" class="text-sm text-gray-500">
            No modules yet.
        </div>
        <ol v-else class="space-y-4">
            <li v-for="(module, index) in course.modules" :key="index" class="rounded border p-4">
                <h3 class="font-medium">{{ module.title }}</h3>
                <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-gray-600">
                    <li v-for="(lesson, lessonIndex) in module.lessons" :key="lessonIndex">
                        {{ lesson.title }}
                    </li>
                </ul>
            </li>
        </ol>
    </AuthenticatedLayout>
</template>
