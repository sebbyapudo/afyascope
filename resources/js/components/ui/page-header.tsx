import type { ReactNode } from 'react';

type PageHeaderProps = {
    actions?: ReactNode;
    backLink?: ReactNode;
    description?: ReactNode;
    eyebrow?: ReactNode;
    title: ReactNode;
};

export function PageHeader({
    actions,
    backLink,
    description,
    eyebrow,
    title,
}: PageHeaderProps) {
    return (
        <header className="flex flex-wrap items-end justify-between gap-4">
            <div className="max-w-3xl">
                {backLink ? <div className="mb-3">{backLink}</div> : null}
                {eyebrow ? (
                    <p className="text-sm font-semibold text-brand-primary">
                        {eyebrow}
                    </p>
                ) : null}
                <h1 className="text-3xl font-semibold tracking-tight text-text">
                    {title}
                </h1>
                {description ? (
                    <p className="mt-2 text-sm leading-6 text-text-secondary">
                        {description}
                    </p>
                ) : null}
            </div>
            {actions ? <div className="shrink-0">{actions}</div> : null}
        </header>
    );
}
