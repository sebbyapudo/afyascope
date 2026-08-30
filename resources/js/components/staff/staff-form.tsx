import { Form } from '@inertiajs/react';
import { ActionLink, Button } from '@/components/ui/button';
import { FormField, formControlStyles } from '@/components/ui/form-field';
import { index } from '@/routes/staff';
import type { RoleOption, StaffUser } from '@/types';
import type { RouteFormDefinition } from '@/wayfinder';

type StaffFormProps = {
    form: RouteFormDefinition<'post'>;
    roles: RoleOption[];
    staffUser?: StaffUser;
    submitLabel: string;
};

export function StaffForm({
    form,
    roles,
    staffUser,
    submitLabel,
}: StaffFormProps) {
    return (
        <Form {...form}>
            {({ errors, processing }) => (
                <div className="grid gap-6">
                    <div className="grid gap-5 sm:grid-cols-2">
                        <FormField
                            error={errors.name}
                            id="name"
                            label="Full name"
                            required
                        >
                            <input
                                aria-describedby={
                                    errors.name ? 'name-error' : undefined
                                }
                                aria-invalid={Boolean(errors.name)}
                                autoComplete="name"
                                className={formControlStyles}
                                defaultValue={staffUser?.name}
                                id="name"
                                name="name"
                                required
                                type="text"
                            />
                        </FormField>

                        <FormField
                            error={errors.email}
                            id="email"
                            label="Email address"
                            required
                        >
                            <input
                                aria-describedby={
                                    errors.email ? 'email-error' : undefined
                                }
                                aria-invalid={Boolean(errors.email)}
                                autoComplete="email"
                                className={formControlStyles}
                                defaultValue={staffUser?.email}
                                id="email"
                                name="email"
                                required
                                type="email"
                            />
                        </FormField>

                        <FormField
                            error={errors.role}
                            id="role"
                            label="Staff role"
                            required
                        >
                            <select
                                aria-describedby={
                                    errors.role ? 'role-error' : undefined
                                }
                                aria-invalid={Boolean(errors.role)}
                                className={formControlStyles}
                                defaultValue={
                                    staffUser?.role.slug ?? roles[0]?.value
                                }
                                id="role"
                                name="role"
                                required
                            >
                                {roles.map((role) => (
                                    <option key={role.value} value={role.value}>
                                        {role.label}
                                    </option>
                                ))}
                            </select>
                        </FormField>

                        <FormField
                            error={errors.is_active}
                            id="is_active"
                            label="Account status"
                        >
                            <select
                                aria-describedby={
                                    errors.is_active
                                        ? 'is_active-error'
                                        : undefined
                                }
                                aria-invalid={Boolean(errors.is_active)}
                                className={formControlStyles}
                                defaultValue={
                                    staffUser?.isActive === false ? '0' : '1'
                                }
                                id="is_active"
                                name="is_active"
                            >
                                <option value="1">Active</option>
                                <option value="0">Disabled</option>
                            </select>
                        </FormField>
                    </div>

                    {!staffUser ? (
                        <p className="rounded-control border border-info-border bg-info-soft px-4 py-3 text-sm leading-6 text-info">
                            The staff member will receive a secure link to set
                            their password. Administrators do not set staff
                            passwords.
                        </p>
                    ) : null}

                    <div className="flex flex-wrap items-center justify-end gap-3 border-t border-border pt-6">
                        <ActionLink href={index()} variant="secondary">
                            Cancel
                        </ActionLink>
                        <Button disabled={processing} type="submit">
                            {processing ? 'Saving…' : submitLabel}
                        </Button>
                    </div>
                </div>
            )}
        </Form>
    );
}
