import { Head, usePage, router } from "@inertiajs/react";
import AppLayout from "@/layouts/app-layout";
import { type BreadcrumbItem, type Report } from "@/types";
import { useState } from "react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Download, FileText, Search } from "lucide-react";
import Table from "@/components/Table";

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: "Reports",
        href: "/reports",
    },
];

export default function Reports() {
    const { reports } = usePage<{ reports: { data: Report[]; links: any } }>().props;

    const [search, setSearch] = useState("");
    const [loading, setLoading] = useState(false);

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

                <Table
                    headers={["ID", "Report Name", "Generated Date", "Type", "Actions"]}
                    data={reports.data
                        .filter((r) =>
                            r.name.toLowerCase().includes(search.toLowerCase())
                        )
                        .map((report) => [
                            report.id,
                            report.name,
                            report.generated_at,
                            report.type.toUpperCase(),
                            <div key={report.id} className="flex gap-2">
                                <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={() => handleDownload(report.id, "pdf")}
                                    disabled={loading}
                                >
                                    <FileText size={16} />
                                    PDF
                                </Button>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={() => handleDownload(report.id, "excel")}
                                    disabled={loading}
                                >
                                    <Download size={16} />
                                    Excel
                                </Button>
                            </div>,
                        ])}
                />

                {/* Pagination */}
                <div className="mt-4 flex justify-between">
                    {reports.links.map((link: { url: string; label: string; active: boolean }, index: number) => (
                        <Button
                            key={index}
                            variant={link.active ? "default" : "outline"}
                            onClick={() => link.url && router.get(link.url)}
                            disabled={!link.url}
                        >
                            {link.label}
                        </Button>
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}
