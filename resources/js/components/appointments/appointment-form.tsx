import { Form } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { FormField, formControlStyles } from '@/components/ui/form-field';
import type { RouteFormDefinition } from '@/wayfinder';

type AppointmentFormProps = {
    defaultScheduledAt?: string;
    form: RouteFormDefinition<'post'>;
    submitLabel: string;
};

function dateTimeLocalValue(value?: string): string {
    if (!value) {
        return '';
    }

    const date = new Date(value);
    const pad = (part: number) => String(part).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

export function AppointmentForm({
    defaultScheduledAt,
    form,
    submitLabel,
}: AppointmentFormProps) {
    return (
        <Form
            {...form}
            transform={(data) => ({
                ...data,
                scheduled_at:
                    typeof data.scheduled_at === 'string' &&
                    data.scheduled_at !== ''
                        ? new Date(data.scheduled_at).toISOString()
                        : data.scheduled_at,
            })}
        >
            {({ errors, processing }) => (
                <div className="grid gap-6">
                    <FormField
                        error={errors.scheduled_at}
                        hint="Choose a future local date and time."
                        id="scheduled-at"
                        label="Scheduled date and time"
                        required
                    >
                        <input
                            aria-describedby={
                                errors.scheduled_at
                                    ? 'scheduled-at-error'
                                    : 'scheduled-at-hint'
                            }
                            aria-invalid={Boolean(errors.scheduled_at)}
                            className={formControlStyles}
                            defaultValue={dateTimeLocalValue(
                                defaultScheduledAt,
                            )}
                            id="scheduled-at"
                            name="scheduled_at"
                            required
                            type="datetime-local"
                        />
                    </FormField>

                    <div>
                        <Button disabled={processing} type="submit">
                            {processing ? 'Saving…' : submitLabel}
                        </Button>
                    </div>
                </div>
            )}
        </Form>
    );
}
