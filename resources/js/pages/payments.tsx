import { Head, usePage, router } from "@inertiajs/react";
import AppLayout from "@/layouts/app-layout";
import { type BreadcrumbItem, type Payment } from "@/types";
import { useState } from "react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Pencil, Trash, PlusCircle } from "lucide-react";
import Table from "@/components/Table";
import Modal from "@/components/ui/modal";

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: "Payments",
        href: "/payments",
    },
];

export default function Payments() {
    const { payments } = usePage<{ payments: { data: Payment[]; links: any } }>().props;

    const [search, setSearch] = useState("");
    const [showModal, setShowModal] = useState(false);
    const [editPayment, setEditPayment] = useState<Payment | null>(null);

    const handleDelete = (id: number) => {
        if (confirm("Are you sure you want to delete this payment?")) {
            router.delete(`/payments/${id}`);
        }
    };

    const handleSave = (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        const formData = new FormData(e.currentTarget);

        if (editPayment) {
            router.put(`/payments/${editPayment.id}`, formData);
        } else {
            router.post("/payments", formData);
        }

        setShowModal(false);
        setEditPayment(null);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Payments" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="flex justify-between items-center mb-4">
                    <h1 className="text-2xl font-bold">Payments</h1>
                    <div className="flex gap-2">
                        <Input
                            type="text"
                            placeholder="Search payments..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                        />
                        <Button
                            onClick={() => {
                                setEditPayment(null);
                                setShowModal(true);
                            }}
                            className="flex items-center gap-2"
                        >
                            <PlusCircle size={18} />
                            Add Payment
                        </Button>
                    </div>
                </div>

                <Table
                    headers={["ID", "Customer", "Payment Date", "Amount", "Method", "Transaction ID", "Actions"]}
                    data={payments.data
                        .filter((p) =>
                            p.customer.name.toLowerCase().includes(search.toLowerCase())
                        )
                        .map((payment) => [
                            payment.id,
                            payment.customer.name,
                            payment.payment_date,
                            `KES ${payment.amount.toFixed(2)}`,
                            payment.method,
                            payment.transaction_id,
                            <div key={payment.id} className="flex gap-2">
                                <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={() => {
                                        setEditPayment(payment);
                                        setShowModal(true);
                                    }}
                                >
                                    <Pencil size={16} />
                                </Button>
                                <Button
                                    size="sm"
                                    variant="destructive"
                                    onClick={() => handleDelete(payment.id)}
                                >
                                    <Trash size={16} />
                                </Button>
                            </div>,
                        ])}
                />

                {/* Pagination */}
                <div className="mt-4 flex justify-between">
                    {payments.links.map((link: { url: string; label: string; active: boolean }, index: number) => (
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

                {/* Add/Edit Payment Modal */}
                {showModal && (
                    <Modal onClose={() => setShowModal(false)}>
                        <form onSubmit={handleSave} className="p-6 space-y-4">
                            <h2 className="text-xl font-bold">
                                {editPayment ? "Edit Payment" : "Add Payment"}
                            </h2>
                            <Input
                                type="text"
                                name="meter_id"
                                placeholder="Customer ID"
                                defaultValue={editPayment?.customer_id || ""}
                                required
                            />
                            <Input
                                type="date"
                                name="payment_date"
                                defaultValue={editPayment?.payment_date || ""}
                                required
                            />
                            <Input
                                type="number"
                                name="amount"
                                placeholder="Amount"
                                step="0.01"
                                defaultValue={editPayment?.amount || ""}
                                required
                            />
                            <select
                                name="method"
                                defaultValue={editPayment?.method || "M-Pesa"}
                                className="w-full p-2 border rounded"
                            >
                                <option value="M-Pesa">M-Pesa</option>
                                <option value="Bank Transfer">Bank Transfer</option>
                                <option value="Cash">Cash</option>
                            </select>
                            <Input
                                type="text"
                                name="transaction_id"
                                placeholder="Transaction ID"
                                defaultValue={editPayment?.transaction_id || ""}
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
