import { cn } from '@/lib/utils';
import type { HTMLAttributes } from 'react';

type PanelProps = HTMLAttributes<HTMLElement>;

export function Panel({ className, ...props }: PanelProps) {
    return (
        <section
            className={cn(
                'rounded-panel border border-border bg-surface shadow-panel',
                className,
            )}
            {...props}
        />
    );
}
