import { cn } from '@/lib/utils';
import type { PropsWithChildren } from 'react';

type PageContainerProps = PropsWithChildren<{
    className?: string;
    width?: 'default' | 'narrow' | 'wide';
}>;

const widths = {
    default: 'max-w-6xl',
    narrow: 'max-w-4xl',
    wide: 'max-w-7xl',
};

export function PageContainer({
    children,
    className,
    width = 'default',
}: PageContainerProps) {
    return (
        <div
            className={cn(
                'mx-auto grid w-full gap-6',
                widths[width],
                className,
            )}
        >
            {children}
        </div>
    );
}
