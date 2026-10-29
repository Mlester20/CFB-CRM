import { inputClass } from '@/Components/Ui';

/** A labelled select used in the SAIMS filter bars. */
export function SelectFilter({ id, label, value, onChange, children }) {
    return (
        <div>
            <label htmlFor={id} className="mb-1 block text-xs font-medium text-gray-500">
                {label}
            </label>
            <select
                id={id}
                value={value ?? ''}
                onChange={(e) => onChange(e.target.value || null)}
                className={`${inputClass()} py-2 pe-8`}
            >
                {children}
            </select>
        </div>
    );
}

/** A labelled date input (YYYY-MM-DD). */
export function DateFilter({ id, label, value, onChange, min, max }) {
    return (
        <div>
            <label htmlFor={id} className="mb-1 block text-xs font-medium text-gray-500">
                {label}
            </label>
            <input
                id={id}
                type="date"
                value={value ?? ''}
                min={min}
                max={max}
                onChange={(e) => onChange(e.target.value || null)}
                className={`${inputClass()} py-2`}
            />
        </div>
    );
}
