<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import RichTextEditor from '@/Components/RichTextEditor.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import draggable from 'vuedraggable';

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
const deleteModule = (module) => {
    if (confirm(`Delete module "${module.title}" and its lessons?`)) {
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
const deleteLesson = (lesson) => {
    if (confirm(`Delete lesson "${lesson.title}"?`)) {
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

                    <div class="mt-3 flex gap-2 pl-6">
                        <input
                            v-model="newLessonTitle[module.id]"
                            class="flex-1 rounded border-gray-300 text-sm"
                            placeholder="New lesson title"
                            @keyup.enter="addLesson(module)"
                        />
                        <button type="button" class="rounded bg-gray-900 px-3 py-1 text-sm text-white" @click="addLesson(module)">
                            Add lesson
                        </button>
                    </div>
                </div>
            </template>
        </draggable>

        <div class="mt-6 flex gap-2">
            <input
                v-model="newModuleTitle"
                class="flex-1 rounded border-gray-300 text-sm"
                placeholder="New module title"
                @keyup.enter="addModule"
            />
            <button type="button" class="rounded bg-gray-900 px-4 py-2 text-sm font-medium text-white" @click="addModule">
                Add module
            </button>
        </div>
    </AuthenticatedLayout>
</template>
