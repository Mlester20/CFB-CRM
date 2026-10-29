import Card from '@/Components/Dashboard/Card';
import { Badge, Button, Icon, icons } from '@/Components/Ui';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';

const states = {
    connected: { tone: 'green', title: 'Connected' },
    unreachable: { tone: 'red', title: 'Unreachable' },
    token_rejected: { tone: 'red', title: 'Token rejected' },
    forbidden: { tone: 'amber', title: 'Not allowed' },
    rate_limited: { tone: 'amber', title: 'Rate limited' },
    not_configured: { tone: 'gray', title: 'Not configured' },
    disabled: { tone: 'gray', title: 'Turned off' },
    failed: { tone: 'amber', title: 'Unexpected answer' },
};

function Setting({ label, children }) {
    return (
        <div className="flex items-center justify-between gap-4 py-3 text-sm">
            <dt className="text-gray-500">{label}</dt>
            <dd className="font-medium text-gray-900">{children}</dd>
        </div>
    );
}

export default function Saims({ status, settings }) {
    const state = states[status.state] ?? { tone: 'gray', title: status.state };
    const checkedAt = new Date(status.checked_at).toLocaleTimeString('en-PH', { hour: 'numeric', minute: '2-digit', second: '2-digit' });

    return (
        <AuthenticatedLayout
            header={
                <div>
                    <h1 className="text-2xl font-bold tracking-tight text-gray-900">SAIMS connection</h1>
                    <p className="mt-1 text-sm text-gray-600">Whether the CRM can read from your SAIMS system.</p>
                </div>
            }
        >
            <Head title="SAIMS connection" />

            <div className="max-w-3xl space-y-6">
                <Card as="section" aria-labelledby="status-title">
                    <div className="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <h2 id="status-title" className="text-base font-bold text-gray-900">
                                Status
                            </h2>
                            <div className="mt-3 flex flex-wrap items-center gap-3">
                                <Badge tone={state.tone}>
                                    <span
                                        className={`me-1.5 h-2 w-2 rounded-full ${
                                            state.tone === 'green' ? 'bg-emerald-500' : state.tone === 'red' ? 'bg-rose-500' : state.tone === 'amber' ? 'bg-amber-500' : 'bg-slate-400'
                                        }`}
                                        aria-hidden="true"
                                    />
                                    {state.title}
                                </Badge>
                                {status.response_ms !== null && (
                                    <span className="text-sm text-gray-600">
                                        Responded in <span className="font-semibold text-gray-900">{status.response_ms} ms</span>
                                    </span>
                                )}
                            </div>
                            <p role="status" className="mt-3 text-sm text-gray-700">
                                {status.message}
                            </p>
                            <p className="mt-1 text-xs text-gray-500">Checked at {checkedAt}</p>
                        </div>

                        <Button variant="secondary" onClick={() => router.get(route('admin.saims'), { refresh: 1 }, { preserveScroll: true })}>
                            <Icon path={icons.refresh} className="h-4 w-4" />
                            Check again
                        </Button>
                    </div>
                </Card>

                <Card as="section" aria-labelledby="settings-title">
                    <h2 id="settings-title" className="text-base font-bold text-gray-900">
                        Settings
                    </h2>
                    <dl className="mt-2 divide-y divide-slate-100">
                        <Setting label="Server">{settings.server ?? <span className="text-gray-400">Not set</span>}</Setting>
                        <Setting label="Connection">{settings.enabled ? 'Turned on' : 'Turned off'}</Setting>
                        <Setting label="Access token">{settings.configured ? 'Set (hidden)' : <span className="text-gray-400">Not set</span>}</Setting>
                        <Setting label="Timeout">{settings.timeout} seconds</Setting>
                        <Setting label="Cache">{settings.cache_seconds} seconds</Setting>
                    </dl>
                    <p className="mt-3 rounded-xl bg-slate-50 px-4 py-3 text-xs text-gray-600">
                        The access token is kept on the server and is never shown here or sent to the browser. It is changed in the CRM's
                        environment settings (<code className="font-mono">SAIMS_TOKEN</code>).
                    </p>
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
