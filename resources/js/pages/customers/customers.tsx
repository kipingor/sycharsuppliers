import { Head, usePage, router } from "@inertiajs/react";
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from "@/types";
import { useState } from "react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Pencil, Trash, PlusCircle } from "lucide-react";
import Table from "@/components/table";
import Modal from "@/components/ui/modal";

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: "Customers",
        href: "/customers",
    },
];

export default function Customers({customers}) {
    const { errors } = usePage().props;
    // const { customers } = usePage<{ customers: { data: Customer[]; links: any } }>().props;

    const [search, setSearch] = useState("");
    const [showModal, setShowModal] = useState(false);
    const [editCustomer, setEditCustomer] = useState(null);

    const handleDelete = (id: number) => {
        if (confirm("Are you sure you want to delete this customer?")) {
            router.delete(`/customers/${id}`);
        }
    };

    const handleSave = (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        const formData = new FormData(e.currentTarget);

        if (editCustomer) {
            router.put(`/customers/${editCustomer.id}`, formData);
        } else {
            router.post("/customers", formData);
        }

        setShowModal(false);
        setEditCustomer(null);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Customers" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="flex justify-between items-center mb-4">
                    <h1 className="text-2xl font-bold">Customers</h1>
                    <div className="flex gap-2">
                        <Input
                            type="text"
                            placeholder="Search customers..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                        />
                        <Button
                            onClick={() => {
                                setEditCustomer(null);
                                setShowModal(true);
                            }}
                            className="flex items-center gap-2"
                        >
                            <PlusCircle size={18} />
                            Add Customer
                        </Button>
                    </div>
                </div>

                <Table
                    headers={["ID", "Name", "Email", "Phone", "Actions"]}
                    data={customers.data
                        .filter((c) =>
                            c.name.toLowerCase().includes(search.toLowerCase())
                        )
                        .map((customer) => [
                            customer.id,
                            customer.name,
                            customer.email,
                            customer.phone,
                            <div key={customer.id} className="flex gap-2">
                                <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={() => {
                                        setEditCustomer(customer);
                                        setShowModal(true);
                                    }}
                                >
                                    <Pencil size={16} />
                                </Button>
                                <Button
                                    size="sm"
                                    variant="destructive"
                                    onClick={() => handleDelete(customer.id)}
                                >
                                    <Trash size={16} />
                                </Button>
                            </div>,
                        ])}
                />

                {/* Pagination */}
                <div className="mt-4 flex justify-center gap-2">
                    {customers.links.map((link: { url: string; label: string; active: boolean }, index: number) => (
                        <Button
                            key={index}
                            variant={link.active ? "default" : "outline"}
                            onClick={() => link.url && router.get(link.url)}
                            disabled={!link.url}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ))}
                </div>

                {/* Add/Edit Customer Modal */}
                {showModal && (
                    <Modal onClose={() => setShowModal(false)}>
                        <form onSubmit={handleSave} className="p-6 space-y-4">
                            <h2 className="text-xl font-bold">
                                {editCustomer ? "Edit Customer" : "Add Customer"}
                            </h2>
                            <Input
                                type="text"
                                name="name"
                                placeholder="Name"
                                defaultValue={editCustomer?.name || ""}
                                required
                            />
                            <Input
                                type="email"
                                name="email"
                                placeholder="Email"
                                defaultValue={editCustomer?.email || ""}
                                required
                            />
                            <Input
                                type="text"
                                name="phone"
                                placeholder="Phone"
                                defaultValue={editCustomer?.phone || ""}
                                required
                            />
                            <div className="flex justify-end gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setShowModal(false)}
                                >
                                    Cancel
                                </Button>
                                <Button type="submit">Save</Button>
                            </div>
                        </form>
                    </Modal>
                )}
            </div>
        </AppLayout>
    );
}
