import type { PropsWithChildren, ReactNode } from 'react';

type AuthLayoutProps = PropsWithChildren<{
    description: ReactNode;
    title: string;
}>;

export function AuthLayout({ children, description, title }: AuthLayoutProps) {
    return (
        <main className="flex min-h-screen items-center justify-center bg-slate-50 px-4 py-10 text-slate-950">
            <section className="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                <div className="grid gap-6">
                    <header className="grid gap-3 text-center">
                        <div>
                            <p className="text-lg font-semibold tracking-tight text-sky-900">
                                AfyaScope
                            </p>
                            <p className="text-xs font-medium tracking-widest text-slate-500 uppercase">
                                HMS
                            </p>
                        </div>
                        <div className="grid gap-1.5">
                            <h1 className="text-2xl font-semibold tracking-tight">
                                {title}
                            </h1>
                            <p className="text-sm leading-6 text-slate-600">
                                {description}
                            </p>
                        </div>
                    </header>

                    {children}
                </div>
            </section>
        </main>
    );
}
