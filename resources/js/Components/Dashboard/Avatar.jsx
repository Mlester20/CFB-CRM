import { initials } from '@/Utils/format';

// Static class names so Tailwind can see them.
const palette = [
    'bg-sky-100 text-sky-700',
    'bg-indigo-100 text-indigo-700',
    'bg-emerald-100 text-emerald-700',
    'bg-amber-100 text-amber-800',
    'bg-rose-100 text-rose-700',
    'bg-violet-100 text-violet-700',
];

const sizes = {
    sm: 'h-8 w-8 text-xs',
    md: 'h-10 w-10 text-sm',
};

/** Round avatar with the person's initials. Decorative: the name is next to it. */
export default function Avatar({ name, size = 'md', className = '' }) {
    const color = palette[[...name].reduce((sum, char) => sum + char.charCodeAt(0), 0) % palette.length];

    return (
        <span
            aria-hidden="true"
            className={`inline-flex shrink-0 items-center justify-center rounded-full font-semibold ${sizes[size]} ${color} ${className}`}
        >
            {initials(name)}
        </span>
    );
}
