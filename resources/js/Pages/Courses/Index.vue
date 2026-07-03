<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import LevelBadge from '@/Components/LevelBadge.vue';
import Pagination from '@/Components/Pagination.vue';
import { Button } from '@/Components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Plus, MoreHorizontal, ListTree, Users, Pencil, Send, Archive, Trash2, GraduationCap } from 'lucide-vue-next';

defineProps({
    courses: {
        type: Object,
        required: true,
    },
});

const canCreate = computed(() => usePage().props.auth.user.can?.create_courses ?? false);

const destroy = (course) => {
    if (confirm(`Delete "${course.title}"?`)) {
        router.delete(route('courses.destroy', course.slug));
    }
};

const publish = (course) => {
    router.post(route('courses.publish', course.slug));
};

const archive = (course) => {
    router.post(route('courses.archive', course.slug));
};
</script>

<template>
    <AuthenticatedLayout>
        <Head title="Courses" />

        <PageHeader
            title="Courses"
            subtitle="Author curriculum, publish, and manage your rosters."
        >
            <template #actions>
                <Button v-if="canCreate" as-child class="bg-amber-500 text-white hover:bg-amber-600">
                    <Link :href="route('courses.create')">
                        <Plus class="size-4" />
                        New course
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <div
            v-if="courses.total === 0"
            class="rounded-2xl border border-dashed bg-card p-12 text-center"
        >
            <div class="mx-auto flex size-12 items-center justify-center rounded-xl bg-amber-500/15 text-amber-600">
                <GraduationCap class="size-6" />
            </div>
            <p class="mt-4 font-medium text-foreground">No courses yet</p>
            <p class="mt-1 text-sm text-muted-foreground">Create your first course to get started.</p>
        </div>

        <div v-else class="overflow-hidden rounded-2xl border bg-card shadow-sm">
            <Table>
                <TableHeader>
                    <TableRow class="bg-muted/40 hover:bg-muted/40">
                        <TableHead>Title</TableHead>
                        <TableHead class="w-40">Level</TableHead>
                        <TableHead class="w-32">Status</TableHead>
                        <TableHead class="w-20 text-right">Actions</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="course in courses.data" :key="course.id">
                        <TableCell class="font-semibold text-foreground">{{ course.title }}</TableCell>
                        <TableCell>
                            <LevelBadge :level="course.level" />
                        </TableCell>
                        <TableCell>
                            <StatusBadge :status="course.status" />
                        </TableCell>
                        <TableCell class="text-right">
                            <DropdownMenu>
                                <DropdownMenuTrigger as-child>
                                    <Button variant="ghost" size="icon" class="size-8">
                                        <MoreHorizontal class="size-4" />
                                        <span class="sr-only">Course actions</span>
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end" class="w-44">
                                    <DropdownMenuItem as-child>
                                        <Link :href="route('curriculum.show', course.slug)" class="cursor-pointer">
                                            <ListTree class="size-4" />
                                            Curriculum
                                        </Link>
                                    </DropdownMenuItem>
                                    <DropdownMenuItem as-child>
                                        <Link :href="route('courses.roster', course.slug)" class="cursor-pointer">
                                            <Users class="size-4" />
                                            Roster
                                        </Link>
                                    </DropdownMenuItem>
                                    <DropdownMenuItem as-child>
                                        <Link :href="route('courses.edit', course.slug)" class="cursor-pointer">
                                            <Pencil class="size-4" />
                                            Edit
                                        </Link>
                                    </DropdownMenuItem>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem class="cursor-pointer" @select="publish(course)">
                                        <Send class="size-4" />
                                        Publish
                                    </DropdownMenuItem>
                                    <DropdownMenuItem class="cursor-pointer" @select="archive(course)">
                                        <Archive class="size-4" />
                                        Archive
                                    </DropdownMenuItem>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem variant="destructive" class="cursor-pointer" @select="destroy(course)">
                                        <Trash2 class="size-4" />
                                        Delete
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <Pagination :paginator="courses" />
    </AuthenticatedLayout>
</template>
