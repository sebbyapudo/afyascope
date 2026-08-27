import { Form, Head } from '@inertiajs/react';
import { AuthField } from '@/components/auth/auth-field';
import { AuthLayout } from '@/layouts/auth-layout';
import { store } from '@/routes/password/confirm';

export default function ConfirmPassword() {
    return (
        <AuthLayout
            description="Confirm your password before continuing to this protected action."
            title="Confirm password"
        >
            <Head title="Confirm password" />

            <Form {...store.form()}>
                {({ errors, processing }) => (
                    <div className="grid gap-5">
                        <AuthField
                            autoComplete="current-password"
                            autoFocus
                            error={errors.password}
                            id="password"
                            label="Password"
                            name="password"
                            required
                            type="password"
                        />

                        <button
                            className="h-11 rounded-xl bg-sky-900 px-4 text-sm font-semibold text-white transition hover:bg-sky-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-800 disabled:cursor-not-allowed disabled:opacity-60"
                            disabled={processing}
                            type="submit"
                        >
                            {processing ? 'Confirming…' : 'Confirm password'}
                        </button>
                    </div>
                )}
            </Form>
        </AuthLayout>
    );
}
