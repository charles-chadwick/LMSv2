<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import ProgressBar from '@/Components/ProgressBar.vue';
import { Avatar, AvatarFallback } from '@/Components/ui/avatar';
import { Button } from '@/Components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import { Head, router } from '@inertiajs/vue3';
import { Users } from 'lucide-vue-next';

const props = defineProps({
    course: { type: Object, required: true },
    students: { type: Array, required: true },
});

const remove = (student) => {
    if (! confirm(`Remove ${student.name} from "${props.course.title}"?`)) {
        return;
    }
    router.delete(route('enrollments.destroy', student.id), { preserveScroll: true });
};

const initialsFor = (name) => {
    return (name || '?')
        .split(' ')
        .map((part) => part[0])
        .filter(Boolean)
        .slice(0, 2)
        .join('')
        .toUpperCase();
};
</script>

<template>
    <AuthenticatedLayout>
        <Head :title="`Roster — ${course.title}`" />

        <PageHeader
            :title="course.title"
            eyebrow="Roster"
            :subtitle="`${students.length} ${students.length === 1 ? 'student' : 'students'} enrolled`"
        />

        <div
            v-if="students.length === 0"
            class="rounded-2xl border border-dashed bg-card p-12 text-center"
        >
            <div class="mx-auto flex size-12 items-center justify-center rounded-xl bg-amber-500/15 text-amber-600">
                <Users class="size-6" />
            </div>
            <p class="mt-4 font-medium text-foreground">No students enrolled yet</p>
            <p class="mt-1 text-sm text-muted-foreground">Enrollments will appear here as students join.</p>
        </div>

        <div v-else class="overflow-hidden rounded-2xl border bg-card shadow-sm">
            <Table>
                <TableHeader>
                    <TableRow class="bg-muted/40 hover:bg-muted/40">
                        <TableHead>Student</TableHead>
                        <TableHead class="w-32">Status</TableHead>
                        <TableHead class="w-56">Progress</TableHead>
                        <TableHead class="w-36">Enrolled</TableHead>
                        <TableHead class="w-24 text-right">Actions</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="student in students" :key="student.id">
                        <TableCell>
                            <div class="flex items-center gap-3">
                                <Avatar class="size-8">
                                    <AvatarFallback class="bg-amber-500/15 text-xs font-bold text-amber-700">
                                        {{ initialsFor(student.name) }}
                                    </AvatarFallback>
                                </Avatar>
                                <span class="font-semibold text-foreground">{{ student.name }}</span>
                            </div>
                        </TableCell>
                        <TableCell>
                            <StatusBadge :status="student.status" />
                        </TableCell>
                        <TableCell>
                            <ProgressBar :value="student.progress_percentage" />
                        </TableCell>
                        <TableCell class="text-sm text-muted-foreground">{{ student.enrolled_at }}</TableCell>
                        <TableCell class="text-right">
                            <Button
                                v-if="student.status === 'Active'"
                                type="button"
                                variant="ghost"
                                size="sm"
                                class="text-rose-600 hover:bg-rose-50 hover:text-rose-700"
                                @click="remove(student)"
                            >
                                Remove
                            </Button>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
    </AuthenticatedLayout>
</template>
