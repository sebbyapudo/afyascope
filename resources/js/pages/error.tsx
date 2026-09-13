import { Head } from '@inertiajs/react';
import { ActionLink } from '@/components/ui/button';
import { dashboard, login } from '@/routes';

type ErrorPageProps = {
    status: number;
};

const errorContent: Record<
    number,
    { action: string; description: string; title: string }
> = {
    403: {
        action: 'Return to dashboard',
        description:
            'Your account does not have permission to open this page or perform this action.',
        title: 'Access denied',
    },
    404: {
        action: 'Return to dashboard',
        description:
            'The requested record or page could not be found. It may no longer be available.',
        title: 'Page not found',
    },
    419: {
        action: 'Sign in again',
        description:
            'Your secure session has expired. Sign in again before repeating the action.',
        title: 'Session expired',
    },
    500: {
        action: 'Return to dashboard',
        description:
            'AfyaScope could not complete this request. No further action was recorded.',
        title: 'Request could not be completed',
    },
    503: {
        action: 'Return to dashboard',
        description:
            'AfyaScope is temporarily unavailable. Please try again shortly.',
        title: 'Service unavailable',
    },
};

export default function ErrorPage({ status }: ErrorPageProps) {
    const content = errorContent[status] ?? errorContent[500];

    return (
        <main className="grid min-h-screen place-items-center bg-canvas px-4 py-10 text-text">
            <Head title={content.title} />
            <section
                aria-labelledby="error-title"
                className="w-full max-w-lg rounded-panel border border-border bg-surface p-6 text-center shadow-panel sm:p-8"
            >
                <p className="text-sm font-semibold tracking-wider text-brand-primary uppercase">
                    AfyaScope · Error {status}
                </p>
                <h1
                    className="mt-3 text-2xl font-semibold tracking-tight"
                    id="error-title"
                >
                    {content.title}
                </h1>
                <p className="mt-3 text-sm leading-6 text-text-secondary">
                    {content.description}
                </p>
                <ActionLink
                    className="mt-6"
                    href={status === 419 ? login() : dashboard()}
                >
                    {content.action}
                </ActionLink>
            </section>
        </main>
    );
}
