import { Button, Icon, icons, inputClass } from '@/Components/Ui';
import { formatDate } from '@/Utils/format';
import { Popover, PopoverButton, PopoverPanel } from '@headlessui/react';
import { router } from '@inertiajs/react';
import { useState } from 'react';

const pad = (n) => String(n).padStart(2, '0');
const ymd = (date) => `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;

/** Quick ranges, computed in the user's own calendar. */
function presets(now = new Date()) {
    const year = now.getFullYear();

    return [
        { label: 'This month', from: ymd(new Date(year, now.getMonth(), 1)), to: ymd(new Date(year, now.getMonth() + 1, 0)) },
        { label: 'Last 30 days', from: ymd(new Date(year, now.getMonth(), now.getDate() - 29)), to: ymd(now) },
        { label: 'This year', from: `${year}-01-01`, to: `${year}-12-31` },
        { label: 'Last year', from: `${year - 1}-01-01`, to: `${year - 1}-12-31` },
    ];
}

/**
 * The date range of the dashboard. It changes the URL (?from=&to=), which makes
 * the lead widgets follow it. The default is the whole current year.
 */
export default function DateRangePicker({ period }) {
    const [from, setFrom] = useState(period.from);
    const [to, setTo] = useState(period.to);
    const [error, setError] = useState(null);

    const visit = (nextFrom, nextTo, close) => {
        if (!nextFrom || !nextTo) return setError('Choose both dates.');
        if (nextFrom > nextTo) return setError('The end date must be on or after the start date.');

        setError(null);
        close();
        router.get(route('dashboard'), { from: nextFrom, to: nextTo }, { preserveScroll: true, preserveState: true });
    };

    const reset = (close) => {
        close();
        router.get(route('dashboard'), {}, { preserveScroll: true, preserveState: true });
    };

    return (
        <Popover className="relative">
            <PopoverButton
                onClick={() => {
                    setFrom(period.from);
                    setTo(period.to);
                    setError(null);
                }}
                className="inline-flex items-center gap-2 rounded-full bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-xs ring-1 ring-slate-200 hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-primary-600"
            >
                <Icon path={icons.calendar} className="h-4 w-4 text-sky-600" />
                <span className="sr-only">Date range: </span>
                {formatDate(period.from)} – {formatDate(period.to)}
                {!period.is_default && (
                    <span className="rounded-full bg-sky-50 px-2 py-0.5 text-[11px] font-semibold text-sky-700">Custom</span>
                )}
                <Icon path={icons.chevronDown} className="h-4 w-4 text-gray-400" />
            </PopoverButton>

            <PopoverPanel
                anchor="bottom end"
                className="z-40 w-80 rounded-2xl bg-white p-4 shadow-xl ring-1 ring-black/5 [--anchor-gap:8px]"
            >
                {({ close }) => (
                    <div>
                        <p className="text-sm font-semibold text-gray-900">Date range</p>
                        <p className="mt-0.5 text-xs text-gray-500">Applies to the lead widgets (by the date a lead was created).</p>

                        <div className="mt-3 flex flex-wrap gap-2">
                            {presets().map((preset) => (
                                <button
                                    key={preset.label}
                                    type="button"
                                    onClick={() => visit(preset.from, preset.to, close)}
                                    className="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-200 focus-visible:outline-2 focus-visible:outline-primary-600"
                                >
                                    {preset.label}
                                </button>
                            ))}
                        </div>

                        <div className="mt-4 grid grid-cols-2 gap-3">
                            <div>
                                <label htmlFor="range-from" className="mb-1 block text-xs font-medium text-gray-500">
                                    From
                                </label>
                                <input
                                    id="range-from"
                                    type="date"
                                    value={from}
                                    max={to || undefined}
                                    onChange={(e) => setFrom(e.target.value)}
                                    className={`${inputClass()} py-2`}
                                />
                            </div>
                            <div>
                                <label htmlFor="range-to" className="mb-1 block text-xs font-medium text-gray-500">
                                    To
                                </label>
                                <input
                                    id="range-to"
                                    type="date"
                                    value={to}
                                    min={from || undefined}
                                    onChange={(e) => setTo(e.target.value)}
                                    className={`${inputClass()} py-2`}
                                />
                            </div>
                        </div>

                        {error && (
                            <p role="alert" className="mt-2 text-sm text-red-600">
                                {error}
                            </p>
                        )}

                        <div className="mt-4 flex items-center justify-between gap-2">
                            {!period.is_default ? (
                                <button
                                    type="button"
                                    onClick={() => reset(close)}
                                    className="rounded-md text-sm font-medium text-primary-700 hover:underline focus-visible:outline-2 focus-visible:outline-primary-600"
                                >
                                    Reset to this year
                                </button>
                            ) : (
                                <span />
                            )}
                            <Button onClick={() => visit(from, to, close)}>Apply</Button>
                        </div>
                    </div>
                )}
            </PopoverPanel>
        </Popover>
    );
}
