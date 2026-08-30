import type { FormComponentRef } from '@inertiajs/core';
import { Form, Link, useHttp } from '@inertiajs/react';
import { useRef, useState } from 'react';
import { ActionLink, Button, textLinkStyles } from '@/components/ui/button';
import { FormField, formControlStyles } from '@/components/ui/form-field';
import { cn } from '@/lib/utils';
import { index, possibleDuplicates, show } from '@/routes/patients';
import type {
    PatientDetails,
    PatientFormData,
    PatientSexOption,
    PossiblePatientDuplicate,
} from '@/types';
import type { RouteFormDefinition } from '@/wayfinder';

type PatientFormProps = {
    form: RouteFormDefinition<'post'>;
    patient?: PatientDetails;
    sexOptions: PatientSexOption[];
    submitLabel: string;
};

type DuplicateCheckData = Pick<
    PatientFormData,
    'date_of_birth' | 'email' | 'first_name' | 'last_name' | 'phone'
>;

type DuplicateCheckResponse = {
    matches: PossiblePatientDuplicate[];
};

function initialPatientData(patient?: PatientDetails): PatientFormData {
    return {
        first_name: patient?.firstName ?? '',
        middle_name: patient?.middleName ?? '',
        last_name: patient?.lastName ?? '',
        date_of_birth: patient?.dateOfBirth ?? '',
        sex: patient?.sex?.value ?? '',
        phone: patient?.phone ?? '',
        email: patient?.email ?? '',
        address: patient?.address ?? '',
    };
}

export function PatientForm({
    form,
    patient,
    sexOptions,
    submitLabel,
}: PatientFormProps) {
    const formRef = useRef<FormComponentRef<PatientFormData>>(null);
    const [possibleMatches, setPossibleMatches] = useState<
        PossiblePatientDuplicate[] | null
    >(null);
    const duplicateCheck = useHttp<DuplicateCheckData, DuplicateCheckResponse>(
        possibleDuplicates(),
        {
            first_name: '',
            last_name: '',
            date_of_birth: '',
            phone: '',
            email: '',
        },
    );
    const initialData = initialPatientData(patient);

    async function checkForPossibleMatches() {
        duplicateCheck.transform(() => {
            const data = formRef.current?.getData() ?? initialData;

            return {
                first_name: data.first_name,
                last_name: data.last_name,
                date_of_birth: data.date_of_birth,
                phone: data.phone,
                email: data.email,
            };
        });

        const response = await duplicateCheck.submit();
        setPossibleMatches(response.matches);
    }

    function clearPossibleMatches() {
        setPossibleMatches(null);
        duplicateCheck.clearErrors();
    }

    return (
        <Form
            {...form}
            onInput={patient ? undefined : clearPossibleMatches}
            ref={formRef}
        >
            {({ errors, processing }) => (
                <div className="grid gap-6">
                    <div className="grid gap-5 sm:grid-cols-2">
                        <FormField
                            error={errors.first_name}
                            id="first_name"
                            label="First name"
                            required
                        >
                            <input
                                aria-describedby={
                                    errors.first_name
                                        ? 'first_name-error'
                                        : undefined
                                }
                                aria-invalid={Boolean(errors.first_name)}
                                autoComplete="given-name"
                                className={formControlStyles}
                                defaultValue={initialData.first_name}
                                id="first_name"
                                name="first_name"
                                required
                                type="text"
                            />
                        </FormField>

                        <FormField
                            error={errors.middle_name}
                            id="middle_name"
                            label="Middle name"
                        >
                            <input
                                aria-describedby={
                                    errors.middle_name
                                        ? 'middle_name-error'
                                        : undefined
                                }
                                aria-invalid={Boolean(errors.middle_name)}
                                autoComplete="additional-name"
                                className={formControlStyles}
                                defaultValue={initialData.middle_name}
                                id="middle_name"
                                name="middle_name"
                                type="text"
                            />
                        </FormField>

                        <FormField
                            error={errors.last_name}
                            id="last_name"
                            label="Last name"
                            required
                        >
                            <input
                                aria-describedby={
                                    errors.last_name
                                        ? 'last_name-error'
                                        : undefined
                                }
                                aria-invalid={Boolean(errors.last_name)}
                                autoComplete="family-name"
                                className={formControlStyles}
                                defaultValue={initialData.last_name}
                                id="last_name"
                                name="last_name"
                                required
                                type="text"
                            />
                        </FormField>

                        <FormField
                            error={errors.date_of_birth}
                            id="date_of_birth"
                            label="Date of birth"
                        >
                            <input
                                aria-describedby={
                                    errors.date_of_birth
                                        ? 'date_of_birth-error'
                                        : undefined
                                }
                                aria-invalid={Boolean(errors.date_of_birth)}
                                className={formControlStyles}
                                defaultValue={initialData.date_of_birth}
                                id="date_of_birth"
                                max={new Date().toISOString().slice(0, 10)}
                                name="date_of_birth"
                                type="date"
                            />
                        </FormField>

                        <FormField error={errors.sex} id="sex" label="Sex">
                            <select
                                aria-describedby={
                                    errors.sex ? 'sex-error' : undefined
                                }
                                aria-invalid={Boolean(errors.sex)}
                                className={formControlStyles}
                                defaultValue={initialData.sex}
                                id="sex"
                                name="sex"
                            >
                                <option value="">Not recorded</option>
                                {sexOptions.map((option) => (
                                    <option
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </option>
                                ))}
                            </select>
                        </FormField>

                        <FormField
                            error={errors.phone}
                            hint="Formatting spaces and punctuation are removed when saved."
                            id="phone"
                            label="Phone number"
                        >
                            <input
                                aria-describedby={
                                    errors.phone ? 'phone-error' : 'phone-hint'
                                }
                                aria-invalid={Boolean(errors.phone)}
                                autoComplete="tel"
                                className={formControlStyles}
                                defaultValue={initialData.phone}
                                id="phone"
                                name="phone"
                                type="tel"
                            />
                        </FormField>

                        <FormField
                            error={errors.email}
                            id="email"
                            label="Email address"
                        >
                            <input
                                aria-describedby={
                                    errors.email ? 'email-error' : undefined
                                }
                                aria-invalid={Boolean(errors.email)}
                                autoComplete="email"
                                className={formControlStyles}
                                defaultValue={initialData.email}
                                id="email"
                                name="email"
                                type="email"
                            />
                        </FormField>
                    </div>

                    <FormField
                        error={errors.address}
                        id="address"
                        label="Address"
                    >
                        <textarea
                            aria-describedby={
                                errors.address ? 'address-error' : undefined
                            }
                            aria-invalid={Boolean(errors.address)}
                            autoComplete="street-address"
                            className={cn(
                                formControlStyles,
                                'min-h-28 resize-y py-3',
                            )}
                            defaultValue={initialData.address}
                            id="address"
                            name="address"
                        />
                    </FormField>

                    {!patient ? (
                        <section
                            aria-labelledby="possible-matches-heading"
                            className="rounded-panel border border-info-border bg-info-soft p-4 sm:p-5"
                        >
                            <div className="flex flex-wrap items-start justify-between gap-4">
                                <div className="max-w-2xl">
                                    <h2
                                        className="font-semibold text-info"
                                        id="possible-matches-heading"
                                    >
                                        Check for an existing Patient
                                    </h2>
                                    <p className="mt-1 text-sm leading-6 text-info">
                                        We check exact phone, email, or first
                                        and last name with date of birth. This
                                        never blocks registration or merges
                                        records.
                                    </p>
                                </div>
                                <Button
                                    disabled={duplicateCheck.processing}
                                    onClick={checkForPossibleMatches}
                                    type="button"
                                    variant="secondary"
                                >
                                    {duplicateCheck.processing
                                        ? 'Checking…'
                                        : 'Check possible matches'}
                                </Button>
                            </div>

                            <div aria-live="polite" className="mt-4">
                                {duplicateCheck.hasErrors ? (
                                    <p
                                        className="text-sm text-danger"
                                        role="alert"
                                    >
                                        Enter valid Patient details before
                                        checking for matches.
                                    </p>
                                ) : possibleMatches?.length === 0 ? (
                                    <p className="text-sm text-info">
                                        No possible matches found. You can
                                        continue registration.
                                    </p>
                                ) : possibleMatches ? (
                                    <div className="grid gap-3">
                                        <p className="text-sm font-semibold text-warning">
                                            Possible existing Patients found.
                                            Review them, then continue only if
                                            this is a different person.
                                        </p>
                                        <ul className="grid gap-2">
                                            {possibleMatches.map((match) => (
                                                <li
                                                    className="rounded-control border border-warning-border bg-surface px-4 py-3 text-sm"
                                                    key={match.id}
                                                >
                                                    <div className="flex flex-wrap items-center justify-between gap-3">
                                                        <div>
                                                            <p className="font-semibold text-text">
                                                                {match.name}
                                                            </p>
                                                            <p className="mt-1 text-text-secondary">
                                                                {
                                                                    match.patientNumber
                                                                }
                                                                {match.dateOfBirth
                                                                    ? ` · Born ${match.dateOfBirth}`
                                                                    : ''}
                                                                {match.phone
                                                                    ? ` · ${match.phone}`
                                                                    : ''}
                                                            </p>
                                                        </div>
                                                        <Link
                                                            className={
                                                                textLinkStyles
                                                            }
                                                            href={show(
                                                                match.id,
                                                            )}
                                                        >
                                                            Open Patient
                                                        </Link>
                                                    </div>
                                                </li>
                                            ))}
                                        </ul>
                                    </div>
                                ) : null}
                            </div>
                        </section>
                    ) : null}

                    <div className="flex flex-wrap items-center justify-end gap-3 border-t border-border pt-6">
                        <ActionLink
                            href={patient ? show(patient.id) : index()}
                            variant="secondary"
                        >
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
