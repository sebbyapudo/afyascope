import { Form, Head, Link } from '@inertiajs/react';
import { AuthField } from '@/components/auth/auth-field';
import { AuthLayout } from '@/layouts/auth-layout';
import { store } from '@/routes/login';
import { request as passwordRequest } from '@/routes/password';

type LoginProps = {
    canResetPassword: boolean;
    status?: string | null;
};

export default function Login({ canResetPassword, status }: LoginProps) {
    return (
        <AuthLayout
            description="Use your staff account to continue."
            title="Sign in"
        >
            <Head title="Sign in" />

            {status ? (
                <p
                    className="rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
                    role="status"
                >
                    {status}
                </p>
            ) : null}

            <Form {...store.form()}>
                {({ errors, processing }) => (
                    <div className="grid gap-5">
                        <AuthField
                            autoComplete="username"
                            autoFocus
                            error={errors.email}
                            id="email"
                            label="Email address"
                            name="email"
                            required
                            type="email"
                        />

                        <AuthField
                            autoComplete="current-password"
                            error={errors.password}
                            id="password"
                            label="Password"
                            name="password"
                            required
                            type="password"
                        />

                        <label className="flex items-center gap-2 text-sm text-slate-700">
                            <input
                                className="size-4 rounded border-slate-300 text-sky-800 focus:ring-sky-700"
                                name="remember"
                                type="checkbox"
                            />
                            Keep me signed in
                        </label>

                        <button
                            className="h-11 rounded-xl bg-sky-900 px-4 text-sm font-semibold text-white transition hover:bg-sky-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-800 disabled:cursor-not-allowed disabled:opacity-60"
                            disabled={processing}
                            type="submit"
                        >
                            {processing ? 'Signing in…' : 'Sign in'}
                        </button>

                        {canResetPassword ? (
                            <Link
                                className="text-center text-sm font-medium text-sky-800 underline-offset-4 hover:underline"
                                href={passwordRequest()}
                            >
                                Forgot your password?
                            </Link>
                        ) : null}
                    </div>
                )}
            </Form>
        </AuthLayout>
    );
}
