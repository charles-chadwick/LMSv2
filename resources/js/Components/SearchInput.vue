<script setup>
import { ref, watch, onBeforeUnmount } from 'vue';
import { router } from '@inertiajs/vue3';
import { Input } from '@/Components/ui/input';
import { Search } from 'lucide-vue-next';

const props = defineProps({
    initial: { type: String, default: '' },
    placeholder: { type: String, default: 'Search…' },
    paramName: { type: String, default: 'search' },
});

const query = ref(props.initial);

let debounce_timer = null;

watch(query, (value) => {
    if (debounce_timer) {
        clearTimeout(debounce_timer);
    }

    debounce_timer = setTimeout(() => {
        const term = value.trim();
        const data = term === '' ? {} : { [props.paramName]: term };

        router.get(window.location.pathname, data, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }, 300);
});

onBeforeUnmount(() => {
    if (debounce_timer) {
        clearTimeout(debounce_timer);
    }
});
</script>

<template>
    <div class="relative w-full max-w-sm">
        <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
        <Input
            v-model="query"
            type="search"
            :placeholder="placeholder"
            class="pl-9"
        />
    </div>
</template>
