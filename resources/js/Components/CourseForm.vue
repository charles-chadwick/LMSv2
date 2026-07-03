<script setup>
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

defineProps({
    form: {
        type: Object,
        required: true,
    },
    levels: {
        type: Array,
        required: true,
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
            <Label for="level">Level</Label>
            <Select v-model="form.level">
                <SelectTrigger id="level" class="w-full" :aria-invalid="Boolean(form.errors.level)">
                    <SelectValue placeholder="Select a level" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem v-for="level in levels" :key="level.value" :value="level.value">
                        {{ level.label }}
                    </SelectItem>
                </SelectContent>
            </Select>
            <p v-if="form.errors.level" class="text-sm text-destructive">{{ form.errors.level }}</p>
        </div>

        <div class="grid gap-2">
            <Label for="summary">Summary</Label>
            <Input
                id="summary"
                v-model="form.summary"
                type="text"
                :aria-invalid="Boolean(form.errors.summary)"
            />
            <p v-if="form.errors.summary" class="text-sm text-destructive">{{ form.errors.summary }}</p>
        </div>

        <div class="grid gap-2">
            <Label for="description">Description</Label>
            <Textarea
                id="description"
                v-model="form.description"
                rows="6"
                :aria-invalid="Boolean(form.errors.description)"
            />
            <p v-if="form.errors.description" class="text-sm text-destructive">{{ form.errors.description }}</p>
        </div>
    </div>
</template>
