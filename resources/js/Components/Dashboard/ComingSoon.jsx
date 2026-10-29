import { useId } from 'react';

const variants = {
    primary: 'bg-linear-to-r from-primary-600 to-sky-500 text-white',
    secondary: 'bg-white text-gray-700 ring-1 ring-gray-300',
    ghost: 'bg-sky-50 text-sky-800',
};

/**
 * A button for something that does not exist yet. It looks muted, is marked
 * aria-disabled, does nothing, and explains why in a tooltip on hover or focus.
 */
export default function ComingSoon({ children, tooltip, variant = 'primary', className = '' }) {
    const id = useId();

    return (
        <span className="group relative inline-flex">
            <button
                type="button"
                aria-disabled="true"
                aria-describedby={id}
                onClick={(event) => event.preventDefault()}
                className={`inline-flex cursor-not-allowed items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold opacity-55 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600 ${variants[variant]} ${className}`}
            >
                {children}
            </button>
            <span
                id={id}
                role="tooltip"
                className="pointer-events-none absolute -top-9 start-1/2 z-30 -translate-x-1/2 whitespace-nowrap rounded-md bg-gray-900 px-2.5 py-1 text-xs font-medium text-white opacity-0 shadow-lg transition-opacity group-focus-within:opacity-100 group-hover:opacity-100"
            >
                {tooltip}
            </span>
        </span>
    );
}
