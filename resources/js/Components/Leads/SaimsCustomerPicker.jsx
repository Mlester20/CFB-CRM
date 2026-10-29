import { Icon, icons, inputClass } from '@/Components/Ui';
import { Combobox, ComboboxInput, ComboboxOption, ComboboxOptions } from '@headlessui/react';
import { useEffect, useRef, useState } from 'react';

/**
 * Searchable picker for the SAIMS customer of a lead.
 *
 * It only ever asks OUR server (a fixed, throttled endpoint) and sends nothing
 * but the typed search. Results are capped at 10 by the server. Linking is
 * manual: nothing is matched by name automatically.
 *
 * value: { id, name } | null.  onChange(customer | null).
 */
export default function SaimsCustomerPicker({ id, value, onChange, error }) {
    const [items, setItems] = useState([]);
    const [state, setState] = useState({ loading: false, loaded: false, error: null });
    const timer = useRef(null);
    const controller = useRef(null);

    const search = async (text) => {
        controller.current?.abort();
        controller.current = new AbortController();

        setState((s) => ({ ...s, loading: true, error: null }));

        try {
            const response = await fetch(route('leads.saims-customers', text ? { search: text } : {}), {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                signal: controller.current.signal,
            });

            if (!response.ok) throw new Error(String(response.status));

            const json = await response.json();

            setItems(json.items);
            setState({ loading: false, loaded: true, error: json.error?.message ?? null });
        } catch (e) {
            if (e.name === 'AbortError') return;

            setItems([]);
            setState({ loading: false, loaded: true, error: 'The customer search is not available right now.' });
        }
    };

    useEffect(
        () => () => {
            clearTimeout(timer.current);
            controller.current?.abort();
        },
        [],
    );

    const onQuery = (text) => {
        clearTimeout(timer.current);
        timer.current = setTimeout(() => search(text.trim()), 300);
    };

    if (value) {
        return (
            <div
                id={id}
                className={`flex items-center justify-between gap-3 rounded-lg border bg-white px-3 py-2.5 text-sm ${
                    error ? 'border-red-500' : 'border-gray-300'
                }`}
            >
                <span className="flex min-w-0 items-center gap-2 text-gray-900">
                    <Icon path={icons.link} className="h-4 w-4 shrink-0 text-sky-600" />
                    <span className="truncate font-medium">{value.name}</span>
                    <span className="shrink-0 text-xs text-gray-500">SAIMS #{value.id}</span>
                </span>
                <button
                    type="button"
                    onClick={() => onChange(null)}
                    className="shrink-0 rounded-md px-2 py-1 text-sm font-medium text-red-700 hover:bg-red-50 focus-visible:outline-2 focus-visible:outline-red-600"
                >
                    Unlink
                </button>
            </div>
        );
    }

    return (
        <Combobox value={null} onChange={(customer) => customer && onChange({ id: customer.id, name: customer.name })} immediate>
            <div className="relative">
                <span className="pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3 text-gray-400">
                    <Icon path={icons.search} />
                </span>
                <ComboboxInput
                    id={id}
                    aria-label="Search SAIMS customers"
                    aria-invalid={!!error}
                    placeholder="Search SAIMS customers by name"
                    autoComplete="off"
                    displayValue={() => ''}
                    onChange={(e) => onQuery(e.target.value)}
                    onFocus={() => !state.loaded && !state.loading && search('')}
                    className={`${inputClass(error)} ps-10`}
                />
                <ComboboxOptions
                    anchor="bottom start"
                    className="z-[60] max-h-64 w-(--input-width) overflow-auto rounded-xl bg-white p-1 text-sm shadow-lg ring-1 ring-black/5 [--anchor-gap:4px] focus:outline-hidden"
                >
                    {state.loading && <p className="px-3 py-2 text-gray-500">Searching SAIMS…</p>}
                    {state.error && (
                        <p role="alert" className="px-3 py-2 text-amber-700">
                            {state.error}
                        </p>
                    )}
                    {!state.loading && !state.error && state.loaded && items.length === 0 && (
                        <p className="px-3 py-2 text-gray-500">No customers found.</p>
                    )}
                    {items.map((customer) => (
                        <ComboboxOption
                            key={customer.id}
                            value={customer}
                            className="cursor-pointer rounded-lg px-3 py-2 data-focus:bg-slate-100"
                        >
                            <p className="font-medium text-gray-900">{customer.name}</p>
                            <p className="text-xs text-gray-500">
                                {[customer.type, `SAIMS #${customer.id}`].filter(Boolean).join(' · ')}
                            </p>
                        </ComboboxOption>
                    ))}
                </ComboboxOptions>
            </div>
        </Combobox>
    );
}
