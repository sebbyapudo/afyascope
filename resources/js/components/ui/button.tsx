import { Link } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import type { ComponentProps, ButtonHTMLAttributes } from 'react';

type ButtonVariant = 'danger' | 'primary' | 'secondary';
type ButtonSize = 'default' | 'small';

type ButtonStyleOptions = {
    className?: string;
    size?: ButtonSize;
    variant?: ButtonVariant;
};

const variants: Record<ButtonVariant, string> = {
    danger: 'bg-danger text-white hover:bg-danger/90',
    primary: 'bg-brand-primary text-white hover:bg-brand-deep',
    secondary:
        'border border-border bg-surface text-text hover:bg-surface-subtle',
};

const sizes: Record<ButtonSize, string> = {
    default: 'h-11 px-5',
    small: 'h-10 px-4',
};

export function buttonStyles({
    className,
    size = 'default',
    variant = 'primary',
}: ButtonStyleOptions = {}) {
    return cn(
        'inline-flex items-center justify-center rounded-control text-sm font-semibold transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-primary disabled:cursor-not-allowed disabled:opacity-60',
        variants[variant],
        sizes[size],
        className,
    );
}

type ButtonProps = ButtonHTMLAttributes<HTMLButtonElement> &
    Pick<ButtonStyleOptions, 'size' | 'variant'>;

export function Button({ className, size, variant, ...props }: ButtonProps) {
    return (
        <button
            className={buttonStyles({ className, size, variant })}
            {...props}
        />
    );
}

type ActionLinkProps = Omit<ComponentProps<typeof Link>, 'className' | 'size'> &
    ButtonStyleOptions;

export function ActionLink({
    className,
    size,
    variant,
    ...props
}: ActionLinkProps) {
    return (
        <Link
            className={buttonStyles({
                className,
                size,
                variant,
            })}
            {...props}
        />
    );
}

export const textLinkStyles =
    'rounded-sm font-semibold text-brand-primary underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-primary';
