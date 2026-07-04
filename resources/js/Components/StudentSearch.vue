<script setup>
import { ref, watch, onBeforeUnmount } from 'vue';
import { Input } from '@/Components/ui/input';
import UserAvatar from '@/Components/UserAvatar.vue';
import { Search, Loader2 } from 'lucide-vue-next';

const props = defineProps({
    courseSlug: { type: String, required: true },
    invalid: { type: Boolean, default: false },
});

// The currently chosen student ({ id, name }) or null. Owned by the parent.
const selected = defineModel('selected', { default: null });

const query = ref('');
const results = ref([]);
const loading = ref(false);
const open = ref(false);

let debounce_timer = null;
let request_id = 0;
let suppress_search = false;

const runSearch = async (term) => {
    const current = ++request_id;

    try {
        const url = route('courses.roster.search', { course: props.courseSlug, q: term });
        const response = await fetch(url, { headers: { Accept: 'application/json' } });
        const data = await response.json();

        if (current !== request_id) {
            return; // A newer request superseded this one.
        }

        results.value = data;
    } catch {
        if (current === request_id) {
            results.value = [];
        }
    } finally {
        if (current === request_id) {
            loading.value = false;
        }
    }
};

watch(query, (value) => {
    if (suppress_search) {
        suppress_search = false;
        return;
    }

    // Editing the text invalidates any pending selection.
    if (selected.value !== null) {
        selected.value = null;
    }

    if (debounce_timer) {
        clearTimeout(debounce_timer);
    }

    const term = value.trim();

    if (term === '') {
        results.value = [];
        loading.value = false;
        open.value = false;

        return;
    }

    loading.value = true;
    open.value = true;
    debounce_timer = setTimeout(() => runSearch(term), 250);
});

// A parent clearing the selection (e.g. after enrolling) resets the field.
watch(selected, (value) => {
    if (value === null && query.value !== '') {
        suppress_search = true;
        query.value = '';
        results.value = [];
        open.value = false;
    }
});

const choose = (student) => {
    suppress_search = true;
    selected.value = student;
    query.value = student.name;
    results.value = [];
    open.value = false;
};

const onFocus = () => {
    if (results.value.length > 0) {
        open.value = true;
    }
};

const onBlur = () => {
    // Delay so a result click (mousedown) can register before closing.
    setTimeout(() => {
        open.value = false;
    }, 150);
};

onBeforeUnmount(() => {
    if (debounce_timer) {
        clearTimeout(debounce_timer);
    }
});
</script>

<template>
    <div class="relative w-64">
        <div class="relative">
            <Search class="pointer-events-none absolute left-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
            <Input
                v-model="query"
                type="text"
                placeholder="Search students…"
                class="bg-card pl-8"
                :aria-invalid="invalid"
                @focus="onFocus"
                @blur="onBlur"
            />
            <Loader2
                v-if="loading"
                class="absolute right-2.5 top-1/2 size-4 -translate-y-1/2 animate-spin text-muted-foreground"
            />
        </div>

        <div
            v-if="open"
            class="absolute z-50 mt-1 max-h-64 w-full overflow-auto rounded-lg border bg-popover p-1 text-popover-foreground shadow-md"
        >
            <button
                v-for="student in results"
                :key="student.id"
                type="button"
                class="flex w-full items-center gap-2.5 rounded-md px-2.5 py-1.5 text-left text-sm hover:bg-accent hover:text-accent-foreground"
                @mousedown.prevent="choose(student)"
            >
                <UserAvatar :user="student" size="sm" />
                {{ student.name }}
            </button>
            <p v-if="! loading && results.length === 0" class="px-2.5 py-1.5 text-sm text-muted-foreground">
                No matching students.
            </p>
        </div>
    </div>
</template>
