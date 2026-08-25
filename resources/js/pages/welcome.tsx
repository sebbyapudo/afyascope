import { Head, Link, usePage } from '@inertiajs/react';
import { dashboard, login } from '@/routes';

export default function Welcome() {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="AfyaScope HMS" />

            <div className="flex min-h-screen items-center justify-center bg-background px-6">
                <div className="w-full max-w-lg space-y-8 text-center">
                    <div className="space-y-3">
                        <h1 className="text-3xl font-semibold tracking-tight">
                            AfyaScope HMS
                        </h1>

                        <p className="text-muted-foreground">
                            Endoscopy Center Management System
                        </p>
                    </div>

                    <div>
                        {auth.user ? (
                            <Link
                                href={dashboard()}
                                className="inline-flex h-10 items-center justify-center rounded-md bg-primary px-6 text-sm font-medium text-primary-foreground"
                            >
                                Go to Dashboard
                            </Link>
                        ) : (
                            <Link
                                href={login()}
                                className="inline-flex h-10 items-center justify-center rounded-md bg-primary px-6 text-sm font-medium text-primary-foreground"
                            >
                                Log in
                            </Link>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}