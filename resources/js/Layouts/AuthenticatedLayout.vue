<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { LayoutDashboard, Compass, GraduationCap, BookMarked, LogOut, ChevronDown, UserRound, Mail, UsersRound } from 'lucide-vue-next';
import { useSectionTheme, THEMES } from '@/composables/useSectionTheme';
import UserAvatar from '@/Components/UserAvatar.vue';
import NotificationBell from '@/Components/NotificationBell.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';

const page = usePage();
const user = computed(() => page.props.auth.user);
const canCreateCourses = computed(() => user.value.can?.create_courses ?? false);
const canManageUsers = computed(() => user.value.can?.manage_users ?? false);
const primaryRole = computed(() => user.value.role ?? user.value.roles?.[0] ?? 'Member');
const unreadMessagesCount = computed(() => user.value.unread_messages_count ?? 0);

const { section, theme } = useSectionTheme();

const navItems = computed(() => {
    const items = [
        { label: 'Dashboard', routeName: 'dashboard', icon: LayoutDashboard, key: 'home' },
        { label: 'Browse', routeName: 'catalog.index', icon: Compass, key: 'browse' },
        { label: 'My Courses', routeName: 'enrollments.index', icon: BookMarked, key: 'my' },
    ];

    if (canCreateCourses.value) {
        items.push({ label: 'Courses', routeName: 'courses.index', icon: GraduationCap, key: 'manage' });
    }

    if (canManageUsers.value) {
        items.push({ label: 'Users', routeName: 'users.index', icon: UsersRound, key: 'users' });
    }

    return items;
});
</script>

<template>
    <div class="min-h-screen bg-muted/30 text-foreground">
        <nav class="sticky top-0 z-40 border-b bg-card/85 backdrop-blur-md">
            <div class="mx-auto flex h-16 max-w-6xl items-center justify-between gap-4 px-4 sm:px-6">
                <!-- Brand + primary nav -->
                <div class="flex items-center gap-1.5 sm:gap-6">
                    <Link :href="route('dashboard')" class="group flex items-center gap-2.5">
                        <span
                            class="flex size-9 items-center justify-center rounded-xl bg-gradient-to-br from-violet-500 via-sky-500 to-emerald-500 text-sm font-bold text-white shadow-sm transition-transform group-hover:scale-105"
                        >
                            L
                        </span>
                        <span class="hidden font-display text-lg font-bold tracking-tight sm:block">LMS</span>
                    </Link>

                    <div class="flex items-center gap-1">
                        <Link
                            v-for="item in navItems"
                            :key="item.routeName"
                            :href="route(item.routeName)"
                            class="flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium transition-colors"
                            :class="
                                section === item.key
                                    ? THEMES[item.key].soft
                                    : 'text-muted-foreground hover:bg-muted hover:text-foreground'
                            "
                        >
                            <component :is="item.icon" class="size-4" :stroke-width="2.25" />
                            <span class="hidden sm:inline">{{ item.label }}</span>
                        </Link>
                    </div>
                </div>

                <!-- User menu -->
                <div class="flex items-center gap-3">
                    <NotificationBell />

                    <DropdownMenu>
                        <DropdownMenuTrigger
                            class="flex items-center gap-2 rounded-full py-1 pl-1 pr-2 outline-none transition-colors hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring"
                        >
                            <UserAvatar :user="user" size="md" />
                            <span class="hidden text-sm font-medium sm:block">{{ user.name }}</span>
                            <ChevronDown class="size-4 text-muted-foreground" />
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="w-56">
                            <DropdownMenuLabel class="flex flex-col gap-0.5">
                                <span class="font-semibold">{{ user.name }}</span>
                                <span class="text-xs font-normal capitalize text-muted-foreground">{{ primaryRole }}</span>
                            </DropdownMenuLabel>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem as-child>
                                <Link :href="route('users.show', user.id)" class="w-full cursor-pointer">
                                    <UserRound class="size-4" />
                                    Profile
                                </Link>
                            </DropdownMenuItem>
                            <DropdownMenuItem as-child>
                                <Link :href="route('conversations.index')" class="w-full cursor-pointer">
                                    <Mail class="size-4" />
                                    Messages
                                    <span
                                        v-if="unreadMessagesCount > 0"
                                        class="ml-auto rounded-full bg-red-500 px-1.5 text-xs text-white"
                                    >{{ unreadMessagesCount }}</span>
                                </Link>
                            </DropdownMenuItem>
                            <DropdownMenuItem as-child variant="destructive">
                                <Link :href="route('logout')" method="post" as="button" class="w-full cursor-pointer">
                                    <LogOut class="size-4" />
                                    Log out
                                </Link>
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </div>

            <!-- Section accent line -->
            <div class="h-0.5 w-full" :class="theme.accent" />
        </nav>

        <main class="mx-auto max-w-6xl px-4 py-8 sm:px-6">
            <slot />
        </main>

        <ConfirmDialog />
    </div>
</template>
