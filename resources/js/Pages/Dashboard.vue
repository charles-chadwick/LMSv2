<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Compass, BookMarked, GraduationCap, ArrowRight } from 'lucide-vue-next';

const page = usePage();
const user = computed(() => page.props.auth.user);
const roles = computed(() => user.value.roles ?? []);
const canCreateCourses = computed(() => user.value.can?.create_courses ?? false);

const cards = computed(() => {
    const items = [
        {
            label: 'Browse courses',
            description: 'Explore the catalog and enroll in something new.',
            routeName: 'catalog.index',
            icon: Compass,
            iconClass: 'bg-emerald-500/15 text-emerald-600',
            gradient: 'from-emerald-500 to-teal-500',
            hover: 'hover:border-emerald-300',
        },
        {
            label: 'My courses',
            description: 'Pick up where you left off and track progress.',
            routeName: 'enrollments.index',
            icon: BookMarked,
            iconClass: 'bg-sky-500/15 text-sky-600',
            gradient: 'from-sky-500 to-indigo-500',
            hover: 'hover:border-sky-300',
        },
    ];

    if (canCreateCourses.value) {
        items.push({
            label: 'Manage courses',
            description: 'Author curriculum, publish, and review rosters.',
            routeName: 'courses.index',
            icon: GraduationCap,
            iconClass: 'bg-amber-500/15 text-amber-600',
            gradient: 'from-amber-500 to-orange-500',
            hover: 'hover:border-amber-300',
        });
    }

    return items;
});

const firstName = computed(() => (user.value.name || '').split(' ')[0]);
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Dashboard" />

        <!-- Hero -->
        <section
            class="relative mb-8 overflow-hidden rounded-3xl bg-gradient-to-br from-violet-600 via-violet-500 to-fuchsia-500 px-6 py-10 text-white shadow-lg sm:px-10 sm:py-14"
        >
            <div class="pointer-events-none absolute -right-16 -top-16 size-64 rounded-full bg-white/10 blur-2xl" />
            <div class="pointer-events-none absolute -bottom-24 -left-10 size-72 rounded-full bg-fuchsia-300/20 blur-3xl" />

            <div class="relative">
                <p class="text-sm font-semibold uppercase tracking-[0.16em] text-white/70">Dashboard</p>
                <h1 class="mt-2 font-display text-4xl font-extrabold leading-tight tracking-tight sm:text-5xl">
                    Welcome back, {{ firstName }} 👋
                </h1>
                <p class="mt-3 max-w-xl text-white/85">
                    You're signed in as
                    <span class="font-semibold capitalize">{{ roles.join(', ') || 'no role' }}</span>.
                    Here's where to head next.
                </p>
            </div>
        </section>

        <!-- Quick links -->
        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            <Link
                v-for="card in cards"
                :key="card.routeName"
                :href="route(card.routeName)"
                class="group relative overflow-hidden rounded-2xl border bg-card p-6 shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-md"
                :class="card.hover"
            >
                <div
                    class="pointer-events-none absolute inset-x-0 top-0 h-1 bg-gradient-to-r"
                    :class="card.gradient"
                />
                <div class="flex size-12 items-center justify-center rounded-xl" :class="card.iconClass">
                    <component :is="card.icon" class="size-6" :stroke-width="2.25" />
                </div>
                <h2 class="mt-4 font-display text-lg font-bold tracking-tight">{{ card.label }}</h2>
                <p class="mt-1 text-sm text-muted-foreground">{{ card.description }}</p>
                <span class="mt-4 inline-flex items-center gap-1 text-sm font-semibold text-foreground">
                    Go
                    <ArrowRight class="size-4 transition-transform group-hover:translate-x-1" />
                </span>
            </Link>
        </div>
    </AuthenticatedLayout>
</template>
