import { Head, usePage, router } from "@inertiajs/react";
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from "@/types";
import { useState } from "react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Pencil, Trash, PlusCircle, EllipsisVertical } from "lucide-react";
import { DropdownMenuItem, DropdownMenuContent, DropdownMenuTrigger, DropdownMenu } from "@/components/ui/dropdown-menu";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/table";
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
    const filteredCustomers = customers.data.filter((c) =>
        c.name.toLowerCase().includes(search.toLowerCase())
    );

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

                <Table>
                    <TableHead>
                        <TableRow>
                        {['ID', 'Name', 'Email', 'Phone', 'Actions'].map((header) => (
                            <TableCell key={header} as="th" className="text-left font-medium">
                            {header}
                            </TableCell>
                        ))}
                        </TableRow>
                    </TableHead>
                    <TableBody>
                        {filteredCustomers.map((customer) => (
                        <TableRow key={customer.id}>
                            <TableCell>{customer.id}</TableCell>
                            <TableCell>{customer.name}</TableCell>
                            <TableCell>{customer.email}</TableCell>
                            <TableCell>{customer.phone}</TableCell>                            
                            <TableCell>
                            <DropdownMenu>
                                <DropdownMenuTrigger>
                                <EllipsisVertical size={16} />
                                </DropdownMenuTrigger>
                                <DropdownMenuContent>
                                <DropdownMenuItem onClick={() => console.log('View', customer.id)}>
                                    View
                                </DropdownMenuItem>
                                <DropdownMenuItem onClick={() => {
                                        setEditCustomer(customer);
                                        setShowModal(true);
                                    }}>
                                    <Pencil size={16} /> Edit
                                </DropdownMenuItem>
                                <DropdownMenuItem onClick={() => handleDelete(customer.id)}>
                                    <Trash size={16} /> Delete
                                </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                            </TableCell>
                        </TableRow>
                        ))}
                    </TableBody>
                </Table>

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
