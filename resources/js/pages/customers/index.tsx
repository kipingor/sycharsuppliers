import { Head, usePage, router } from "@inertiajs/react";
import AppLayout from "@/layouts/app-layout";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Pencil, Trash, PlusCircle, Search } from "lucide-react";
import { useState } from "react";

export default function IndexCustomer() {
    const { customers } = usePage<{ customers: { data: any[], links: any } }>().props;
    const [search, setSearch] = useState("");

    const handleDelete = (id: number) => {
        if (confirm("Are you sure you want to delete this customer?")) {
            router.delete(`/customers/${id}`);
        }
    };

    return (
        <AppLayout>
            <Head title="Customers" />

            <div className="max-w-5xl mx-auto bg-white p-6 rounded-lg shadow">
                <div className="flex justify-between items-center mb-4">
                    <h1 className="text-2xl font-bold">Customers</h1>
                    <div className="flex gap-2">
                        <Input
                            type="text"
                            placeholder="Search customers..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                        />
                        <Button variant="default" href="/customers/create" className="flex items-center gap-2">
                            <PlusCircle size={18} />
                            Add Customer
                        </Button>
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
                            {customers.data
                                .filter((customer) => customer.name.toLowerCase().includes(search.toLowerCase()))
                                .map((customer) => (
                                    <tr key={customer.id} className="hover:bg-gray-50">
                                        <td className="p-2 border border-gray-200">{customer.name}</td>
                                        <td className="p-2 border border-gray-200">{customer.email || "-"}</td>
                                        <td className="p-2 border border-gray-200">{customer.phone}</td>
                                        <td className="p-2 border border-gray-200">{customer.address || "-"}</td>
                                        <td className="p-2 border border-gray-200 flex gap-2">
                                            <Button size="sm" variant="outline" href={`/customers/${customer.id}/edit`}>
                                                <Pencil size={16} />
                                            </Button>
                                            <Button size="sm" variant="destructive" onClick={() => handleDelete(customer.id)}>
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
                    {customers.links.map((link: { url: string; label: string; active: boolean }, index: number) => (
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
