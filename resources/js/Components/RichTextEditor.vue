<script setup>
import { Editor, EditorContent } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import Link from '@tiptap/extension-link';
import { onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps({
    modelValue: { type: String, default: '' },
});
const emit = defineEmits(['update:modelValue', 'blur']);

const editor = ref(
    new Editor({
        content: props.modelValue || '',
        extensions: [StarterKit, Link.configure({ openOnClick: false })],
        onUpdate: ({ editor }) => {
            emit('update:modelValue', editor.getHTML());
        },
        onBlur: () => {
            emit('blur');
        },
    }),
);

// Keep the editor in sync if the bound value changes externally (e.g. props reload).
watch(() => props.modelValue, (value) => {
    if (editor.value && value !== editor.value.getHTML()) {
        editor.value.commands.setContent(value || '', false);
    }
});

onBeforeUnmount(() => {
    editor.value?.destroy();
});
</script>

<template>
    <div class="rounded border border-gray-300">
        <div v-if="editor" class="flex flex-wrap gap-1 border-b bg-gray-50 p-1 text-sm">
            <button type="button" class="rounded px-2 py-1 hover:bg-gray-200" :class="{ 'bg-gray-200': editor.isActive('bold') }" @click="editor.chain().focus().toggleBold().run()">B</button>
            <button type="button" class="rounded px-2 py-1 italic hover:bg-gray-200" :class="{ 'bg-gray-200': editor.isActive('italic') }" @click="editor.chain().focus().toggleItalic().run()">i</button>
            <button type="button" class="rounded px-2 py-1 hover:bg-gray-200" :class="{ 'bg-gray-200': editor.isActive('heading', { level: 2 }) }" @click="editor.chain().focus().toggleHeading({ level: 2 }).run()">H2</button>
            <button type="button" class="rounded px-2 py-1 hover:bg-gray-200" :class="{ 'bg-gray-200': editor.isActive('bulletList') }" @click="editor.chain().focus().toggleBulletList().run()">• List</button>
            <button type="button" class="rounded px-2 py-1 hover:bg-gray-200" :class="{ 'bg-gray-200': editor.isActive('orderedList') }" @click="editor.chain().focus().toggleOrderedList().run()">1. List</button>
        </div>
        <EditorContent :editor="editor" class="prose max-w-none p-3 text-sm focus:outline-none" />
    </div>
</template>
