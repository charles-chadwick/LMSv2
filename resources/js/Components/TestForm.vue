<script setup>
import { computed } from 'vue';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';

const props = defineProps({
    form: {
        type: Object,
        required: true,
    },
    lessons: {
        type: Array,
        default: () => [],
    },
});

const NO_LESSON = 'none';

// shadcn Select cannot bind a null/empty value, so proxy the optional lesson
// through a sentinel string and translate back to null on the form.
const lessonSelection = computed({
    get: () => (props.form.lesson_id ? String(props.form.lesson_id) : NO_LESSON),
    set: (value) => {
        props.form.lesson_id = value === NO_LESSON ? null : Number(value);
    },
});
</script>

<template>
    <div class="space-y-6">
        <div class="grid gap-2">
            <Label for="title">Title</Label>
            <Input
                id="title"
                v-model="form.title"
                type="text"
                required
                :aria-invalid="Boolean(form.errors.title)"
            />
            <p v-if="form.errors.title" class="text-sm text-destructive">{{ form.errors.title }}</p>
        </div>

        <div class="grid gap-2">
            <Label for="lesson">Lesson (optional)</Label>
            <Select v-model="lessonSelection">
                <SelectTrigger id="lesson" class="w-full" :aria-invalid="Boolean(form.errors.lesson_id)">
                    <SelectValue placeholder="Whole course" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem :value="NO_LESSON">Whole course</SelectItem>
                    <SelectItem v-for="lesson in lessons" :key="lesson.value" :value="String(lesson.value)">
                        {{ lesson.label }}
                    </SelectItem>
                </SelectContent>
            </Select>
            <p v-if="form.errors.lesson_id" class="text-sm text-destructive">{{ form.errors.lesson_id }}</p>
        </div>

        <div class="grid gap-2">
            <Label for="description">Description</Label>
            <Textarea
                id="description"
                v-model="form.description"
                rows="5"
                :aria-invalid="Boolean(form.errors.description)"
            />
            <p v-if="form.errors.description" class="text-sm text-destructive">{{ form.errors.description }}</p>
        </div>

        <div class="grid gap-6 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="time_limit_minutes">Time limit (minutes)</Label>
                <Input
                    id="time_limit_minutes"
                    v-model="form.time_limit_minutes"
                    type="number"
                    min="1"
                    :aria-invalid="Boolean(form.errors.time_limit_minutes)"
                />
                <p v-if="form.errors.time_limit_minutes" class="text-sm text-destructive">{{ form.errors.time_limit_minutes }}</p>
            </div>

            <div class="grid gap-2">
                <Label for="max_attempts">Max attempts</Label>
                <Input
                    id="max_attempts"
                    v-model="form.max_attempts"
                    type="number"
                    min="1"
                    max="255"
                    required
                    :aria-invalid="Boolean(form.errors.max_attempts)"
                />
                <p v-if="form.errors.max_attempts" class="text-sm text-destructive">{{ form.errors.max_attempts }}</p>
            </div>

            <div class="grid gap-2">
                <Label for="passing_score">Passing score (%)</Label>
                <Input
                    id="passing_score"
                    v-model="form.passing_score"
                    type="number"
                    min="0"
                    max="100"
                    step="0.01"
                    :aria-invalid="Boolean(form.errors.passing_score)"
                />
                <p v-if="form.errors.passing_score" class="text-sm text-destructive">{{ form.errors.passing_score }}</p>
            </div>
        </div>

        <div class="grid gap-6 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="available_from">Available from</Label>
                <Input
                    id="available_from"
                    v-model="form.available_from"
                    type="datetime-local"
                    :aria-invalid="Boolean(form.errors.available_from)"
                />
                <p v-if="form.errors.available_from" class="text-sm text-destructive">{{ form.errors.available_from }}</p>
            </div>

            <div class="grid gap-2">
                <Label for="due_at">Due at</Label>
                <Input
                    id="due_at"
                    v-model="form.due_at"
                    type="datetime-local"
                    :aria-invalid="Boolean(form.errors.due_at)"
                />
                <p v-if="form.errors.due_at" class="text-sm text-destructive">{{ form.errors.due_at }}</p>
            </div>
        </div>
    </div>
</template>
