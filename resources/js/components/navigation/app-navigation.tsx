import { Link } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import { index as appointmentIndex } from '@/routes/appointments';
import { index as auditLogIndex } from '@/routes/audit-logs';
import { index as clearanceIndex } from '@/routes/billing/clearances';
import { index as consultationBillingIndex } from '@/routes/billing/consultations';
import { index as paymentIndex } from '@/routes/billing/payments';
import { index as checkInIndex } from '@/routes/check-ins';
import { index as clinicalConsultationIndex } from '@/routes/clinical/consultations';
import { index as patientIndex } from '@/routes/patients';
import { index as staffIndex } from '@/routes/staff';
import { index as visitIndex } from '@/routes/visits';
import type { Capabilities } from '@/types';

type AppNavigationProps = {
    capabilities: Capabilities;
    currentUrl: string;
    onNavigate?: () => void;
};

function urlPath(url: string): string {
    const path = url.split(/[?#]/, 1)[0].replace(/\/+$/, '');

    return path || '/';
}

function isCurrentPath(
    currentUrl: string,
    destinationUrl: string,
    includeChildren = false,
): boolean {
    const currentPath = urlPath(currentUrl);
    const destinationPath = urlPath(destinationUrl);

    return (
        currentPath === destinationPath ||
        (includeChildren && currentPath.startsWith(`${destinationPath}/`))
    );
}

export function navigationItems(
    capabilities: Capabilities,
    currentUrl: string,
) {
    return [
        {
            active: isCurrentPath(currentUrl, dashboard.url()),
            href: dashboard(),
            label: 'Dashboard',
            visible: capabilities.viewDashboard,
        },
        {
            active: isCurrentPath(currentUrl, patientIndex.url(), true),
            href: patientIndex(),
            label: 'Patients',
            visible: capabilities.viewPatients,
        },
        {
            active: isCurrentPath(currentUrl, appointmentIndex.url(), true),
            href: appointmentIndex(),
            label: 'Appointments',
            visible: capabilities.viewAppointments,
        },
        {
            active: isCurrentPath(currentUrl, visitIndex.url(), true),
            href: visitIndex(),
            label: 'Visits',
            visible: capabilities.viewVisits,
        },
        {
            active: isCurrentPath(currentUrl, checkInIndex.url(), true),
            href: checkInIndex(),
            label: 'Check-in',
            visible: capabilities.viewCheckIns,
        },
        {
            active: isCurrentPath(
                currentUrl,
                clinicalConsultationIndex.url(),
                true,
            ),
            href: clinicalConsultationIndex(),
            label: 'Clinical Consultations',
            visible: capabilities.viewConsultations,
        },
        {
            active: isCurrentPath(
                currentUrl,
                consultationBillingIndex.url(),
                true,
            ),
            href: consultationBillingIndex(),
            label: 'Consultation Billing',
            visible: capabilities.viewBilling,
        },
        {
            active: isCurrentPath(currentUrl, paymentIndex.url(), true),
            href: paymentIndex(),
            label: 'Payments',
            visible: capabilities.viewPayments,
        },
        {
            active: isCurrentPath(currentUrl, clearanceIndex.url(), true),
            href: clearanceIndex(),
            label: 'Financial Clearance',
            visible: capabilities.viewClearance,
        },
        {
            active: isCurrentPath(currentUrl, staffIndex.url(), true),
            href: staffIndex(),
            label: 'Staff Administration',
            visible: capabilities.viewUsers,
        },
        {
            active: isCurrentPath(currentUrl, auditLogIndex.url()),
            href: auditLogIndex(),
            label: 'Audit Log',
            visible: capabilities.viewAudit,
        },
    ].filter((item) => item.visible);
}

export function AppNavigation({
    capabilities,
    currentUrl,
    onNavigate,
}: AppNavigationProps) {
    const items = navigationItems(capabilities, currentUrl);

    return (
        <nav aria-label="Primary navigation">
            <ul className="grid gap-1">
                {items.map((item) => (
                    <li key={item.label}>
                        <Link
                            aria-current={item.active ? 'page' : undefined}
                            className={cn(
                                'relative flex min-h-11 items-center rounded-control px-4 py-2.5 text-sm font-medium text-white/80 transition-colors hover:bg-white/10 hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-aqua data-loading:opacity-60',
                                item.active &&
                                    'bg-white/10 font-semibold text-white',
                            )}
                            href={item.href}
                            onClick={onNavigate}
                        >
                            {item.active ? (
                                <span
                                    aria-hidden="true"
                                    className="absolute inset-y-2 left-0 w-1 rounded-r-full bg-brand-aqua"
                                />
                            ) : null}
                            <span>{item.label}</span>
                            {item.active ? (
                                <span className="sr-only">, current page</span>
                            ) : null}
                        </Link>
                    </li>
                ))}
            </ul>
        </nav>
    );
}
