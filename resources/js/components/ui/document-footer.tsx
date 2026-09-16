import { cn } from '@/lib/utils';

type DocumentFooterProps = {
    className?: string;
};

export function DocumentFooter({ className }: DocumentFooterProps) {
    return (
        <footer
            className={cn(
                'break-inside-avoid border-t border-border pt-4 text-center text-xs leading-5 text-text-muted',
                className,
            )}
        >
            Developed by stortechltd.com
        </footer>
    );
}
