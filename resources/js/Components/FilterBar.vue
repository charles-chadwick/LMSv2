<script setup>
import { reactive, ref, computed, watch, onBeforeUnmount } from 'vue';
import { router } from '@inertiajs/vue3';
import { Input } from '@/Components/ui/input';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuCheckboxItem,
    DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import { Search, ChevronDown, X } from 'lucide-vue-next';

const props = defineProps({
    filters: {
        type: Object,
        default: () => ({}),
    },
    filterOptions: {
        type: Array,
        default: () => [],
    },
});

const search = ref(props.filters.search ?? '');

const values = reactive({});
props.filterOptions.forEach((option) => {
    if (option.type === 'daterange') {
        const current = props.filters[option.key] ?? {};
        values[option.key] = { from: current.from ?? '', to: current.to ?? '' };
    } else {
        values[option.key] = [...(props.filters[option.key] ?? [])];
    }
});

const buildQuery = () => {
    const query = {};
    const term = search.value.trim();
    if (term !== '') {
        query.search = term;
    }

    const filters = {};
    props.filterOptions.forEach((option) => {
        if (option.type === 'daterange') {
            const range = {};
            if (values[option.key].from) {
                range.from = values[option.key].from;
            }
            if (values[option.key].to) {
                range.to = values[option.key].to;
            }
            if (Object.keys(range).length > 0) {
                filters[option.key] = range;
            }
        } else if (values[option.key].length > 0) {
            filters[option.key] = values[option.key];
        }
    });

    if (Object.keys(filters).length > 0) {
        query.filters = filters;
    }

    return query;
};

const apply = () => {
    router.get(window.location.pathname, buildQuery(), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

let debounce_timer = null;
watch(search, () => {
    if (debounce_timer) {
        clearTimeout(debounce_timer);
    }
    debounce_timer = setTimeout(apply, 300);
});
onBeforeUnmount(() => {
    if (debounce_timer) {
        clearTimeout(debounce_timer);
    }
});

const toggleValue = (key, value) => {
    const current = values[key];
    const index = current.indexOf(value);
    if (index === -1) {
        current.push(value);
    } else {
        current.splice(index, 1);
    }
    apply();
};

const isChecked = (key, value) => values[key].includes(value);

const activeCount = (key) => values[key].length;

const hasActiveFilters = computed(() =>
    props.filterOptions.some((option) =>
        option.type === 'daterange'
            ? Boolean(values[option.key].from || values[option.key].to)
            : values[option.key].length > 0,
    ),
);

const clearFilters = () => {
    props.filterOptions.forEach((option) => {
        if (option.type === 'daterange') {
            values[option.key].from = '';
            values[option.key].to = '';
        } else {
            values[option.key] = [];
        }
    });
    apply();
};
</script>

<template>
    <div class="flex flex-wrap items-center gap-2">
        <div class="relative w-full max-w-xs">
            <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
            <Input v-model="search" type="search" placeholder="Search…" class="pl-9" />
        </div>

        <template v-for="option in filterOptions" :key="option.key">
            <DropdownMenu v-if="option.type === 'select'">
                <DropdownMenuTrigger as-child>
                    <Button variant="outline" class="gap-1.5">
                        {{ option.label }}
                        <Badge v-if="activeCount(option.key) > 0" variant="secondary" class="ml-0.5">
                            {{ activeCount(option.key) }}
                        </Badge>
                        <ChevronDown class="size-4 opacity-60" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="start" class="w-48">
                    <DropdownMenuLabel>{{ option.label }}</DropdownMenuLabel>
                    <DropdownMenuSeparator />
                    <DropdownMenuCheckboxItem
                        v-for="choice in option.options"
                        :key="choice.value"
                        :model-value="isChecked(option.key, choice.value)"
                        @update:model-value="toggleValue(option.key, choice.value)"
                        @select="(event) => event.preventDefault()"
                    >
                        {{ choice.label }}
                    </DropdownMenuCheckboxItem>
                </DropdownMenuContent>
            </DropdownMenu>

            <div v-else-if="option.type === 'daterange'" class="flex items-center gap-1.5">
                <span class="text-sm text-muted-foreground">{{ option.label }}</span>
                <Input v-model="values[option.key].from" type="date" class="w-auto" @change="apply" />
                <span class="text-muted-foreground">–</span>
                <Input v-model="values[option.key].to" type="date" class="w-auto" @change="apply" />
            </div>
        </template>

        <Button v-if="hasActiveFilters" variant="ghost" size="sm" class="text-muted-foreground" @click="clearFilters">
            <X class="size-4" />
            Clear filters
        </Button>
    </div>
</template>
