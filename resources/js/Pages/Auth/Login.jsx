import InputError from '@/Components/InputError';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';

const appName = 'CRM';

const features = [
    {
        text: 'Keep every customer profile and conversation in one place',
        icon: 'M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z',
    },
    {
        text: 'Track leads from first contact to qualified',
        icon: 'M3 4.5h14.25M3 9h9.75M3 13.5h9.75m4.5-4.5v12m0 0-3.75-3.75M17.25 21 21 17.25',
    },
    {
        text: 'Follow every deal through your pipeline to close',
        icon: 'M2.25 18 9 11.25l4.306 4.306a11.95 11.95 0 0 1 5.814-5.518l2.74-1.22m0 0-5.94-2.281m5.94 2.28-2.28 5.941',
    },
];

const mailIcon =
    'M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75';
const lockIcon =
    'M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z';
const eyeIcon =
    'M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178ZM15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z';
const eyeOffIcon =
    'M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88';
const logoIcon =
    'M3.75 3v11.25A2.25 2.25 0 0 0 6 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0 1 18 16.5h-2.25m-7.5 0h7.5m-7.5 0-1 3m8.5-3 1 3m0 0 .5 1.5m-.5-1.5h-9.5m0 0-.5 1.5';

function Icon({ path, className = 'h-5 w-5' }) {
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

function LogoMark({ className = 'h-9 w-9' }) {
    return (
        <span
            className={`${className} flex items-center justify-center rounded-lg bg-white/15 text-white ring-1 ring-white/30`}
            aria-hidden="true"
        >
            <Icon path={logoIcon} className="h-1/2 w-1/2" />
        </span>
    );
}

export default function Login({ status, canResetPassword }) {
    const { flash } = usePage().props;
    const [showPassword, setShowPassword] = useState(false);

    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const submit = (e) => {
        e.preventDefault();

        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    const inputClass = (hasError) =>
        `block w-full rounded-lg border bg-white py-2.5 ps-10 pe-3 text-sm text-gray-900 placeholder-gray-400 shadow-xs focus:border-primary-600 focus:ring-2 focus:ring-primary-600/30 focus:outline-hidden ${
            hasError ? 'border-red-500' : 'border-gray-300'
        }`;

    return (
        <>
            <Head title="Sign in" />

            <div className="min-h-screen md:grid md:grid-cols-2">
                {/* Left panel (md and up) */}
                <aside className="relative hidden overflow-hidden bg-linear-to-br from-primary-950 via-primary-800 to-primary-500 text-white md:flex md:flex-col md:justify-between md:p-12">
                    <div
                        className="pointer-events-none absolute -right-24 -top-24 h-96 w-96 rounded-full bg-white/10 blur-3xl"
                        aria-hidden="true"
                    />
                    <div
                        className="pointer-events-none absolute -bottom-32 -left-20 h-96 w-96 rounded-full bg-primary-300/20 blur-3xl"
                        aria-hidden="true"
                    />

                    <div className="relative flex items-center gap-3">
                        <LogoMark />
                        <span className="text-lg font-semibold">{appName}</span>
                    </div>

                    <div className="relative max-w-md">
                        <h1 className="text-4xl font-bold leading-tight lg:text-5xl">
                            Every customer, lead and deal in one place.
                        </h1>
                        <p className="mt-4 text-base text-primary-100">
                            Keep your team aligned and close more business with
                            a single, clear view of your pipeline.
                        </p>

                        <ul className="mt-10 space-y-5">
                            {features.map((feature) => (
                                <li
                                    key={feature.text}
                                    className="flex items-center gap-4"
                                >
                                    <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white/15 ring-1 ring-white/20">
                                        <Icon path={feature.icon} />
                                    </span>
                                    <span className="text-sm text-white/90">
                                        {feature.text}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </div>

                    <p className="relative text-xs text-white/60">
                        &copy; 2026 {appName}. All rights reserved.
                    </p>
                </aside>

                {/* Right panel */}
                <main className="flex min-h-screen flex-col bg-white md:min-h-0">
                    {/* Compact brand header (mobile) */}
                    <header className="flex items-center gap-3 bg-linear-to-r from-primary-900 to-primary-600 px-6 py-4 text-white md:hidden">
                        <LogoMark className="h-8 w-8" />
                        <span className="text-base font-semibold">
                            {appName}
                        </span>
                    </header>

                    <div className="flex flex-1 items-center justify-center px-6 py-10">
                        <div className="w-full max-w-sm">
                            <h2 className="text-3xl font-bold text-gray-900">
                                Welcome back
                            </h2>
                            <p className="mt-2 text-sm text-gray-600">
                                Sign in to your {appName} workspace.
                            </p>

                            {(status || flash?.success) && (
                                <div
                                    role="status"
                                    className="mt-6 rounded-lg bg-green-50 px-4 py-3 text-sm font-medium text-green-700"
                                >
                                    {status || flash.success}
                                </div>
                            )}

                            {flash?.error && (
                                <div
                                    role="alert"
                                    className="mt-6 rounded-lg bg-red-50 px-4 py-3 text-sm font-medium text-red-700"
                                >
                                    {flash.error}
                                </div>
                            )}

                            <form
                                onSubmit={submit}
                                className="mt-8 space-y-5"
                                noValidate
                            >
                                <div>
                                    <label
                                        htmlFor="email"
                                        className="block text-sm font-medium text-gray-700"
                                    >
                                        Email
                                    </label>
                                    <div className="relative mt-1.5">
                                        <span className="pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3 text-gray-400">
                                            <Icon path={mailIcon} />
                                        </span>
                                        <input
                                            id="email"
                                            type="email"
                                            name="email"
                                            value={data.email}
                                            placeholder="you@company.com"
                                            autoComplete="email"
                                            autoFocus
                                            required
                                            aria-invalid={!!errors.email}
                                            aria-describedby={
                                                errors.email
                                                    ? 'email-error'
                                                    : undefined
                                            }
                                            className={inputClass(errors.email)}
                                            onChange={(e) =>
                                                setData('email', e.target.value)
                                            }
                                        />
                                    </div>
                                    <InputError
                                        id="email-error"
                                        role="alert"
                                        message={errors.email}
                                        className="mt-2"
                                    />
                                </div>

                                <div>
                                    <label
                                        htmlFor="password"
                                        className="block text-sm font-medium text-gray-700"
                                    >
                                        Password
                                    </label>
                                    <div className="relative mt-1.5">
                                        <span className="pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3 text-gray-400">
                                            <Icon path={lockIcon} />
                                        </span>
                                        <input
                                            id="password"
                                            type={
                                                showPassword
                                                    ? 'text'
                                                    : 'password'
                                            }
                                            name="password"
                                            value={data.password}
                                            autoComplete="current-password"
                                            required
                                            aria-invalid={!!errors.password}
                                            aria-describedby={
                                                errors.password
                                                    ? 'password-error'
                                                    : undefined
                                            }
                                            className={`${inputClass(errors.password)} pe-11`}
                                            onChange={(e) =>
                                                setData(
                                                    'password',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                        <button
                                            type="button"
                                            onClick={() =>
                                                setShowPassword((v) => !v)
                                            }
                                            aria-label={
                                                showPassword
                                                    ? 'Hide password'
                                                    : 'Show password'
                                            }
                                            aria-pressed={showPassword}
                                            className="absolute inset-y-0 end-0 flex items-center rounded-e-lg pe-3 ps-2 text-gray-500 hover:text-gray-700 focus-visible:text-primary-700 focus-visible:outline-2 focus-visible:outline-primary-600"
                                        >
                                            <Icon
                                                path={
                                                    showPassword
                                                        ? eyeOffIcon
                                                        : eyeIcon
                                                }
                                            />
                                        </button>
                                    </div>
                                    <InputError
                                        id="password-error"
                                        role="alert"
                                        message={errors.password}
                                        className="mt-2"
                                    />
                                </div>

                                <div className="flex items-center justify-between gap-4">
                                    <label className="flex items-center gap-2 text-sm text-gray-700">
                                        <input
                                            type="checkbox"
                                            name="remember"
                                            checked={data.remember}
                                            onChange={(e) =>
                                                setData(
                                                    'remember',
                                                    e.target.checked,
                                                )
                                            }
                                            className="h-4 w-4 rounded-sm border-gray-300 text-primary-600 focus:ring-primary-600"
                                        />
                                        Remember me
                                    </label>

                                    {canResetPassword && (
                                        <Link
                                            href={route('password.request')}
                                            className="rounded-sm text-sm font-medium text-primary-700 hover:text-primary-800 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600"
                                        >
                                            Forgot password?
                                        </Link>
                                    )}
                                </div>

                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="flex w-full items-center justify-center gap-2 rounded-xl bg-linear-to-r from-primary-600 to-primary-500 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:from-primary-700 hover:to-primary-600 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600 disabled:cursor-not-allowed disabled:opacity-60"
                                >
                                    {processing && (
                                        <svg
                                            className="h-4 w-4 animate-spin"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            aria-hidden="true"
                                        >
                                            <circle
                                                className="opacity-25"
                                                cx="12"
                                                cy="12"
                                                r="10"
                                                stroke="currentColor"
                                                strokeWidth="4"
                                            />
                                            <path
                                                className="opacity-75"
                                                fill="currentColor"
                                                d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4Z"
                                            />
                                        </svg>
                                    )}
                                    {processing ? 'Signing in…' : 'Sign in'}
                                </button>
                            </form>
                        </div>
                    </div>

                    <p className="px-6 pb-6 text-center text-xs text-gray-500 md:hidden">
                        &copy; 2026 {appName}. All rights reserved.
                    </p>
                </main>
            </div>
        </>
    );
}
