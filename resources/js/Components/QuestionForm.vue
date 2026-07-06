<script setup>
import { computed } from 'vue';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import { Button } from '@/Components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';
import { Plus, Trash2 } from 'lucide-vue-next';

const props = defineProps({
    form: {
        type: Object,
        required: true,
    },
    questionTypes: {
        type: Array,
        default: () => [],
    },
});

const selectedType = computed(() =>
    props.questionTypes.find((questionType) => questionType.value === props.form.type),
);

// Types flagged gradable require answer options; others (Short Answer, Essay)
// are graded manually and carry none.
const requiresOptions = computed(() => Boolean(selectedType.value?.gradable));

// Types with preset options (e.g. True/False) own a fixed, non-editable set.
const hasFixedOptions = computed(() => (selectedType.value?.presetOptions?.length ?? 0) > 0);

const onTypeChange = (value) => {
    props.form.type = value;
    const questionType = props.questionTypes.find((candidate) => candidate.value === value);

    if (!questionType?.gradable) {
        props.form.options = [];

        return;
    }

    if (questionType.presetOptions?.length) {
        props.form.options = questionType.presetOptions.map((option) => ({ id: null, ...option }));

        return;
    }

    if (props.form.options.length === 0) {
        props.form.options = [
            { id: null, text: '', is_correct: false },
            { id: null, text: '', is_correct: false },
        ];
    }
};

const addOption = () => {
    props.form.options.push({ id: null, text: '', is_correct: false });
};

const removeOption = (index) => {
    props.form.options.splice(index, 1);
};

const optionError = (index, field) => props.form.errors[`options.${index}.${field}`];
</script>

<template>
    <div class="space-y-6">
        <div class="grid gap-2">
            <Label for="prompt">Prompt</Label>
            <Textarea
                id="prompt"
                v-model="form.prompt"
                rows="3"
                required
                :aria-invalid="Boolean(form.errors.prompt)"
            />
            <p v-if="form.errors.prompt" class="text-sm text-destructive">{{ form.errors.prompt }}</p>
        </div>

        <div class="grid gap-6 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="type">Type</Label>
                <Select :model-value="form.type" @update:model-value="onTypeChange">
                    <SelectTrigger id="type" class="w-full" :aria-invalid="Boolean(form.errors.type)">
                        <SelectValue placeholder="Select a type" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="questionType in questionTypes"
                            :key="questionType.value"
                            :value="questionType.value"
                        >
                            {{ questionType.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <p v-if="form.errors.type" class="text-sm text-destructive">{{ form.errors.type }}</p>
            </div>

            <div class="grid gap-2">
                <Label for="points">Points</Label>
                <Input
                    id="points"
                    v-model="form.points"
                    type="number"
                    min="1"
                    max="1000"
                    required
                    :aria-invalid="Boolean(form.errors.points)"
                />
                <p v-if="form.errors.points" class="text-sm text-destructive">{{ form.errors.points }}</p>
            </div>
        </div>

        <div v-if="requiresOptions" class="grid gap-3">
            <div class="flex items-center justify-between">
                <Label>Answer options</Label>
                <Button
                    v-if="!hasFixedOptions"
                    type="button"
                    variant="outline"
                    size="sm"
                    @click="addOption"
                >
                    <Plus class="size-4" />
                    Add option
                </Button>
            </div>
            <p class="text-sm text-muted-foreground">Check the box next to every correct answer.</p>

            <div
                v-for="(option, index) in form.options"
                :key="index"
                class="flex items-start gap-3"
            >
                <input
                    :id="`option-correct-${index}`"
                    v-model="option.is_correct"
                    type="checkbox"
                    class="mt-3 size-4 rounded border-input text-amber-600 focus:ring-amber-500"
                />
                <div class="grid flex-1 gap-1">
                    <Input
                        v-model="option.text"
                        type="text"
                        :readonly="hasFixedOptions"
                        :aria-label="`Option ${index + 1} text`"
                        :aria-invalid="Boolean(optionError(index, 'text'))"
                    />
                    <p v-if="optionError(index, 'text')" class="text-sm text-destructive">
                        {{ optionError(index, 'text') }}
                    </p>
                </div>
                <Button
                    v-if="!hasFixedOptions"
                    type="button"
                    variant="ghost"
                    size="icon"
                    class="mt-0.5 size-9"
                    @click="removeOption(index)"
                >
                    <Trash2 class="size-4" />
                    <span class="sr-only">Remove option</span>
                </Button>
            </div>

            <p v-if="form.errors.options" class="text-sm text-destructive">{{ form.errors.options }}</p>
        </div>
    </div>
</template>
