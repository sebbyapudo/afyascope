import { Form, Head, Link, usePage } from '@inertiajs/react';
import { ActionLink, Button, textLinkStyles } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/empty-state';
import { formControlStyles } from '@/components/ui/form-field';
import { PageContainer } from '@/components/ui/page-container';
import { PageHeader } from '@/components/ui/page-header';
import { Panel } from '@/components/ui/panel';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { create, index, show } from '@/routes/patients';
import type { PatientPage } from '@/types';

type PatientIndexProps = {
    filters: { q: string };
    patients: PatientPage;
};

function formatDate(date: string | null): string {
    if (!date) {
        return 'Not recorded';
    }

    return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(
        new Date(`${date}T00:00:00`),
    );
}

export default function PatientIndex({ filters, patients }: PatientIndexProps) {
    const { props } = usePage();
    const { data, pagination } = patients;

    return (
        <>
            <Head title="Patients" />
            <PageContainer width="wide">
                <PageHeader
                    actions={
                        props.auth.capabilities.createPatients ? (
                            <ActionLink href={create()}>
                                Register Patient
                            </ActionLink>
                        ) : null
                    }
                    description="Find and review Patient administrative records."
                    title="Patient registry"
                />

                <Panel className="p-4 sm:p-5">
                    <Form action={index()}>
                        {({ processing }) => (
                            <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
                                <div className="min-w-0 flex-1">
                                    <label
                                        className="text-sm font-medium text-text"
                                        htmlFor="patient-search"
                                    >
                                        Search Patients
                                    </label>
                                    <input
                                        className={formControlStyles}
                                        defaultValue={filters.q}
                                        id="patient-search"
                                        maxLength={100}
                                        name="q"
                                        placeholder="Patient reference, name, or phone"
                                        type="search"
                                    />
                                </div>
                                <div className="flex gap-3">
                                    <Button disabled={processing} type="submit">
                                        {processing ? 'Searching…' : 'Search'}
                                    </Button>
                                    {filters.q ? (
                                        <ActionLink
                                            href={index()}
                                            variant="secondary"
                                        >
                                            Clear
                                        </ActionLink>
                                    ) : null}
                                </div>
                            </div>
                        )}
                    </Form>
                </Panel>

                <Panel className="overflow-hidden">
                    {data.length === 0 ? (
                        <EmptyState
                            action={
                                filters.q ? (
                                    <ActionLink
                                        href={index()}
                                        variant="secondary"
                                    >
                                        Clear search
                                    </ActionLink>
                                ) : props.auth.capabilities.createPatients ? (
                                    <ActionLink href={create()}>
                                        Register Patient
                                    </ActionLink>
                                ) : null
                            }
                            description={
                                filters.q
                                    ? `No Patient records match “${filters.q}”.`
                                    : 'Registered Patients will appear here.'
                            }
                            title={
                                filters.q
                                    ? 'No matching Patients'
                                    : 'No Patients registered'
                            }
                        />
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-4xl border-collapse text-left text-sm">
                                <thead className="bg-surface-subtle text-xs font-semibold tracking-wide text-text-secondary uppercase">
                                    <tr>
                                        <th className="px-5 py-4" scope="col">
                                            Patient
                                        </th>
                                        <th className="px-5 py-4" scope="col">
                                            Reference
                                        </th>
                                        <th className="px-5 py-4" scope="col">
                                            Date of birth
                                        </th>
                                        <th className="px-5 py-4" scope="col">
                                            Sex
                                        </th>
                                        <th className="px-5 py-4" scope="col">
                                            Phone
                                        </th>
                                        <th
                                            className="px-5 py-4 text-right"
                                            scope="col"
                                        >
                                            Action
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border">
                                    {data.map((patient) => (
                                        <tr
                                            className="transition-colors hover:bg-canvas"
                                            key={patient.id}
                                        >
                                            <td className="px-5 py-4 font-medium text-text">
                                                {patient.name}
                                            </td>
                                            <td className="px-5 py-4 font-medium text-brand-primary tabular-nums">
                                                {patient.patientNumber}
                                            </td>
                                            <td className="px-5 py-4 text-text-secondary">
                                                {formatDate(
                                                    patient.dateOfBirth,
                                                )}
                                            </td>
                                            <td className="px-5 py-4 text-text-secondary">
                                                {patient.sex?.label ??
                                                    'Not recorded'}
                                            </td>
                                            <td className="px-5 py-4 text-text-secondary">
                                                {patient.phone ??
                                                    'Not recorded'}
                                            </td>
                                            <td className="px-5 py-4 text-right">
                                                <Link
                                                    className={textLinkStyles}
                                                    href={show(patient.id)}
                                                >
                                                    View
                                                </Link>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}

                    {pagination.total > 0 ? (
                        <footer className="flex flex-wrap items-center justify-between gap-4 border-t border-border px-5 py-4 text-sm text-text-secondary">
                            <p className="tabular-nums">
                                Showing {pagination.from}–{pagination.to} of{' '}
                                {pagination.total}
                            </p>
                            <nav
                                aria-label="Patient registry pagination"
                                className="flex items-center gap-2"
                            >
                                {pagination.currentPage > 1 ? (
                                    <ActionLink
                                        href={index({
                                            query: {
                                                page:
                                                    pagination.currentPage - 1,
                                                q: filters.q || undefined,
                                            },
                                        })}
                                        size="small"
                                        variant="secondary"
                                    >
                                        Previous
                                    </ActionLink>
                                ) : null}
                                {pagination.currentPage <
                                pagination.lastPage ? (
                                    <ActionLink
                                        href={index({
                                            query: {
                                                page:
                                                    pagination.currentPage + 1,
                                                q: filters.q || undefined,
                                            },
                                        })}
                                        size="small"
                                        variant="secondary"
                                    >
                                        Next
                                    </ActionLink>
                                ) : null}
                            </nav>
                        </footer>
                    ) : null}
                </Panel>
            </PageContainer>
        </>
    );
}

PatientIndex.layout = [AuthenticatedLayout];
