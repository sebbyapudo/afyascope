import { Form, Head } from '@inertiajs/react';
import { AuthField } from '@/components/auth/auth-field';
import { AuthLayout } from '@/layouts/auth-layout';
import { update } from '@/routes/password';

type ResetPasswordProps = {
    email: string;
    token: string;
};

export default function ResetPassword({ email, token }: ResetPasswordProps) {
    return (
        <AuthLayout
            description="Choose a strong password for your AfyaScope staff account."
            title="Create your password"
        >
            <Head title="Create password" />

            <Form {...update.form()}>
                {({ errors, processing }) => (
                    <div className="grid gap-5">
                        <input name="token" type="hidden" value={token} />

                        <AuthField
                            autoComplete="email"
                            defaultValue={email}
                            error={errors.email}
                            id="email"
                            label="Email address"
                            name="email"
                            readOnly
                            required
                            type="email"
                        />

                        <AuthField
                            autoComplete="new-password"
                            autoFocus
                            error={errors.password}
                            id="password"
                            label="New password"
                            name="password"
                            required
                            type="password"
                        />

                        <AuthField
                            autoComplete="new-password"
                            id="password_confirmation"
                            label="Confirm new password"
                            name="password_confirmation"
                            required
                            type="password"
                        />

                        <button
                            className="h-11 rounded-xl bg-sky-900 px-4 text-sm font-semibold text-white transition hover:bg-sky-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-800 disabled:cursor-not-allowed disabled:opacity-60"
                            disabled={processing}
                            type="submit"
                        >
                            {processing ? 'Saving password…' : 'Save password'}
                        </button>
                    </div>
                )}
            </Form>
        </AuthLayout>
    );
}
