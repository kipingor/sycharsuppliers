import { Head, usePage, router } from "@inertiajs/react";
import AppLayout from "@/layouts/app-layout";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Pencil, Trash, PlusCircle } from "lucide-react";
import { useState } from "react";

interface Meter {
    id: number;
    meter_number: string;
    location: string;
    installation_date: string;
    status: boolean;
    resident: { name: string };
}

interface MetersPageProps {
    meters: { data: Meter[], links: any };
}

export default function IndexMeters() {
    const { meters } = usePage<MetersPageProps>().props;
    const [search, setSearch] = useState("");

    const handleDelete = (id: number) => {
        if (confirm("Are you sure you want to delete this meter?")) {
            router.delete(`/meters/${id}`);
        }
    };

    return (
        <AppLayout>
            <Head title="Meters" />

            <div className="max-w-5xl mx-auto bg-white p-6 rounded-lg shadow">
                <div className="flex justify-between items-center mb-4">
                    <h1 className="text-2xl font-bold">Meters</h1>
                    <div className="flex gap-2">
                        <Input
                            type="text"
                            placeholder="Search meters..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                        />
                        <Button variant="default" href="/meters/create" className="flex items-center gap-2">
                            <PlusCircle size={18} />
                            Add Meter
                        </Button>
                    </div>
                </div>

                <div className="overflow-x-auto">
                    <table className="w-full border-collapse border border-gray-200">
                        <thead>
                            <tr className="bg-gray-100">
                                <th className="p-2 border border-gray-200">Meter Number</th>
                                <th className="p-2 border border-gray-200">Resident</th>
                                <th className="p-2 border border-gray-200">Location</th>
                                <th className="p-2 border border-gray-200">Installation Date</th>
                                <th className="p-2 border border-gray-200">Status</th>
                                <th className="p-2 border border-gray-200">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {meters.data
                                .filter((meter) =>
                                    meter.meter_number.toLowerCase().includes(search.toLowerCase())
                                )
                                .map((meter) => (
                                    <tr key={meter.id} className="hover:bg-gray-50">
                                        <td className="p-2 border border-gray-200">{meter.meter_number}</td>
                                        <td className="p-2 border border-gray-200">{meter.resident.name}</td>
                                        <td className="p-2 border border-gray-200">{meter.location || "-"}</td>
                                        <td className="p-2 border border-gray-200">{meter.installation_date}</td>
                                        <td className={`p-2 border border-gray-200 font-semibold ${meter.status ? "text-green-600" : "text-red-600"}`}>
                                            {meter.status ? "Active" : "Inactive"}
                                        </td>
                                        <td className="p-2 border border-gray-200 flex gap-2">
                                            <Button size="sm" variant="outline" href={`/meters/${meter.id}/edit`}>
                                                <Pencil size={16} />
                                            </Button>
                                            <Button size="sm" variant="destructive" onClick={() => handleDelete(meter.id)}>
                                                <Trash size={16} />
                                            </Button>
                                        </td>
                                    </tr>
                                ))}
                        </tbody>
                    </table>
                </div>

                {/* Pagination */}
                <div className="mt-4 flex justify-between">
                    {meters.links.map((link: { url: string; label: string; active: boolean }, index: number) => (
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
