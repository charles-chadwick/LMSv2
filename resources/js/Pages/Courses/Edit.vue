<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CourseForm from '@/Components/CourseForm.vue';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
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
            <Card>
                <CardContent>
                    <CourseForm :form="form" :levels="levels" />
                </CardContent>
            </Card>

            <div class="mt-6 flex items-center gap-3">
                <Button type="submit" :disabled="form.processing">Save changes</Button>
                <Button as-child variant="ghost">
                    <Link :href="route('courses.index')">Cancel</Link>
                </Button>
            </div>
        </form>
    </AuthenticatedLayout>
</template>
