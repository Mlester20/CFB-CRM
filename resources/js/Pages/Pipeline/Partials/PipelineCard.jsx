import { PriorityPill } from '@/Components/Leads/Pills';
import { Icon, icons } from '@/Components/Ui';
import { formatPhp } from '@/Utils/format';
import { Menu, MenuButton, MenuItem, MenuItems } from '@headlessui/react';
import { Link } from '@inertiajs/react';

const initials = (name) =>
    name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0].toUpperCase())
        .join('');

/**
 * The card itself, used in the column, in the drag overlay and (without the
 * handle and menu) for users who cannot move deals.
 * `sortable` is the useSortable result; it is absent in the overlay.
 */
export default function PipelineCard({ card, href, stages, onMove, canMove, sortable, overlay = false, dragging = false }) {
    const handleProps = sortable ? { ...sortable.attributes, ...sortable.listeners } : {};

    return (
        <article
            className={`rounded-xl bg-white p-3 shadow-xs ring-1 ring-slate-200 transition-shadow motion-reduce:transition-none ${
                overlay ? 'cursor-grabbing shadow-xl ring-primary-300 motion-safe:rotate-1' : 'hover:shadow-md'
            } ${dragging ? 'opacity-40' : ''}`}
        >
            <div className="flex items-start gap-2.5">
                <span
                    aria-hidden="true"
                    className="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary-50 text-xs font-bold text-primary-700"
                >
                    {initials(card.name)}
                </span>

                <div className="min-w-0 flex-1">
                    {overlay || !href ? (
                        <p className="truncate text-sm font-semibold text-gray-900">{card.name}</p>
                    ) : (
                        <Link
                            href={href}
                            only={['drawer']}
                            preserveState
                            preserveScroll
                            title={card.name}
                            className="block truncate rounded-sm text-sm font-semibold text-gray-900 hover:text-primary-700 focus-visible:outline-2 focus-visible:outline-primary-600"
                        >
                            {card.name}
                        </Link>
                    )}
                    <p className="mt-0.5 flex items-center gap-1 text-xs text-gray-500">
                        <Icon path={icons.building} className="h-3.5 w-3.5 shrink-0" />
                        <span className="truncate">{card.company}</span>
                    </p>
                </div>

                {canMove && !overlay && (
                    <div className="-me-1 -mt-1 flex shrink-0 items-center">
                        <Menu>
                            <MenuButton
                                aria-label={`Move ${card.name} to another column`}
                                className="rounded-md p-1 text-gray-400 hover:bg-slate-100 hover:text-gray-700 focus-visible:outline-2 focus-visible:outline-primary-600"
                            >
                                <Icon path={icons.ellipsis} className="h-4 w-4" />
                            </MenuButton>
                            <MenuItems
                                anchor="bottom end"
                                className="z-50 w-44 rounded-lg bg-white py-1 text-sm shadow-lg ring-1 ring-black/5 focus:outline-hidden [--anchor-gap:4px]"
                            >
                                <p className="px-3 py-1 text-xs font-semibold uppercase tracking-wide text-gray-400">Move to</p>
                                {stages
                                    .filter((stage) => stage.value !== card.stage)
                                    .map((stage) => (
                                        <MenuItem key={stage.value}>
                                            <button
                                                type="button"
                                                onClick={() => onMove(card.id, stage.value)}
                                                className="block w-full px-3 py-1.5 text-start text-gray-700 data-focus:bg-slate-100"
                                            >
                                                {stage.label}
                                            </button>
                                        </MenuItem>
                                    ))}
                            </MenuItems>
                        </Menu>

                        <button
                            type="button"
                            ref={sortable?.setActivatorNodeRef}
                            aria-label={`Drag ${card.name}`}
                            {...handleProps}
                            className="touch-none cursor-grab rounded-md p-1.5 text-gray-400 hover:bg-slate-100 hover:text-gray-700 focus-visible:outline-2 focus-visible:outline-primary-600 active:cursor-grabbing"
                        >
                            <Icon path={icons.grip} className="h-4 w-4" />
                        </button>
                    </div>
                )}
            </div>

            <div className="mt-2.5 flex items-center justify-between gap-2">
                <p className="truncate text-sm font-bold text-gray-900">{formatPhp(card.value)}</p>
                <PriorityPill priority={card.priority} />
            </div>
        </article>
    );
}
