import { cn } from '@/lib/utils';
import type { PropsWithChildren, ReactNode } from 'react';

type FormFieldProps = PropsWithChildren<{
    error?: string;
    hint?: ReactNode;
    id: string;
    label: ReactNode;
    required?: boolean;
}>;

export const formControlStyles =
    'mt-2 h-11 w-full rounded-control border border-border bg-surface px-3 text-sm text-text outline-none transition placeholder:text-text-secondary focus:border-brand-primary focus:ring-3 focus:ring-brand-primary/15 disabled:cursor-not-allowed disabled:bg-surface-subtle disabled:opacity-70 aria-invalid:border-danger aria-invalid:focus:border-danger aria-invalid:focus:ring-danger/15';

export function FormField({
    children,
    error,
    hint,
    id,
    label,
    required = false,
}: FormFieldProps) {
    return (
        <div>
            <label className="text-sm font-medium text-text" htmlFor={id}>
                {label}
                {required ? (
                    <>
                        <span aria-hidden="true" className="text-danger">
                            {' '}
                            *
                        </span>
                        <span className="sr-only"> (required)</span>
                    </>
                ) : null}
            </label>
            {children}
            {error ? (
                <p
                    className="mt-2 text-sm text-danger"
                    id={`${id}-error`}
                    role="alert"
                >
                    {error}
                </p>
            ) : hint ? (
                <p
                    className="mt-2 text-xs leading-5 text-text-secondary"
                    id={`${id}-hint`}
                >
                    {hint}
                </p>
            ) : null}
        </div>
    );
}
