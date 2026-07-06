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
    questionTypes: {
        type: Array,
        default: () => [],
    },
});

// Seed the initial options to match the default type, mirroring the form's
// own onTypeChange logic so a fresh Multiple Choice starts with two blank rows.
const initialOptions = (questionType) => {
    if (!questionType?.gradable) {
        return [];
    }

    if (questionType.presetOptions?.length) {
        return questionType.presetOptions.map((option) => ({ id: null, ...option }));
    }

    return [
        { id: null, text: '', is_correct: false },
        { id: null, text: '', is_correct: false },
    ];
};

const defaultType = props.questionTypes[0];

const form = useForm({
    prompt: '',
    type: defaultType?.value ?? '',
    points: 1,
    options: initialOptions(defaultType),
});

const submit = () => {
    form.post(route('questions.store', props.test.id));
};
</script>

<template>
    <AuthenticatedLayout>
        <Head title="New question" />

        <h1 class="mb-1 text-2xl font-semibold">New question</h1>
        <p class="mb-6 text-sm text-muted-foreground">{{ test.title }}</p>

        <form class="max-w-2xl" @submit.prevent="submit">
            <Card>
                <CardContent>
                    <QuestionForm :form="form" :question-types="questionTypes" />
                </CardContent>
            </Card>

            <div class="mt-6 flex items-center gap-3">
                <Button type="submit" :disabled="form.processing">Create question</Button>
                <Button as-child variant="ghost">
                    <Link :href="route('questions.index', test.id)">Cancel</Link>
                </Button>
            </div>
        </form>
    </AuthenticatedLayout>
</template>
