import { Icon, icons } from '@/Components/Ui';
import { Link } from '@inertiajs/react';
import { Menu, MenuButton, MenuItem, MenuItems } from '@headlessui/react';

const item = (focus, tone = 'text-gray-700') =>
    `flex w-full items-center gap-2 rounded-lg px-3 py-2 text-start text-sm ${tone} ${focus ? 'bg-slate-100' : ''}`;

/**
 * The "..." menu of a lead. It lists only what the server says the user may
 * do (lead.can), and is fully keyboard operable (Enter/Space, arrows, Esc).
 */
export default function RowMenu({ lead, urls, onDelete }) {
    return (
        <Menu>
            <MenuButton
                aria-label={`Actions for ${lead.name}`}
                className="rounded-lg p-1.5 text-gray-500 hover:bg-slate-100 hover:text-gray-700 focus-visible:outline-2 focus-visible:outline-primary-600"
            >
                <Icon path={icons.ellipsis} />
            </MenuButton>

            <MenuItems
                anchor="bottom end"
                className="z-30 w-40 rounded-xl bg-white p-1 shadow-lg ring-1 ring-black/5 [--anchor-gap:4px] focus:outline-hidden"
            >
                <MenuItem>
                    {({ focus }) => (
                        <Link href={urls.show} preserveState preserveScroll className={item(focus)}>
                            <Icon path={icons.eye} className="h-4 w-4" />
                            View
                        </Link>
                    )}
                </MenuItem>

                {lead.can.update && (
                    <MenuItem>
                        {({ focus }) => (
                            <Link href={urls.edit} preserveState preserveScroll className={item(focus)}>
                                <Icon path={icons.pencil} className="h-4 w-4" />
                                Edit
                            </Link>
                        )}
                    </MenuItem>
                )}

                {lead.can.delete && (
                    <MenuItem>
                        {({ focus }) => (
                            <button type="button" onClick={onDelete} className={item(focus, 'text-red-700')}>
                                <Icon path={icons.trash} className="h-4 w-4" />
                                Delete
                            </button>
                        )}
                    </MenuItem>
                )}
            </MenuItems>
        </Menu>
    );
}
