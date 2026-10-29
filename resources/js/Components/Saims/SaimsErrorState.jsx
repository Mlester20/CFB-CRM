import { Button, Icon, icons } from '@/Components/Ui';
import { router } from '@inertiajs/react';

const titles = {
    disabled: 'SAIMS is not connected',
    not_configured: 'SAIMS is not connected',
    unreachable: "SAIMS can't be reached",
    token_rejected: 'SAIMS rejected the connection',
    forbidden: 'Not allowed to read this data',
    rate_limited: 'Too many requests',
    not_found: 'Not found in SAIMS',
};

/**
 * Shown instead of a table when SAIMS could not answer. The message comes
 * from our own server (a fixed friendly text), never from SAIMS itself.
 */
export default function SaimsErrorState({ error, compact = false }) {
    const notConnected = error.code === 'disabled' || error.code === 'not_configured';

    return (
        <div role="alert" className={`px-6 text-center ${compact ? 'py-8' : 'py-16'}`}>
            <span
                className={`mx-auto flex h-12 w-12 items-center justify-center rounded-2xl ${
                    notConnected ? 'bg-slate-100 text-slate-500' : 'bg-amber-50 text-amber-600'
                }`}
            >
                <Icon path={notConnected ? icons.link : icons.warning2} className="h-6 w-6" />
            </span>
            <h2 className="mt-4 text-base font-bold text-gray-900">{titles[error.code] ?? 'Something went wrong'}</h2>
            <p className="mx-auto mt-1 max-w-md text-sm text-gray-600">{error.message}</p>

            {!notConnected && (
                <Button variant="secondary" className="mt-5" onClick={() => router.reload()}>
                    <Icon path={icons.refresh} className="h-4 w-4" />
                    Try again
                </Button>
            )}
        </div>
    );
}
