import Pagination from '@/components/pagination';
import { Table, TableBody, TableCell, TableHead, TableRow } from '@/components/table';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import { useForm } from 'laravel-precognition-react';
import { debounce } from 'lodash';
import { useState } from 'react';

interface Report {
    id: number;
    resident?: {
        name: string;
    };
    report_type: string;
}

interface ReportProps {
    reports: {
        data: Report[];
        links: any[];
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Reports',
        href: '/reports',
    },
];

export default function Reports({ reports }: ReportProps) {
    const [search, setSearch] = useState('');
    const [loading, setLoading] = useState(false);

    const { data, setData, post, processing, errors } = useForm({
        field: 'value',
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        post(route('resource.store'));
    };

    const handleSearch = debounce((query: string) => {
        router.reload({
            only: ['meters'],
            data: { search: query },
            replace: true,
        });
    }, 300);

    const filteredReports = reports.data.filter((r: any) => r.resident?.name?.toLowerCase()?.includes(search.toLowerCase()));

    const handleDownload = async (id: number, format: 'pdf' | 'excel') => {
        setLoading(true);
        try {
            router.get(`/reports/download/${id}?format=${format}`, {}, { preserveScroll: true });
        } finally {
            setLoading(false);
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Reports" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="mb-4 flex items-center justify-between">
                    <h1 className="text-2xl font-bold">Reports</h1>
                    <div className="flex gap-2">
                        <Input type="text" placeholder="Search reports..." value={search} onChange={(e) => setSearch(e.target.value)} />
                    </div>
                </div>

                <Table>
                    <TableHead>
                        <TableRow>
                            {['ID', 'Report Name', 'Generated Date', 'stsus', 'Actions'].map((header) => (
                                <TableCell key={header} className="text-left font-medium">
                                    {header}
                                </TableCell>
                            ))}
                        </TableRow>
                    </TableHead>
                    <TableBody>
                        {filteredReports.map((report) => (
                            <TableRow key={report.id}>
                                <TableCell>{report.resident.name}</TableCell>
                                <TableCell>{report.generated_at}</TableCell>
                                <TableCell>{report.status}</TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>

                {/* Pagination */}
                <Pagination links={reports.links} />
            </div>
        </AppLayout>
    );
}
