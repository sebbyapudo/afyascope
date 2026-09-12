import { Form } from '@inertiajs/react';
import { ActionLink, Button } from '@/components/ui/button';
import { FormField, formControlStyles } from '@/components/ui/form-field';
import { index } from '@/routes/service-catalog';
import type { ServiceCatalogItem, ServiceCategoryOption } from '@/types';
import type { RouteFormDefinition } from '@/wayfinder';

type ServiceCatalogItemFormProps = {
    categories: ServiceCategoryOption[];
    form: RouteFormDefinition<'post'>;
    service?: ServiceCatalogItem;
    submitLabel: string;
};

export function ServiceCatalogItemForm({
    categories,
    form,
    service,
    submitLabel,
}: ServiceCatalogItemFormProps) {
    return (
        <Form {...form}>
            {({ errors, processing }) => (
                <div className="grid gap-6">
                    <div className="grid gap-5 sm:grid-cols-2">
                        <FormField
                            error={errors.name}
                            id="name"
                            label="Service name"
                            required
                        >
                            <input
                                aria-describedby={
                                    errors.name ? 'name-error' : undefined
                                }
                                aria-invalid={Boolean(errors.name)}
                                className={formControlStyles}
                                defaultValue={service?.name}
                                id="name"
                                name="name"
                                required
                                type="text"
                            />
                        </FormField>

                        <FormField
                            error={errors.category}
                            hint={
                                service?.isReferenced
                                    ? 'Category is locked because this service has historical use.'
                                    : 'Choose whether this is billed for consultation or procedure.'
                            }
                            id="category"
                            label="Category"
                            required
                        >
                            {service?.isReferenced ? (
                                <input
                                    name="category"
                                    type="hidden"
                                    value={service.category.value}
                                />
                            ) : null}
                            <select
                                aria-describedby={
                                    errors.category
                                        ? 'category-error'
                                        : 'category-hint'
                                }
                                aria-invalid={Boolean(errors.category)}
                                className={formControlStyles}
                                defaultValue={
                                    service?.category.value ??
                                    categories[0]?.value
                                }
                                disabled={service?.isReferenced}
                                id="category"
                                name="category"
                                required
                            >
                                {categories.map((category) => (
                                    <option
                                        key={category.value}
                                        value={category.value}
                                    >
                                        {category.label}
                                    </option>
                                ))}
                            </select>
                        </FormField>

                        {!service ? (
                            <FormField
                                error={errors.unit_price}
                                hint="Enter the initial price in Kenya shillings, with up to two decimal places."
                                id="unit_price"
                                label="Initial price (KES)"
                                required
                            >
                                <input
                                    aria-describedby={
                                        errors.unit_price
                                            ? 'unit_price-error'
                                            : 'unit_price-hint'
                                    }
                                    aria-invalid={Boolean(errors.unit_price)}
                                    className={formControlStyles}
                                    id="unit_price"
                                    inputMode="decimal"
                                    min="0.01"
                                    name="unit_price"
                                    required
                                    step="0.01"
                                    type="number"
                                />
                            </FormField>
                        ) : null}
                    </div>

                    <div className="flex flex-wrap items-center justify-end gap-3 border-t border-border pt-6">
                        <ActionLink href={index()} variant="secondary">
                            Cancel
                        </ActionLink>
                        <Button disabled={processing} type="submit">
                            {processing ? 'Saving...' : submitLabel}
                        </Button>
                    </div>
                </div>
            )}
        </Form>
    );
}
