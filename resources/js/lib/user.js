// Shared helpers for rendering a user's avatar consistently wherever their
// name appears. Keeps initials + fallback colour logic in one place.

const AVATAR_PALETTE = [
    'bg-violet-500/15 text-violet-700',
    'bg-sky-500/15 text-sky-700',
    'bg-emerald-500/15 text-emerald-700',
    'bg-amber-500/15 text-amber-700',
    'bg-rose-500/15 text-rose-700',
    'bg-indigo-500/15 text-indigo-700',
    'bg-teal-500/15 text-teal-700',
    'bg-fuchsia-500/15 text-fuchsia-700',
];

/**
 * Up to two uppercase initials derived from a display name.
 */
export function getInitials(name) {
    return (name || '?')
        .split(' ')
        .map((part) => part[0])
        .filter(Boolean)
        .slice(0, 2)
        .join('')
        .toUpperCase();
}

/**
 * Deterministic soft background + text colour for a user's initials fallback,
 * so the same person always renders the same colour.
 */
export function avatarColor(user) {
    const seed = String(user?.id ?? user?.name ?? '');
    let hash = 0;

    for (let index = 0; index < seed.length; index += 1) {
        hash = (hash * 31 + seed.charCodeAt(index)) | 0;
    }

    return AVATAR_PALETTE[Math.abs(hash) % AVATAR_PALETTE.length];
}
