import { Form, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import type { PropsWithChildren } from 'react';
import {
    AppNavigation,
    navigationItems,
} from '@/components/navigation/app-navigation';
import { Button } from '@/components/ui/button';
import { logout } from '@/routes';

type SidebarContentProps = {
    onNavigate?: () => void;
};

function SidebarContent({ onNavigate }: SidebarContentProps) {
    const { props, url } = usePage();

    return (
        <div className="flex h-full flex-col bg-brand-deep text-white">
            <div className="flex h-20 shrink-0 items-center gap-3 border-b border-white/10 px-5">
                <span
                    aria-hidden="true"
                    className="grid size-10 place-items-center rounded-control bg-brand-aqua font-semibold text-brand-deep"
                >
                    AS
                </span>
                <div>
                    <p className="text-lg font-semibold tracking-tight">
                        AfyaScope
                    </p>
                    <p className="text-xs font-medium tracking-wider text-white/70 uppercase">
                        HMS
                    </p>
                </div>
            </div>

            <div className="flex-1 overflow-y-auto px-3 py-6">
                <p className="px-4 pb-3 text-xs font-semibold tracking-wider text-white/60 uppercase">
                    Workspace
                </p>
                <AppNavigation
                    capabilities={props.auth.capabilities}
                    currentUrl={url}
                    onNavigate={onNavigate}
                />
            </div>
        </div>
    );
}

export default function AuthenticatedLayout({ children }: PropsWithChildren) {
    const { props, url } = usePage();
    const mobileNavigation = useRef<HTMLDialogElement>(null);
    const [mobileNavigationOpen, setMobileNavigationOpen] = useState(false);
    const currentSection = navigationItems(props.auth.capabilities, url).find(
        (item) => item.active,
    )?.label;

    useEffect(() => {
        mobileNavigation.current?.close();
    }, [url]);

    useEffect(() => {
        const desktopViewport = window.matchMedia('(min-width: 64rem)');

        function closeNavigationOnDesktop(event: MediaQueryListEvent) {
            if (event.matches) {
                mobileNavigation.current?.close();
            }
        }

        desktopViewport.addEventListener('change', closeNavigationOnDesktop);

        return () => {
            desktopViewport.removeEventListener(
                'change',
                closeNavigationOnDesktop,
            );
        };
    }, []);

    function openMobileNavigation() {
        mobileNavigation.current?.showModal();
        setMobileNavigationOpen(true);
    }

    function closeMobileNavigation() {
        mobileNavigation.current?.close();
    }

    return (
        <div className="min-h-screen bg-canvas">
            <a
                className="fixed top-3 left-3 z-50 -translate-y-20 rounded-control bg-brand-primary px-4 py-2 text-sm font-semibold text-white transition-transform focus:translate-y-0 focus:outline-2 focus:outline-offset-2 focus:outline-brand-aqua"
                href="#main-content"
            >
                Skip to content
            </a>

            <aside className="fixed inset-y-0 left-0 z-30 hidden w-64 lg:block">
                <SidebarContent />
            </aside>

            <dialog
                aria-label="Mobile navigation"
                className="fixed inset-y-0 left-0 m-0 h-dvh max-h-none w-80 max-w-[88vw] border-0 bg-transparent p-0 text-white backdrop:bg-text/50 open:flex lg:hidden"
                id="mobile-navigation"
                onClose={() => setMobileNavigationOpen(false)}
                ref={mobileNavigation}
            >
                <div className="relative h-full w-full">
                    <SidebarContent onNavigate={closeMobileNavigation} />
                    <button
                        autoFocus
                        className="absolute top-5 right-4 rounded-control border border-white/20 bg-white/10 px-3 py-2 text-sm font-semibold text-white transition-colors hover:bg-white/15 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-aqua"
                        onClick={closeMobileNavigation}
                        type="button"
                    >
                        Close
                    </button>
                </div>
            </dialog>

            <div className="min-h-screen lg:pl-64">
                <header className="sticky top-0 z-20 flex min-h-16 items-center gap-3 border-b border-border bg-surface px-4 shadow-xs sm:px-6 lg:px-8">
                    <div className="flex min-w-0 items-center gap-3">
                        <button
                            aria-controls="mobile-navigation"
                            aria-expanded={mobileNavigationOpen}
                            aria-label="Open navigation"
                            className="inline-flex h-10 items-center gap-2 rounded-control border border-border bg-surface px-3 text-sm font-semibold text-text transition-colors hover:bg-surface-subtle focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-primary lg:hidden"
                            onClick={openMobileNavigation}
                            type="button"
                        >
                            <span aria-hidden="true" className="grid w-4 gap-1">
                                <span className="h-0.5 rounded-full bg-current" />
                                <span className="h-0.5 rounded-full bg-current" />
                                <span className="h-0.5 rounded-full bg-current" />
                            </span>
                            Menu
                        </button>
                        <p className="truncate text-sm font-semibold text-text">
                            <span className="lg:hidden">AfyaScope</span>
                            <span className="hidden lg:inline">
                                {currentSection ?? 'AfyaScope'}
                            </span>
                        </p>
                    </div>

                    <div className="ml-auto flex min-w-0 items-center gap-3 sm:gap-4">
                        <div className="min-w-0 text-right">
                            <p className="max-w-28 truncate text-sm font-semibold text-text sm:max-w-48">
                                {props.auth.user?.name}
                            </p>
                            <p className="max-w-28 truncate text-xs text-text-secondary sm:max-w-48">
                                {props.auth.role?.displayName}
                            </p>
                        </div>
                        <Form {...logout.form()}>
                            {({ processing }) => (
                                <Button
                                    disabled={processing}
                                    size="small"
                                    type="submit"
                                    variant="secondary"
                                >
                                    {processing ? 'Signing out…' : 'Sign out'}
                                </Button>
                            )}
                        </Form>
                    </div>
                </header>

                <main
                    className="min-h-[calc(100vh-4rem)] px-4 py-6 sm:px-6 sm:py-8 lg:px-8"
                    id="main-content"
                >
                    {children}
                </main>
            </div>
        </div>
    );
}
