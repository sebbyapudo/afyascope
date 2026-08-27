import type { InputHTMLAttributes } from 'react';

type AuthFieldProps = InputHTMLAttributes<HTMLInputElement> & {
    error?: string;
    label: string;
};

export function AuthField({ error, id, label, ...props }: AuthFieldProps) {
    return (
        <div className="grid gap-2">
            <label className="text-sm font-medium text-slate-800" htmlFor={id}>
                {label}
            </label>
            <input
                {...props}
                aria-describedby={error ? `${id}-error` : undefined}
                aria-invalid={Boolean(error)}
                className="h-11 rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-950 transition outline-none placeholder:text-slate-400 focus:border-sky-700 focus:ring-3 focus:ring-sky-700/15 disabled:cursor-not-allowed disabled:opacity-60"
                id={id}
            />
            {error ? (
                <p
                    className="text-sm text-red-700"
                    id={`${id}-error`}
                    role="alert"
                >
                    {error}
                </p>
            ) : null}
        </div>
    );
}
