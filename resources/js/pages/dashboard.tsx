import { Head } from '@inertiajs/react';
import { PageContainer } from '@/components/ui/page-container';
import { PageHeader } from '@/components/ui/page-header';
import { Panel } from '@/components/ui/panel';
import AuthenticatedLayout from '@/layouts/authenticated-layout';

export default function Dashboard() {
    return (
        <>
            <Head title="Dashboard" />
            <PageContainer>
                <PageHeader
                    description="Your secure starting point for AfyaScope operations."
                    eyebrow="AfyaScope"
                    title="Dashboard"
                />
                <Panel className="p-6 sm:p-8">
                    <h2 className="text-lg font-semibold text-text">
                        Welcome back
                    </h2>
                    <p className="mt-2 text-sm leading-6 text-text-secondary">
                        You are signed in to the AfyaScope workspace.
                    </p>
                </Panel>
            </PageContainer>
        </>
    );
}

Dashboard.layout = [AuthenticatedLayout];
