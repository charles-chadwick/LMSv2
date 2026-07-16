<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import RichTextEditor from '@/Components/RichTextEditor.vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Head, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import draggable from 'vuedraggable';
import { useConfirm } from '@/composables/useConfirm';

const { confirm } = useConfirm();

const props = defineProps({
    course: { type: Object, required: true },
    modules: { type: Array, required: true },
});

// Local, drag-mutable deep copy; re-seeded whenever Inertia reloads props.
const moduleList = ref(clone(props.modules));
watch(() => props.modules, (value) => {
    moduleList.value = clone(value);
});

function clone(value) {
    return JSON.parse(JSON.stringify(value));
}

const reload = { preserveScroll: true };

// Modules
const newModuleTitle = ref('');
const addModule = () => {
    if (! newModuleTitle.value.trim()) {
        return;
    }
    router.post(route('modules.store', props.course.slug), { title: newModuleTitle.value }, {
        ...reload,
        onSuccess: () => {
            newModuleTitle.value = '';
        },
    });
};
const updateModule = (module) => {
    router.put(route('modules.update', module.id), { title: module.title, description: module.description }, reload);
};
const deleteModule = async (module) => {
    const confirmed = await confirm({
        title: 'Delete module',
        description: `Delete module "${module.title}" and its lessons? This cannot be undone.`,
        confirmText: 'Delete',
        variant: 'destructive',
    });

    if (confirmed) {
        router.delete(route('modules.destroy', module.id), reload);
    }
};
const persistModuleOrder = () => {
    router.post(route('modules.reorder', props.course.slug), {
        modules: moduleList.value.map((m) => m.id),
    }, reload);
};

// Lessons
const newLessonTitle = ref({});
const addLesson = (module) => {
    const title = (newLessonTitle.value[module.id] ?? '').trim();
    if (! title) {
        return;
    }
    router.post(route('lessons.store', module.id), { title }, {
        ...reload,
        onSuccess: () => {
            newLessonTitle.value[module.id] = '';
        },
    });
};
const updateLesson = (lesson) => {
    router.put(route('lessons.update', lesson.slug), {
        title: lesson.title,
        content: lesson.content,
        duration_minutes: lesson.duration_minutes,
    }, reload);
};
const deleteLesson = async (lesson) => {
    const confirmed = await confirm({
        title: 'Delete lesson',
        description: `Delete lesson "${lesson.title}"? This cannot be undone.`,
        confirmText: 'Delete',
        variant: 'destructive',
    });

    if (confirmed) {
        router.delete(route('lessons.destroy', lesson.slug), reload);
    }
};
const persistLessonOrder = (module) => {
    router.post(route('lessons.reorder', module.id), {
        lessons: module.lessons.map((l) => l.id),
    }, reload);
};
</script>

<template>
    <AuthenticatedLayout>
        <Head :title="`Curriculum · ${course.title}`" />

        <h1 class="mb-6 text-2xl font-semibold">{{ course.title }} — Curriculum</h1>

        <draggable v-model="moduleList" item-key="id" handle=".module-handle" class="space-y-4" @end="persistModuleOrder">
            <template #item="{ element: module }">
                <div class="rounded border p-4">
                    <div class="mb-3 flex items-center gap-2">
                        <span class="module-handle cursor-move text-gray-400">⠿</span>
                        <input
                            v-model="module.title"
                            class="flex-1 rounded border-gray-300 text-sm font-medium"
                            @blur="updateModule(module)"
                        />
                        <button type="button" class="text-sm text-red-600 hover:underline" @click="deleteModule(module)">
                            Delete
                        </button>
                    </div>

                    <draggable
                        v-model="module.lessons"
                        item-key="id"
                        handle=".lesson-handle"
                        class="space-y-2 pl-6"
                        @end="persistLessonOrder(module)"
                    >
                        <template #item="{ element: lesson }">
                            <div class="rounded bg-gray-50 p-3">
                                <div class="flex items-center gap-2">
                                    <span class="lesson-handle cursor-move text-gray-400">⠿</span>
                                    <input
                                        v-model="lesson.title"
                                        class="flex-1 rounded border-gray-300 text-sm"
                                        @blur="updateLesson(lesson)"
                                    />
                                    <input
                                        v-model.number="lesson.duration_minutes"
                                        type="number"
                                        min="0"
                                        class="w-20 rounded border-gray-300 text-sm"
                                        placeholder="min"
                                        @blur="updateLesson(lesson)"
                                    />
                                    <button type="button" class="text-sm text-red-600 hover:underline" @click="deleteLesson(lesson)">
                                        Delete
                                    </button>
                                </div>
                                <div class="mt-2">
                                    <RichTextEditor v-model="lesson.content" @blur="updateLesson(lesson)" />
                                </div>
                            </div>
                        </template>
                    </draggable>

                    <div class="mt-4 ml-6 rounded-lg border border-dashed bg-muted/40 p-4">
                        <div class="grid gap-2">
                            <Label :for="`new-lesson-${module.id}`" class="text-sm font-medium">
                                Add a lesson to this module
                            </Label>
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-end">
                                <Input
                                    :id="`new-lesson-${module.id}`"
                                    v-model="newLessonTitle[module.id]"
                                    type="text"
                                    class="flex-1"
                                    placeholder="e.g. Introduction to variables"
                                    @keyup.enter="addLesson(module)"
                                />
                                <Button type="button" @click="addLesson(module)">
                                    + Add lesson
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </draggable>

        <div class="mt-8 rounded-lg border-2 border-dashed bg-muted/40 p-6">
            <h2 class="mb-1 text-lg font-semibold">Add a new module</h2>
            <p class="mb-4 text-sm text-muted-foreground">
                Modules group related lessons together. Give it a clear title to get started.
            </p>
            <div class="grid gap-2">
                <Label for="new-module-title" class="text-sm font-medium">Module title</Label>
                <div class="flex flex-col gap-2 sm:flex-row sm:items-end">
                    <Input
                        id="new-module-title"
                        v-model="newModuleTitle"
                        type="text"
                        class="flex-1"
                        placeholder="e.g. Getting Started"
                        @keyup.enter="addModule"
                    />
                    <Button type="button" size="lg" @click="addModule">
                        + Add module
                    </Button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
