<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import QuestionForm from '@/Components/QuestionForm.vue';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    course: {
        type: Object,
        required: true,
    },
    test: {
        type: Object,
        required: true,
    },
    question: {
        type: Object,
        required: true,
    },
    questionTypes: {
        type: Array,
        default: () => [],
    },
});

const form = useForm({
    prompt: props.question.prompt,
    type: props.question.type,
    points: props.question.points,
    options: props.question.options.map((option) => ({
        id: option.id,
        text: option.text,
        is_correct: option.is_correct,
    })),
});

const submit = () => {
    form.put(route('questions.update', props.question.id));
};
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Edit question" />

        <h1 class="mb-1 text-2xl font-semibold">Edit question</h1>
        <p class="mb-6 text-sm text-muted-foreground">{{ test.title }}</p>

        <form class="max-w-2xl" @submit.prevent="submit">
            <Card>
                <CardContent>
                    <QuestionForm :form="form" :question-types="questionTypes" />
                </CardContent>
            </Card>

            <div class="mt-6 flex items-center gap-3">
                <Button type="submit" :disabled="form.processing">Save changes</Button>
                <Button as-child variant="ghost">
                    <Link :href="route('questions.index', test.id)">Cancel</Link>
                </Button>
            </div>
        </form>
    </AuthenticatedLayout>
</template>
