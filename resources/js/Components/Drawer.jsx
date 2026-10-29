import { Icon, icons } from '@/Components/Ui';
import { Dialog, DialogPanel, DialogTitle, Transition, TransitionChild } from '@headlessui/react';

/**
 * A panel that slides in from the right. Focus is trapped inside while it is
 * open, and Esc or a click on the backdrop closes it.
 */
export default function Drawer({ open, onClose, title, subtitle, footer, children, width = 'sm:max-w-lg' }) {
    return (
        <Transition show={open} appear>
            <Dialog className="relative z-50" onClose={onClose}>
                <TransitionChild
                    enter="transition-opacity ease-out duration-200"
                    enterFrom="opacity-0"
                    enterTo="opacity-100"
                    leave="transition-opacity ease-in duration-150"
                    leaveFrom="opacity-100"
                    leaveTo="opacity-0"
                >
                    <div className="fixed inset-0 bg-gray-900/40" aria-hidden="true" />
                </TransitionChild>

                <div className="fixed inset-0 overflow-hidden">
                    <div className="absolute inset-y-0 end-0 flex max-w-full">
                        <TransitionChild
                            enter="transform transition ease-out duration-300"
                            enterFrom="translate-x-full"
                            enterTo="translate-x-0"
                            leave="transform transition ease-in duration-200"
                            leaveFrom="translate-x-0"
                            leaveTo="translate-x-full"
                        >
                            <DialogPanel className={`flex h-full w-screen flex-col bg-white shadow-2xl ${width}`}>
                                <div className="flex items-start justify-between gap-4 border-b border-gray-100 px-6 py-5">
                                    <div className="min-w-0">
                                        <DialogTitle className="truncate text-lg font-bold text-gray-900">
                                            {title}
                                        </DialogTitle>
                                        {subtitle && <p className="mt-0.5 truncate text-sm text-gray-500">{subtitle}</p>}
                                    </div>
                                    <button
                                        type="button"
                                        onClick={onClose}
                                        aria-label="Close"
                                        className="rounded-lg p-1.5 text-gray-500 hover:bg-gray-100 hover:text-gray-700 focus-visible:outline-2 focus-visible:outline-primary-600"
                                    >
                                        <Icon path={icons.close} />
                                    </button>
                                </div>

                                <div className="flex-1 overflow-y-auto px-6 py-5">{children}</div>

                                {footer && (
                                    <div className="flex flex-wrap items-center gap-3 border-t border-gray-100 bg-gray-50/60 px-6 py-4">
                                        {footer}
                                    </div>
                                )}
                            </DialogPanel>
                        </TransitionChild>
                    </div>
                </div>
            </Dialog>
        </Transition>
    );
}
