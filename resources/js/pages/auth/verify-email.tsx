import { Form, Head } from '@inertiajs/react';
import { AuthLayout } from '@/layouts/auth-layout';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

type VerifyEmailProps = {
    status?: string | null;
};

export default function VerifyEmail({ status }: VerifyEmailProps) {
    return (
        <AuthLayout
            description="Verify your staff email to keep account recovery secure."
            title="Verify your email"
        >
            <Head title="Verify email" />

            {status === 'verification-link-sent' ? (
                <p
                    className="rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
                    role="status"
                >
                    A new verification link has been sent to your email address.
                </p>
            ) : null}

            <div className="grid gap-3">
                <Form {...send.form()}>
                    {({ processing }) => (
                        <button
                            className="h-11 w-full rounded-xl bg-sky-900 px-4 text-sm font-semibold text-white transition hover:bg-sky-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-800 disabled:cursor-not-allowed disabled:opacity-60"
                            disabled={processing}
                            type="submit"
                        >
                            {processing
                                ? 'Sending link…'
                                : 'Resend verification email'}
                        </button>
                    )}
                </Form>

                <Form {...logout.form()}>
                    {({ processing }) => (
                        <button
                            className="h-11 w-full rounded-xl border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-800 transition hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-800 disabled:cursor-not-allowed disabled:opacity-60"
                            disabled={processing}
                            type="submit"
                        >
                            Sign out
                        </button>
                    )}
                </Form>
            </div>
        </AuthLayout>
    );
}
