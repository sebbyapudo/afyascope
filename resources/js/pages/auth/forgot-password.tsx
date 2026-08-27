import { Form, Head, Link } from '@inertiajs/react';
import { AuthField } from '@/components/auth/auth-field';
import { AuthLayout } from '@/layouts/auth-layout';
import { login } from '@/routes';
import { email } from '@/routes/password';

type ForgotPasswordProps = {
    status?: string | null;
};

export default function ForgotPassword({ status }: ForgotPasswordProps) {
    return (
        <AuthLayout
            description="Enter your staff email and we will send a secure password setup link."
            title="Set up or reset password"
        >
            <Head title="Reset password" />

            {status ? (
                <p
                    className="rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
                    role="status"
                >
                    {status}
                </p>
            ) : null}

            <Form {...email.form()}>
                {({ errors, processing }) => (
                    <div className="grid gap-5">
                        <AuthField
                            autoComplete="email"
                            autoFocus
                            error={errors.email}
                            id="email"
                            label="Email address"
                            name="email"
                            required
                            type="email"
                        />

                        <button
                            className="h-11 rounded-xl bg-sky-900 px-4 text-sm font-semibold text-white transition hover:bg-sky-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-800 disabled:cursor-not-allowed disabled:opacity-60"
                            disabled={processing}
                            type="submit"
                        >
                            {processing ? 'Sending link…' : 'Send secure link'}
                        </button>

                        <Link
                            className="text-center text-sm font-medium text-sky-800 underline-offset-4 hover:underline"
                            href={login()}
                        >
                            Back to sign in
                        </Link>
                    </div>
                )}
            </Form>
        </AuthLayout>
    );
}
