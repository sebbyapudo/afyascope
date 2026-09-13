import { ActionLink } from '@/components/ui/button';
import type { DashboardMetric } from '@/types';
import type { RouteDefinition } from '@/wayfinder';

type DashboardMetricCardProps = {
    metric: DashboardMetric;
};

export function DashboardMetricCard({ metric }: DashboardMetricCardProps) {
    return (
        <article className="rounded-control border border-border bg-surface-subtle p-4 sm:p-5">
            <p className="text-sm font-medium text-text-secondary">
                {metric.label}
            </p>
            <p className="mt-2 text-3xl font-semibold text-text tabular-nums">
                {metric.value.toLocaleString()}
            </p>
            <p className="mt-2 text-sm leading-6 text-text-secondary">
                {metric.description}
            </p>
        </article>
    );
}

type DashboardActionCardProps = {
    description: string;
    href: RouteDefinition<'get'>;
    label: string;
};

export function DashboardActionCard({
    description,
    href,
    label,
}: DashboardActionCardProps) {
    return (
        <article className="flex h-full flex-col items-start rounded-control border border-border bg-surface-subtle p-4">
            <h3 className="font-semibold text-text">{label}</h3>
            <p className="mt-1 flex-1 text-sm leading-6 text-text-secondary">
                {description}
            </p>
            <ActionLink className="mt-4" href={href} size="small">
                Open {label}
            </ActionLink>
        </article>
    );
}
