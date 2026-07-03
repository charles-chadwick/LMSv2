<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    course: { type: Object, required: true },
    is_enrolled: { type: Boolean, required: true },
    can_learn: { type: Boolean, default: false },
    completed_lesson_ids: { type: Array, default: () => [] },
    first_incomplete_lesson_slug: { type: String, default: null },
    enrollment_id: { type: Number, default: null },
    enrollment_status: { type: String, default: null },
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

const dropping = ref(false);

const drop = () => {
    if (! confirm(`Drop "${props.course.title}"? Your progress is saved if you re-enroll.`)) {
        return;
    }
    dropping.value = true;
    router.delete(route('enrollments.destroy', props.enrollment_id), {
        preserveScroll: true,
        onFinish: () => {
            dropping.value = false;
        },
    });
};

const isComplete = (lesson) => props.completed_lesson_ids.includes(lesson.id);
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
            <div class="flex items-center gap-2">
                <Link
                    v-if="can_learn && first_incomplete_lesson_slug"
                    :href="route('lessons.show', [course.slug, first_incomplete_lesson_slug])"
                    class="rounded bg-gray-900 px-4 py-2 text-sm font-medium text-white"
                >
                    Continue learning
                </Link>
                <template v-if="is_enrolled && enrollment_status !== 'Dropped'">
                    <span class="rounded bg-green-100 px-4 py-2 text-sm font-medium text-green-700">
                        Enrolled
                    </span>
                    <button
                        v-if="enrollment_status === 'Active'"
                        type="button"
                        :disabled="dropping"
                        class="rounded border border-red-300 px-4 py-2 text-sm font-medium text-red-600 disabled:opacity-50"
                        @click="drop"
                    >
                        Drop course
                    </button>
                </template>
                <button
                    v-if="! is_enrolled || enrollment_status === 'Dropped'"
                    type="button"
                    :disabled="enrolling"
                    class="rounded bg-gray-900 px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
                    @click="enroll"
                >
                    {{ enrollment_status === 'Dropped' ? 'Re-enroll' : 'Enroll' }}
                </button>
            </div>
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
                <ul class="mt-2 space-y-1 pl-1 text-sm text-gray-600">
                    <li v-for="lesson in module.lessons" :key="lesson.id" class="flex items-center gap-2">
                        <span v-if="isComplete(lesson)" class="text-green-600">&check;</span>
                        <span v-else class="text-gray-300">&bull;</span>
                        <Link
                            v-if="can_learn"
                            :href="route('lessons.show', [course.slug, lesson.slug])"
                            class="text-blue-600 hover:underline"
                        >
                            {{ lesson.title }}
                        </Link>
                        <span v-else>{{ lesson.title }}</span>
                    </li>
                </ul>
            </li>
        </ol>
    </AuthenticatedLayout>
</template>
