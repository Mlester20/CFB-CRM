import Modal from '@/Components/Modal';
import { Button, Field, Icon, icons, inputClass } from '@/Components/Ui';
import { useForm, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

function Spinner() {
    return (
        <svg className="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4Z" />
        </svg>
    );
}

/**
 * The form itself. It mounts fresh every time the modal opens, so nothing
 * typed before can leak into the next lead.
 *
 * "Assigned to" only appears when the server sent an owners list (the user has
 * leads.view-all). For everyone else the server decides the owner and ignores
 * any owner_id that is sent.
 */
function LeadFormBody({ lead, options, owners, submitUrl, onClose, registerClose }) {
    const { auth } = usePage().props;
    const editing = lead !== null;
    const canAssign = owners.length > 0;
    const [confirmDiscard, setConfirmDiscard] = useState(false);

    const { data, setData, post, put, processing, errors, isDirty } = useForm({
        name: lead?.name ?? '',
        company: lead?.company ?? '',
        email: lead?.email ?? '',
        phone: lead?.phone ?? '',
        value: lead?.value ?? 0,
        stage: lead?.stage ?? 'new',
        priority: lead?.priority ?? 'medium',
        source: lead?.source ?? 'website',
        notes: lead?.notes ?? '',
        ...(canAssign ? { owner_id: lead?.owner?.id ?? auth.user.id } : {}),
    });

    // Esc, a click on the backdrop and the X all ask first when there is typed text.
    const requestClose = () => {
        if (processing) return;

        if (isDirty) {
            setConfirmDiscard(true);

            return;
        }

        onClose();
    };

    useEffect(() => {
        registerClose(requestClose);

        return () => registerClose(null);
    });

    // Opens with Name focused. When Edit is opened from the detail drawer, that
    // drawer hands focus back as it closes, so check again once it has settled.
    useEffect(() => {
        const timer = setTimeout(() => {
            const name = document.getElementById('lead-name');

            if (name && !name.form?.contains(document.activeElement)) {
                name.focus({ preventScroll: true });
            }
        }, 400);

        return () => clearTimeout(timer);
    }, []);

    const submit = (e) => {
        e.preventDefault();
        (editing ? put : post)(submitUrl, { preserveScroll: true });
    };

    const field = (name) => ({
        id: `lead-${name}`,
        value: data[name] ?? '',
        onChange: (e) => setData(name, e.target.value),
        className: inputClass(errors[name]),
        'aria-invalid': !!errors[name],
        'aria-describedby': errors[name] ? `lead-${name}-error` : undefined,
    });

    const select = (name, choices) => (
        <select {...field(name)}>
            {choices.map((c) => (
                <option key={c.value} value={c.value}>
                    {c.label}
                </option>
            ))}
        </select>
    );

    return (
        <form onSubmit={submit} noValidate>
            <div className="flex items-start justify-between gap-4 px-6 pt-6">
                <div className="min-w-0">
                    <h2 id="lead-form-title" className="text-xl font-bold text-gray-900">
                        {editing ? 'Edit lead' : 'New lead'}
                    </h2>
                    <p className="mt-1 text-sm text-gray-500">
                        {editing ? 'Update the details of this lead.' : 'Add a lead to your pipeline.'}
                    </p>
                </div>
                <button
                    type="button"
                    onClick={requestClose}
                    aria-label="Close"
                    className="-me-2 -mt-1 rounded-lg p-1.5 text-gray-500 hover:bg-gray-100 hover:text-gray-700 focus-visible:outline-2 focus-visible:outline-primary-600"
                >
                    <Icon path={icons.close} />
                </button>
            </div>

            <div className="grid gap-x-4 gap-y-4 px-6 py-5 sm:grid-cols-2">
                <div className="sm:col-span-2">
                    <Field label="Name" htmlFor="lead-name" error={errors.name}>
                        <input
                            {...field('name')}
                            data-autofocus
                            required
                            autoComplete="off"
                            placeholder="Full name"
                        />
                    </Field>
                </div>

                <Field label="Company" htmlFor="lead-company" error={errors.company}>
                    <input {...field('company')} required autoComplete="off" placeholder="Company" />
                </Field>
                <Field label="Email" htmlFor="lead-email" error={errors.email}>
                    <input {...field('email')} type="email" autoComplete="off" placeholder="email@company.com" />
                </Field>

                <Field label="Phone" htmlFor="lead-phone" error={errors.phone}>
                    <input {...field('phone')} type="tel" autoComplete="off" placeholder="+63 900 000 0000" />
                </Field>
                <Field label="Deal value (Php)" htmlFor="lead-value" error={errors.value}>
                    <div className="relative">
                        <span
                            className="pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3 text-sm font-medium text-gray-500"
                            aria-hidden="true"
                        >
                            Php
                        </span>
                        <input
                            {...field('value')}
                            type="number"
                            inputMode="decimal"
                            min="0"
                            step="0.01"
                            placeholder="0"
                            className={`${inputClass(errors.value)} ps-11`}
                        />
                    </div>
                </Field>

                <Field label="Stage" htmlFor="lead-stage" error={errors.stage}>
                    {select('stage', options.stages)}
                </Field>
                <Field label="Priority" htmlFor="lead-priority" error={errors.priority}>
                    {select('priority', options.priorities)}
                </Field>

                <div className="sm:col-span-2">
                    <Field label="Source" htmlFor="lead-source" error={errors.source}>
                        {select('source', options.sources)}
                    </Field>
                </div>

                {canAssign && (
                    <div className="sm:col-span-2">
                        <Field label="Assigned to" htmlFor="lead-owner_id" error={errors.owner_id}>
                            <select {...field('owner_id')}>
                                {owners.map((owner) => (
                                    <option key={owner.id} value={owner.id}>
                                        {owner.id === auth.user.id ? `${owner.name} (you)` : owner.name}
                                    </option>
                                ))}
                            </select>
                        </Field>
                    </div>
                )}

                <div className="sm:col-span-2">
                    <Field label="Notes" htmlFor="lead-notes" error={errors.notes}>
                        <textarea {...field('notes')} rows={4} placeholder="Context, next steps..." />
                    </Field>
                </div>
            </div>

            <div className="border-t border-gray-100 bg-gray-50/70 px-6 py-4">
                {confirmDiscard ? (
                    <div role="alertdialog" aria-labelledby="discard-title" className="flex flex-wrap items-center gap-3">
                        <p id="discard-title" className="me-auto text-sm font-medium text-gray-800">
                            You have unsaved changes. Discard them?
                        </p>
                        <Button variant="secondary" onClick={() => setConfirmDiscard(false)} data-autofocus>
                            Keep editing
                        </Button>
                        <Button variant="destructive" onClick={onClose}>
                            Discard changes
                        </Button>
                    </div>
                ) : (
                    <div className="flex items-center justify-end gap-3">
                        <Button variant="secondary" onClick={requestClose}>
                            Cancel
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing && <Spinner />}
                            {processing ? 'Saving…' : editing ? 'Save changes' : 'Create lead'}
                        </Button>
                    </div>
                )}
            </div>
        </form>
    );
}

/**
 * The add / edit lead form as a centered modal, reused for both.
 * `lead` is null when creating.
 */
export default function LeadForm({ show, lead, options, owners, submitUrl, onClose }) {
    // The form tells us how to close it (it knows if there is typed text to protect).
    const closeRef = useRef(null);

    return (
        <Modal show={show} maxWidth="2xl" onClose={() => (closeRef.current ? closeRef.current() : onClose())}>
            <LeadFormBody
                key={lead?.id ?? 'new'}
                lead={lead}
                options={options}
                owners={owners}
                submitUrl={submitUrl}
                onClose={onClose}
                registerClose={(fn) => {
                    closeRef.current = fn;
                }}
            />
        </Modal>
    );
}
