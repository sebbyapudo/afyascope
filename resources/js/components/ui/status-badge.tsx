import { cn } from '@/lib/utils';
import type { PropsWithChildren } from 'react';

type StatusBadgeProps = PropsWithChildren<{
    className?: string;
    tone?: 'danger' | 'info' | 'neutral' | 'success' | 'warning';
}>;

const tones = {
    danger: 'border-danger-border bg-danger-soft text-danger',
    info: 'border-info-border bg-info-soft text-info',
    neutral: 'border-border bg-surface-subtle text-text-secondary',
    success: 'border-success-border bg-success-soft text-success',
    warning: 'border-warning-border bg-warning-soft text-warning',
};

export function StatusBadge({
    children,
    className,
    tone = 'neutral',
}: StatusBadgeProps) {
    return (
        <span
            className={cn(
                'inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-semibold',
                tones[tone],
                className,
            )}
        >
            {children}
        </span>
    );
}
