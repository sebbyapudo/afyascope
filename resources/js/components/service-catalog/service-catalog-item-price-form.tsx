import { Form } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { FormField, formControlStyles } from '@/components/ui/form-field';
import { Panel } from '@/components/ui/panel';
import { formatMinorAmount } from '@/lib/money';
import { update } from '@/routes/service-catalog/price';
import type { ServiceCatalogItem } from '@/types';

type ServiceCatalogItemPriceFormProps = {
    service: ServiceCatalogItem;
};

export function ServiceCatalogItemPriceForm({
    service,
}: ServiceCatalogItemPriceFormProps) {
    return (
        <Panel className="p-5 sm:p-6">
            <div className="max-w-2xl">
                <h2 className="text-lg font-semibold text-text">
                    Update current price
                </h2>
                <p className="mt-1 text-sm leading-6 text-text-secondary">
                    The saved price applies to future Bills only. Existing Bill
                    items, payments, receipts, and clearances keep their
                    original amounts.
                </p>
            </div>

            <Form {...update.form(service.id)}>
                {({ errors, processing }) => (
                    <div className="mt-6 grid gap-5 sm:grid-cols-[minmax(0,20rem)_auto] sm:items-end">
                        <input
                            name="current_unit_price_minor"
                            type="hidden"
                            value={service.unitPriceMinor}
                        />
                        <FormField
                            error={
                                errors.unit_price ??
                                errors.current_unit_price_minor
                            }
                            hint={`Current price: KES ${formatMinorAmount(service.unitPriceMinor)}`}
                            id="unit_price"
                            label="New price (KES)"
                            required
                        >
                            <input
                                aria-describedby={
                                    errors.unit_price ||
                                    errors.current_unit_price_minor
                                        ? 'unit_price-error'
                                        : 'unit_price-hint'
                                }
                                aria-invalid={Boolean(
                                    errors.unit_price ||
                                    errors.current_unit_price_minor,
                                )}
                                className={formControlStyles}
                                defaultValue={(
                                    service.unitPriceMinor / 100
                                ).toFixed(2)}
                                id="unit_price"
                                inputMode="decimal"
                                min="0.01"
                                name="unit_price"
                                required
                                step="0.01"
                                type="number"
                            />
                        </FormField>
                        <Button disabled={processing} type="submit">
                            {processing ? 'Saving...' : 'Save new price'}
                        </Button>
                    </div>
                )}
            </Form>
        </Panel>
    );
}
