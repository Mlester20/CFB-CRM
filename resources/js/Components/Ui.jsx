import { Link } from '@inertiajs/react';

export const inputClass = (hasError = false) =>
    `block w-full rounded-lg border bg-white px-3 py-2.5 text-sm text-gray-900 placeholder-gray-400 shadow-xs focus:border-primary-600 focus:ring-2 focus:ring-primary-600/30 focus:outline-hidden disabled:bg-gray-50 disabled:text-gray-500 ${
        hasError ? 'border-red-500' : 'border-gray-300'
    }`;

const buttonBase =
    'inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-sm font-semibold transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600 disabled:cursor-not-allowed disabled:opacity-60';

const variants = {
    primary:
        'bg-linear-to-r from-primary-600 to-primary-500 text-white shadow-sm hover:from-primary-700 hover:to-primary-600',
    secondary: 'bg-white text-gray-700 ring-1 ring-gray-300 hover:bg-gray-50',
    danger: 'bg-white text-red-700 ring-1 ring-red-300 hover:bg-red-50',
};

export function Button({ variant = 'primary', className = '', ...props }) {
    return (
        <button
            type="button"
            className={`${buttonBase} ${variants[variant]} ${className}`}
            {...props}
        />
    );
}

export function ButtonLink({ variant = 'primary', className = '', ...props }) {
    return (
        <Link
            className={`${buttonBase} ${variants[variant]} ${className}`}
            {...props}
        />
    );
}

const tones = {
    gray: 'bg-gray-100 text-gray-700',
    blue: 'bg-primary-50 text-primary-700 ring-1 ring-primary-200',
    green: 'bg-green-50 text-green-700 ring-1 ring-green-200',
    red: 'bg-red-50 text-red-700 ring-1 ring-red-200',
    amber: 'bg-amber-50 text-amber-800 ring-1 ring-amber-200',
};

export function Badge({ tone = 'gray', children }) {
    return (
        <span
            className={`inline-flex items-center whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-medium ${tones[tone]}`}
        >
            {children}
        </span>
    );
}

export function Card({ className = '', children }) {
    return (
        <div
            className={`rounded-xl bg-white shadow-xs ring-1 ring-gray-200 ${className}`}
        >
            {children}
        </div>
    );
}

export function Field({ label, htmlFor, error, hint, children }) {
    return (
        <div>
            <label
                htmlFor={htmlFor}
                className="block text-sm font-medium text-gray-700"
            >
                {label}
            </label>
            <div className="mt-1.5">{children}</div>
            {hint && !error && (
                <p className="mt-1.5 text-xs text-gray-500">{hint}</p>
            )}
            {error && (
                <p
                    id={`${htmlFor}-error`}
                    role="alert"
                    className="mt-1.5 text-sm text-red-600"
                >
                    {error}
                </p>
            )}
        </div>
    );
}

const moduleLabels = { ai: 'AI', saims: 'SAIMS' };

export const moduleLabel = (module) =>
    moduleLabels[module] ?? module.charAt(0).toUpperCase() + module.slice(1);

export function Icon({ path, className = 'h-5 w-5' }) {
    return (
        <svg
            className={className}
            fill="none"
            viewBox="0 0 24 24"
            strokeWidth="1.5"
            stroke="currentColor"
            aria-hidden="true"
        >
            <path strokeLinecap="round" strokeLinejoin="round" d={path} />
        </svg>
    );
}

export const icons = {
    home: 'm2.25 12 8.954-8.955a1.126 1.126 0 0 1 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25',
    users: 'M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z',
    shield: 'M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z',
    menu: 'M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5',
    close: 'M6 18 18 6M6 6l12 12',
    lock: 'M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z',
    search: 'm21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z',
    plus: 'M12 4.5v15m7.5-7.5h-15',
    bell: 'M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0',
    calendar: 'M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5',
    sparkles: 'M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456Z',
    check: 'M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
    warning: 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z',
    building: 'M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Z',
    chevronDown: 'm19.5 8.25-7.5 7.5-7.5-7.5',
    logo: 'M3.75 3v11.25A2.25 2.25 0 0 0 6 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0 1 18 16.5h-2.25m-7.5 0h7.5m-7.5 0-1 3m8.5-3 1 3m0 0 .5 1.5m-.5-1.5h-9.5m0 0-.5 1.5',
};
