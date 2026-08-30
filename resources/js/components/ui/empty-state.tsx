import type { ReactNode } from 'react';

type EmptyStateProps = {
    action?: ReactNode;
    description?: ReactNode;
    title: ReactNode;
};

export function EmptyState({ action, description, title }: EmptyStateProps) {
    return (
        <div className="grid justify-items-center gap-2 px-6 py-12 text-center">
            <p className="font-semibold text-text">{title}</p>
            {description ? (
                <p className="max-w-md text-sm leading-6 text-text-secondary">
                    {description}
                </p>
            ) : null}
            {action ? <div className="mt-3">{action}</div> : null}
        </div>
    );
}
