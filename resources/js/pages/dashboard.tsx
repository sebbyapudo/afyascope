import { Head } from '@inertiajs/react';

export default function Dashboard() {
    return (
        <>
            <Head title="Dashboard" />
            <main className="min-h-screen bg-slate-50 p-6 sm:p-8">
                <section className="mx-auto max-w-5xl rounded-2xl border border-slate-200 bg-white p-6 sm:p-8">
                    <p className="text-sm font-medium text-slate-500">
                        AfyaScope
                    </p>
                    <h1 className="mt-2 text-3xl font-semibold text-slate-900">
                        Dashboard
                    </h1>
                    <p className="mt-3 text-sm text-slate-600">
                        You are signed in to the AfyaScope workspace.
                    </p>
                </section>
            </main>
        </>
    );
}
