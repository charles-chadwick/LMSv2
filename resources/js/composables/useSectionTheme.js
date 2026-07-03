import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * Per-section color themes. Every value is a complete, static Tailwind class
 * string so the v4 compiler keeps them — never build these names dynamically.
 *
 * @typedef {object} SectionTheme
 * @property {string} key
 * @property {string} label
 * @property {string} text        Foreground accent (on light surfaces)
 * @property {string} solid       Solid button/background with hover
 * @property {string} soft        Tinted pill/badge background + text
 * @property {string} ring        Focus ring color
 * @property {string} accent      Thin accent line / brand dot background
 * @property {string} gradient    From/to stops for hero + header bands
 * @property {string} icon        Tinted icon chip background + text
 * @property {string} hoverBorder Card hover border tint
 */

/** @type {Record<string, SectionTheme>} */
const THEMES = {
    home: {
        key: 'home',
        label: 'Home',
        text: 'text-violet-600',
        solid: 'bg-violet-600 text-white hover:bg-violet-700',
        soft: 'bg-violet-500/10 text-violet-700',
        ring: 'focus-visible:ring-violet-500',
        accent: 'bg-violet-500',
        gradient: 'from-violet-500 to-fuchsia-500',
        icon: 'bg-violet-500/15 text-violet-600',
        hoverBorder: 'hover:border-violet-300',
    },
    browse: {
        key: 'browse',
        label: 'Browse',
        text: 'text-emerald-600',
        solid: 'bg-emerald-600 text-white hover:bg-emerald-700',
        soft: 'bg-emerald-500/10 text-emerald-700',
        ring: 'focus-visible:ring-emerald-500',
        accent: 'bg-emerald-500',
        gradient: 'from-emerald-500 to-teal-500',
        icon: 'bg-emerald-500/15 text-emerald-600',
        hoverBorder: 'hover:border-emerald-300',
    },
    my: {
        key: 'my',
        label: 'My Courses',
        text: 'text-sky-600',
        solid: 'bg-sky-600 text-white hover:bg-sky-700',
        soft: 'bg-sky-500/10 text-sky-700',
        ring: 'focus-visible:ring-sky-500',
        accent: 'bg-sky-500',
        gradient: 'from-sky-500 to-indigo-500',
        icon: 'bg-sky-500/15 text-sky-600',
        hoverBorder: 'hover:border-sky-300',
    },
    manage: {
        key: 'manage',
        label: 'Courses',
        text: 'text-amber-600',
        solid: 'bg-amber-500 text-white hover:bg-amber-600',
        soft: 'bg-amber-500/10 text-amber-700',
        ring: 'focus-visible:ring-amber-500',
        accent: 'bg-amber-500',
        gradient: 'from-amber-500 to-orange-500',
        icon: 'bg-amber-500/15 text-amber-600',
        hoverBorder: 'hover:border-amber-300',
    },
};

/**
 * Resolve a section key from a live pathname. Matched against the reactive
 * Inertia page URL (Ziggy's route().current() is pinned to the boot URL in
 * this SPA and would not update on client-side visits).
 *
 * @param {string} pathname
 * @returns {string}
 */
function sectionForPath(pathname) {
    if (pathname.startsWith('/catalog')) {
        return 'browse';
    }
    if (pathname.startsWith('/my-courses') || pathname.startsWith('/learn')) {
        return 'my';
    }
    if (pathname.startsWith('/courses')) {
        return 'manage';
    }
    return 'home';
}

/**
 * Expose the active section key and its color theme, recomputed on navigation.
 *
 * @returns {{ section: import('vue').ComputedRef<string>, theme: import('vue').ComputedRef<SectionTheme> }}
 */
export function useSectionTheme() {
    const page = usePage();

    const section = computed(() => sectionForPath((page.url || '/').split('?')[0]));
    const theme = computed(() => THEMES[section.value]);

    return { section, theme };
}

export { THEMES };
