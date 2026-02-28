import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';
import { type BreadcrumbItem } from '@/types';
import { Card, CardContent } from '@/components/ui/card';
import { BarChart3, FileText, ArrowRight } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Reports', href: '#' },
];

const REPORTS = [
    {
        title:       'Tax Report',
        description: 'Monthly revenue breakdown, cash collected, and outstanding receivables for income tax filing.',
        href:        'reports.tax',
        icon:        FileText,
        color:       'text-blue-600',
        bg:          'bg-blue-50 dark:bg-blue-950/30',
    },
    // Add more report types here as your app grows
];

export default function ReportsIndex() {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Reports" />
            <div className="flex h-full flex-1 flex-col gap-5 rounded-xl p-4">
                <div className="flex items-center gap-2">
                    <BarChart3 size={22} className="text-blue-600" />
                    <h1 className="text-2xl font-bold">Reports</h1>
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    {REPORTS.map(r => (
                        <Link key={r.title} href={route(r.href)}>
                            <Card className="hover:shadow-md transition-shadow cursor-pointer group h-full">
                                <CardContent className="pt-5 pb-5 flex flex-col gap-3 h-full">
                                    <div className={`w-10 h-10 rounded-lg ${r.bg} flex items-center justify-center`}>
                                        <r.icon size={20} className={r.color} />
                                    </div>
                                    <div className="flex-1">
                                        <p className="font-semibold">{r.title}</p>
                                        <p className="text-sm text-muted-foreground mt-1">{r.description}</p>
                                    </div>
                                    <div className={`flex items-center gap-1 text-sm font-medium ${r.color}`}>
                                        Open report
                                        <ArrowRight size={14} className="group-hover:translate-x-1 transition-transform" />
                                    </div>
                                </CardContent>
                            </Card>
                        </Link>
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}