import { Head, usePage } from '@inertiajs/react';
import {
    DashboardActionCard,
    DashboardMetricCard,
} from '@/components/dashboard/dashboard-cards';
import { EmptyState } from '@/components/ui/empty-state';
import { PageContainer } from '@/components/ui/page-container';
import { PageHeader } from '@/components/ui/page-header';
import { Panel } from '@/components/ui/panel';
import AuthenticatedLayout from '@/layouts/authenticated-layout';
import { index as appointmentIndex } from '@/routes/appointments';
import { index as auditLogIndex } from '@/routes/audit-logs';
import { index as clearanceIndex } from '@/routes/billing/clearances';
import { index as consultationBillingIndex } from '@/routes/billing/consultations';
import { index as paymentIndex } from '@/routes/billing/payments';
import { index as procedureBillingIndex } from '@/routes/billing/procedures';
import { index as checkInIndex } from '@/routes/check-ins';
import { index as clinicalConsultationIndex } from '@/routes/clinical/consultations';
import { index as clinicalProcedureIndex } from '@/routes/clinical/procedures';
import { index as recoveryEscalationIndex } from '@/routes/clinical/recovery-escalations';
import { index as procedurePreparationIndex } from '@/routes/nursing/pre-procedure-readiness';
import { index as recoveryIndex } from '@/routes/nursing/recovery';
import { index as patientActivityIndex } from '@/routes/patient-activity';
import {
    create as patientCreate,
    index as patientIndex,
} from '@/routes/patients';
import { index as clinicalReportIndex } from '@/routes/reports/clinical';
import { index as financialReportIndex } from '@/routes/reports/financial';
import { index as managementReportIndex } from '@/routes/reports/management';
import { index as operationalReportIndex } from '@/routes/reports/operational';
import { index as serviceCatalogIndex } from '@/routes/service-catalog';
import { index as staffIndex } from '@/routes/staff';
import { index as visitIndex } from '@/routes/visits';
import type { Capabilities, DashboardProjection } from '@/types';
import type { RouteDefinition } from '@/wayfinder';

type DashboardProps = {
    dashboard: DashboardProjection;
};

type DashboardAction = {
    description: string;
    href: RouteDefinition<'get'>;
    label: string;
};

function dashboardActions(
    dashboard: DashboardProjection,
    capabilities: Capabilities,
): DashboardAction[] {
    switch (dashboard.kind) {
        case 'receptionist':
            return [
                capabilities.viewPatients
                    ? {
                          description:
                              'Search and open registered Patient records.',
                          href: patientIndex(),
                          label: 'Patient Registry',
                      }
                    : null,
                capabilities.createPatients
                    ? {
                          description:
                              'Create a new Patient record after duplicate review.',
                          href: patientCreate(),
                          label: 'Register Patient',
                      }
                    : null,
                capabilities.viewVisits
                    ? {
                          description:
                              'Review Visit handoffs and return to Patient context.',
                          href: visitIndex(),
                          label: 'Visits',
                      }
                    : null,
                capabilities.viewAppointments
                    ? {
                          description:
                              'Manage scheduled attendance and Appointment outcomes.',
                          href: appointmentIndex(),
                          label: 'Appointments',
                      }
                    : null,
                capabilities.viewCheckIns
                    ? {
                          description: 'Check in consultation-cleared Visits.',
                          href: checkInIndex(),
                          label: 'Reception Check-in',
                      }
                    : null,
                capabilities.viewOperationalReports
                    ? {
                          description:
                              'Review aggregate Reception and Visit operations.',
                          href: operationalReportIndex(),
                          label: 'Operational Report',
                      }
                    : null,
            ].filter((action): action is DashboardAction => action !== null);

        case 'accountant':
            return [
                capabilities.viewBilling
                    ? {
                          description: 'Create eligible consultation Bills.',
                          href: consultationBillingIndex(),
                          label: 'Consultation Billing',
                      }
                    : null,
                capabilities.viewBilling
                    ? {
                          description:
                              'Create Bills from Doctor-authorized procedure handoffs.',
                          href: procedureBillingIndex(),
                          label: 'Procedure Billing',
                      }
                    : null,
                capabilities.viewPayments
                    ? {
                          description:
                              'Record full Payments and open issued Receipts.',
                          href: paymentIndex(),
                          label: 'Payments & Receipts',
                      }
                    : null,
                capabilities.viewClearance
                    ? {
                          description:
                              'Grant the separate financial-clearance handoff.',
                          href: clearanceIndex(),
                          label: 'Financial Clearance',
                      }
                    : null,
                capabilities.viewFinancialReports
                    ? {
                          description:
                              'Review aggregate immutable financial activity.',
                          href: financialReportIndex(),
                          label: 'Financial Report',
                      }
                    : null,
            ].filter((action): action is DashboardAction => action !== null);

        case 'doctor':
            return [
                capabilities.viewConsultations
                    ? {
                          description:
                              'Start or continue your active consultation work.',
                          href: clinicalConsultationIndex(),
                          label: 'Clinical Consultations',
                      }
                    : null,
                capabilities.viewProcedures
                    ? {
                          description:
                              'Continue prepared and in-progress procedures.',
                          href: clinicalProcedureIndex(),
                          label: 'Procedures',
                      }
                    : null,
                capabilities.reviewRecoveryEscalations
                    ? {
                          description:
                              'Resolve open Doctor-review recovery cases.',
                          href: recoveryEscalationIndex(),
                          label: 'Recovery Review',
                      }
                    : null,
                capabilities.viewClinicalReports
                    ? {
                          description:
                              'Review aggregate clinical and procedure milestones.',
                          href: clinicalReportIndex(),
                          label: 'Clinical / Procedure Report',
                      }
                    : null,
            ].filter((action): action is DashboardAction => action !== null);

        case 'nurse':
            return [
                capabilities.manageNursing
                    ? {
                          description:
                              'Continue procedure preparation and readiness records.',
                          href: procedurePreparationIndex(),
                          label: 'Procedure Preparation',
                      }
                    : null,
                capabilities.manageRecovery
                    ? {
                          description:
                              'Start or continue Nurse-owned Recovery Episodes.',
                          href: recoveryIndex(),
                          label: 'Recovery',
                      }
                    : null,
                capabilities.viewPatientActivity
                    ? {
                          description:
                              'Find your permitted Patient workflow activity.',
                          href: patientActivityIndex(),
                          label: 'Patient Tracking',
                      }
                    : null,
            ].filter((action): action is DashboardAction => action !== null);

        case 'administrator':
            return [
                capabilities.viewUsers
                    ? {
                          description:
                              'Provision, assign, disable, or reactivate staff access.',
                          href: staffIndex(),
                          label: 'Staff Administration',
                      }
                    : null,
                capabilities.manageServiceCatalog
                    ? {
                          description:
                              'Maintain active services and effective pricing.',
                          href: serviceCatalogIndex(),
                          label: 'Service Catalog',
                      }
                    : null,
                capabilities.viewAudit
                    ? {
                          description:
                              'Review the safe, event-aware audit projection.',
                          href: auditLogIndex(),
                          label: 'Audit Log',
                      }
                    : null,
                capabilities.viewOperationalReports
                    ? {
                          description: 'Review aggregate operational activity.',
                          href: operationalReportIndex(),
                          label: 'Operational Report',
                      }
                    : null,
                capabilities.viewFinancialReports
                    ? {
                          description: 'Review aggregate financial activity.',
                          href: financialReportIndex(),
                          label: 'Financial Report',
                      }
                    : null,
                capabilities.viewClinicalReports
                    ? {
                          description:
                              'Review aggregate clinical and procedure activity.',
                          href: clinicalReportIndex(),
                          label: 'Clinical / Procedure Report',
                      }
                    : null,
                capabilities.viewManagementReports
                    ? {
                          description:
                              'Open the authoritative cross-domain summary.',
                          href: managementReportIndex(),
                          label: 'Management Summary',
                      }
                    : null,
            ].filter((action): action is DashboardAction => action !== null);

        case 'management':
            return [
                capabilities.viewManagementReports
                    ? {
                          description:
                              'Open the authoritative cross-domain Management Summary.',
                          href: managementReportIndex(),
                          label: 'Management Summary',
                      }
                    : null,
                capabilities.viewOperationalReports
                    ? {
                          description:
                              'Review Visit workload and current lifecycle stages.',
                          href: operationalReportIndex(),
                          label: 'Operational Report',
                      }
                    : null,
                capabilities.viewFinancialReports
                    ? {
                          description:
                              'Review Bill, Payment, and clearance aggregates.',
                          href: financialReportIndex(),
                          label: 'Financial Report',
                      }
                    : null,
                capabilities.viewClinicalReports
                    ? {
                          description:
                              'Review aggregate clinical and procedure milestones.',
                          href: clinicalReportIndex(),
                          label: 'Clinical / Procedure Report',
                      }
                    : null,
                capabilities.viewAudit
                    ? {
                          description:
                              'Review the safe, event-aware Audit Log.',
                          href: auditLogIndex(),
                          label: 'Audit Log',
                      }
                    : null,
            ].filter((action): action is DashboardAction => action !== null);
    }
}

function formatPeriodDate(date: string): string {
    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeZone: 'UTC',
    }).format(new Date(`${date}T00:00:00Z`));
}

export default function Dashboard({ dashboard }: DashboardProps) {
    const { props } = usePage();
    const actions = dashboardActions(dashboard, props.auth.capabilities);
    const hasWork = dashboard.metrics.some((metric) => metric.value > 0);

    return (
        <>
            <Head title="Dashboard" />
            <PageContainer width="wide">
                <PageHeader
                    description={dashboard.description}
                    eyebrow={dashboard.eyebrow}
                    title={dashboard.title}
                />

                {dashboard.period ? (
                    <p
                        className="rounded-control border border-info-border bg-info-soft px-4 py-3 text-sm text-info"
                        role="status"
                    >
                        Current-month overview:{' '}
                        <span className="font-semibold">
                            {formatPeriodDate(dashboard.period.fromDate)}–
                            {formatPeriodDate(dashboard.period.throughDate)}
                        </span>
                    </p>
                ) : null}

                <Panel className="overflow-hidden">
                    <header className="border-b border-border px-5 py-4">
                        <h2 className="text-lg font-semibold text-text">
                            Current workload
                        </h2>
                        <p className="mt-1 text-sm text-text-secondary">
                            Counts are derived from the current authoritative
                            workflow records.
                        </p>
                    </header>
                    <div className="grid gap-3 p-4 sm:grid-cols-2 sm:p-5 xl:grid-cols-3">
                        {dashboard.metrics.map((metric) => (
                            <DashboardMetricCard
                                key={metric.label}
                                metric={metric}
                            />
                        ))}
                    </div>
                    {!hasWork ? (
                        <div className="border-t border-border">
                            <EmptyState
                                description={dashboard.emptyMessage}
                                title="Queues are clear"
                            />
                        </div>
                    ) : null}
                </Panel>

                <Panel className="overflow-hidden">
                    <header className="border-b border-border px-5 py-4">
                        <h2 className="text-lg font-semibold text-text">
                            Quick access
                        </h2>
                        <p className="mt-1 text-sm text-text-secondary">
                            Continue to the workflows and reports available to
                            your account.
                        </p>
                    </header>
                    {actions.length > 0 ? (
                        <div className="grid gap-3 p-4 sm:grid-cols-2 sm:p-5 xl:grid-cols-3">
                            {actions.map((action) => (
                                <DashboardActionCard
                                    description={action.description}
                                    href={action.href}
                                    key={action.label}
                                    label={action.label}
                                />
                            ))}
                        </div>
                    ) : (
                        <EmptyState
                            description="No additional destinations are available to this account."
                            title="No quick actions"
                        />
                    )}
                </Panel>
            </PageContainer>
        </>
    );
}

Dashboard.layout = [AuthenticatedLayout];
