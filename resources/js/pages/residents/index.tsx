import { Head, usePage, router, Link } from "@inertiajs/react";
import AppLayout from "@/layouts/app-layout";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Pencil, Trash, PlusCircle, Search } from "lucide-react";
import { useState } from "react";

export default function IndexResident() {
    const { residents } = usePage<{ residents: { data: any[], links: any } }>().props;
    const [search, setSearch] = useState("");

    const handleDelete = (id: number) => {
        if (confirm("Are you sure you want to delete this resident?")) {
            router.delete(`/residents/${id}`);
        }
    };

    return (
        <AppLayout>
            <Head title="Residents" />

            <div className="max-w-5xl mx-auto bg-white p-6 rounded-lg shadow">
                <div className="flex justify-between items-center mb-4">
                    <h1 className="text-2xl font-bold">Residents</h1>
                    <div className="flex gap-2">
                        <Input
                            type="text"
                            placeholder="Search residents..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                        />
                        <Link href="/residents/create" className="inline-flex items-center justify-center rounded-md text-sm font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 bg-primary text-primary-foreground hover:bg-primary/90 h-10 px-4 py-2 flex items-center gap-2">
                            <PlusCircle size={18} />
                            Add Resident
                        </Link>
                    </div>
                </div>

                <div className="overflow-x-auto">
                    <table className="w-full border-collapse border border-gray-200">
                        <thead>
                            <tr className="bg-gray-100">
                                <th className="p-2 border border-gray-200">Name</th>
                                <th className="p-2 border border-gray-200">Email</th>
                                <th className="p-2 border border-gray-200">Phone</th>
                                <th className="p-2 border border-gray-200">Address</th>
                                <th className="p-2 border border-gray-200">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {residents.data
                                .filter((resident) => resident.name.toLowerCase().includes(search.toLowerCase()))
                                .map((resident) => (
                                    <tr key={resident.id} className="hover:bg-gray-50">
                                        <td className="p-2 border border-gray-200">{resident.name}</td>
                                        <td className="p-2 border border-gray-200">{resident.email || "-"}</td>
                                        <td className="p-2 border border-gray-200">{resident.phone}</td>
                                        <td className="p-2 border border-gray-200">{resident.address || "-"}</td>
                                        <td className="p-2 border border-gray-200 flex gap-2">
                                            <Button size="sm" variant="outline" href={`/residents/${resident.id}/edit`}>
                                                <Pencil size={16} />
                                            </Button>
                                            <Button size="sm" variant="destructive" onClick={() => handleDelete(resident.id)}>
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
                    {residents.links.map((link: { url: string; label: string; active: boolean }, index: number) => (
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
