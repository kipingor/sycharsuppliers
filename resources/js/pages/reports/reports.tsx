import { Head, usePage, router } from "@inertiajs/react";
import AppLayout from "@/layouts/app-layout";
import { type BreadcrumbItem, type Report } from "@/types";
import { useState } from "react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Download, FileText, Search } from "lucide-react";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/table";
import { debounce } from "lodash";
import Pagination from "@/components/pagination";

interface Report {
    id: number;
    customer?: {
        name: string;
    };
    report_type: string;
}

interface ReportProps {
    reports: {
        data:Report[];
        links: any[];
    };
}


const breadcrumbs: BreadcrumbItem[] = [
    {
        title: "Reports",
        href: "/reports",
    },
];

export default function Reports({ reports }: ReportProps) {
    const { errors } = usePage().props;
    const [search, setSearch] = useState("");
    const [loading, setLoading] = useState(false);

    const handleSearch = debounce((query: string) => {
        router.reload({
            only: ['meters'],
            data: { search: query },
            replace: true,
        });
    }, 300);

    const filteredReports = reports.data.filter((r: any) =>
        r.customer?.name?.toLowerCase()?.includes(search.toLowerCase())
    );

    const handleDownload = async (id: number, format: "pdf" | "excel") => {
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
                <div className="flex justify-between items-center mb-4">
                    <h1 className="text-2xl font-bold">Reports</h1>
                    <div className="flex gap-2">
                        <Input
                            type="text"
                            placeholder="Search reports..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                        />
                    </div>
                </div>
                
                <Table>
                    <TableHead>
                        <TableRow>
                            {["ID", "Report Name", "Generated Date", "stsus", "Actions"].map((header) => (
                                <TableCell key={header} className="text-left font-medium">
                                    {header}
                                </TableCell>
                            ))}                            
                        </TableRow>
                    </TableHead>
                    <TableBody>
                        {filteredReports.map((report) => (
                            <TableRow key={report.id}>
                                <TableCell>{report.customer.name}</TableCell>
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
