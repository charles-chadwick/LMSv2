<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CourseForm from '@/Components/CourseForm.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    course: {
        type: Object,
        required: true,
    },
    levels: {
        type: Array,
        required: true,
    },
});

const form = useForm({
    title: props.course.title,
    summary: props.course.summary ?? '',
    description: props.course.description ?? '',
    level: props.course.level ?? '',
});

const submit = () => {
    form.put(route('courses.update', props.course.slug));
};
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Edit course" />

        <h1 class="mb-6 text-2xl font-semibold">Edit course</h1>

        <form class="max-w-2xl" @submit.prevent="submit">
            <CourseForm :form="form" :levels="levels" />

            <div class="mt-6 flex items-center gap-3">
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="rounded bg-gray-900 px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
                >
                    Save changes
                </button>
                <Link :href="route('courses.index')" class="text-sm text-gray-600 hover:underline">Cancel</Link>
            </div>
        </form>
    </AuthenticatedLayout>
</template>
