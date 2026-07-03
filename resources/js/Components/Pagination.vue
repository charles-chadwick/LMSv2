<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';
import { cn } from '@/lib/utils';

const props = defineProps({
    // A Laravel paginator serialized to its flat shape:
    // { data, links, current_page, last_page, total, prev_page_url, next_page_url }
    paginator: { type: Object, required: true },
});

// Only the numbered entries — Laravel wraps them with Previous/Next at the ends,
// which we render separately as chevrons.
const number_links = computed(() => props.paginator.links.slice(1, -1));

const shouldShow = computed(() => (props.paginator.last_page ?? 1) > 1);

const base_class =
    'inline-flex h-9 min-w-9 items-center justify-center rounded-lg px-2 text-sm font-medium transition-colors';
</script>

<template>
    <nav v-if="shouldShow" aria-label="Pagination" class="mt-6 flex items-center justify-center gap-1">
        <!-- Previous -->
        <Link
            v-if="paginator.prev_page_url"
            :href="paginator.prev_page_url"
            preserve-scroll
            preserve-state
            rel="prev"
            aria-label="Previous page"
            :class="cn(base_class, 'border hover:bg-muted')"
        >
            <ChevronLeft class="size-4" />
        </Link>
        <span v-else :class="cn(base_class, 'border text-muted-foreground/40')" aria-hidden="true">
            <ChevronLeft class="size-4" />
        </span>

        <!-- Page numbers -->
        <template v-for="(link, index) in number_links" :key="index">
            <Link
                v-if="link.url"
                :href="link.url"
                preserve-scroll
                preserve-state
                :aria-current="link.active ? 'page' : undefined"
                :class="
                    cn(
                        base_class,
                        link.active
                            ? 'bg-foreground text-background'
                            : 'border hover:bg-muted',
                    )
                "
            >
                {{ link.label }}
            </Link>
            <span v-else :class="cn(base_class, 'text-muted-foreground')" aria-hidden="true">
                {{ link.label }}
            </span>
        </template>

        <!-- Next -->
        <Link
            v-if="paginator.next_page_url"
            :href="paginator.next_page_url"
            preserve-scroll
            preserve-state
            rel="next"
            aria-label="Next page"
            :class="cn(base_class, 'border hover:bg-muted')"
        >
            <ChevronRight class="size-4" />
        </Link>
        <span v-else :class="cn(base_class, 'border text-muted-foreground/40')" aria-hidden="true">
            <ChevronRight class="size-4" />
        </span>
    </nav>
</template>
