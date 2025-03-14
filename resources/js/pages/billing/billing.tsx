import { Head, usePage, router } from "@inertiajs/react";
import { useForm } from 'laravel-precognition-react-inertia';
import AppLayout from "@/layouts/app-layout";
import { type BreadcrumbItem } from "@/types";
import { useState } from "react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Pencil, Trash, PlusCircle } from "lucide-react";
import Table from "@/components/table";
import Modal from "@/components/ui/modal";
import Pagination from "@/components/pagination";

const breadcrumbs: BreadcrumbItem[] = [
    { 
        title: "Bills", 
        href: "/bills" 
    },
];

export default function Bills({ bills, meters }) {
    const { errors } = usePage().props;
    const [search, setSearch] = useState("");
    const [showModal, setShowModal] = useState(false);
    const [editBill, setEditBill] = useState(null);
    const [lastReading, setLastReading] = useState(0);

    // const form = useForm({
    //     meter_id: "",
    //     reading_value: "",
    // });

    const form = useForm('post', '/billing', {
        meter_id: '',
        reading_value: '',
    });

    const handleDelete = (id) => {
        if (confirm("Are you sure you want to delete this bill?")) {
            form.delete(`/billing/${id}`, {
                onSuccess: () => form.reset(),
            });
        }
    };

    const handleValidation = (e) => {
        form.setData('reading_value', e.target.value);
        form.validate('reading_value');
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        
        // Dynamic method and URL based on edit mode
        const method = editBill ? 'put' : 'post';
        const url = editBill ? `/billing/${editBill.id}` : '/billing';
    
        form.submit({
            method,
            url,
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setShowModal(false);
                setEditBill(null);  // Reset edit mode after saving
            },
        });
    };

    const openEditModal = (bill) => {
        setEditBill(bill);
    
        form.setData({
            meter_id: bill.meter_id,
            reading_value: bill.details.current_reading_value,
        });
    
        setShowModal(true);
    };

    const calculateAmountDue = () => {
        const current = parseFloat(form.data.reading_value || "0");
        const previous = parseFloat(form.data.previous_reading || "0");
        const unitPrice = 300;
        return (current - previous) * unitPrice;
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Bills" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="flex justify-between items-center mb-4">
                    <h1 className="text-2xl font-bold">Bills</h1>
                    <div className="flex gap-2">
                        <Input
                            type="text"
                            placeholder="Search bills..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                        />
                        <Button
                            onClick={() => setShowModal(true)}
                            className="flex items-center gap-2"
                        >
                            <PlusCircle size={18} /> Add Bill
                        </Button>
                    </div>
                </div>

                <Table
                    headers={["ID", "Customer", "Amount Due", "Status", "Actions"]}
                    data={bills.data.filter(b => b.meter?.customer?.name?.toLowerCase()?.includes(search.toLowerCase()))
                        .map((bill) => [
                            bill.id,
                            bill.meter.customer?.name || "N/A",
                            `KES ${parseFloat(bill.amount_due).toFixed(2)}`,
                            <span key={bill.id} className={`inline-flex item-center gap-x-1.5 rounded-md px-1.5 py-0.5 text-sm/5 font-medium sm:text-xs/5 ${
                                bill.status === "paid" ? "forced-color:outline bg-lime-400/20 text-lime-700 group-data-hover:bg-lime-400/30 dark:bg-lime-400/10 dark:text-lime-300 dark-data-hover:bg-lime-400/15" 
                                : bill.status === "pending" ? "forced-color:outline bg-sky-400/15 text-sky-700 group-data-hover:bg-sky-400/25 dark:bg-sky-400/10 dark:text-sky-400 dark-data-hover:bg-sky-400/20" 
                                : bill.status === "overdue" ? "forced-color:outline bg-pink-400/15 text-pink-700 group-data-hover:bg-pink-400/25 dark:bg-pink-400/10 dark:text-pink-400 dark-data-hover:bg-pink-400/20" 
                                : bill.status === "unpaid" ? "forced-color:outline bg-amber-400/15 text-amber-700 group-data-hover:bg-amber-400/25 dark:bg-amber-400/10 dark:text-amber-400 dark-data-hover:bg-amber-400/20" 
                                : "forced-color:outline bg-stone-400/15 text-stone-700 group-data-hover:bg-stone-400/25 dark:bg-stonr-400/10 dark:text-stone-400 dark-data-hover:bg-stone-400/20"}`}>
                                {bill.status}
                            </span>,
                            <div key={bill.id} className="flex gap-2">
                                <Button size="sm" variant="outline" onClick={() => openEditModal(bill)}>
                                    <Pencil size={16} />
                                </Button>
                                <Button size="sm" variant="destructive" onClick={() => handleDelete(bill.id)}>
                                    <Trash size={16} />
                                </Button>
                            </div>,
                        ])}
                />

                {/* Pagination */}
                <div className="mt-4 flex justify-center gap-2">
                    {bills.links.map((link: { url: string; label: string; active: boolean }, index: number) => (
                        <Button
                            key={index}
                            variant={link.active ? "default" : "outline"}
                            onClick={() => link.url && router.get(link.url)}
                            disabled={!link.url}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ))}
                </div>

                {/* Add/Edit Bill Modal */}
                {showModal && (
                    <Modal onClose={() => setShowModal(false)}>
                        <form onSubmit={handleSubmit} className="p-6 space-y-4">
                            <h2 className="text-xl font-bold">{editBill ? "Edit Bill" : "Add Bill"}</h2>
                            <select
                                id="meter_id"
                                name="meter_id"
                                value={form.data.meter_id}
                                onChange={(e) => form.setData('meter_id', e.target.value)}
                                required
                                className="w-full p-2 border rounded"
                            >
                                <option value="">Select a Meter</option>
                                {meters.map(meter => (
                                    <option key={meter.id} value={meter.id}>
                                        {meter.meter_name} - {meter.customer?.name || "N/A"}
                                    </option>
                                ))}
                            </select>
                            {errors.meter_id && <div className="text-red-500 text-sm mt-1">{errors.meter_id}</div>}
                            <Input
                                id="reading_value"
                                type="number"
                                placeholder="Current Reading"
                                value={form.data.reading_value}
                                onChange={handleValidation}
                                required
                            />
                            {form.invalid('reading_value') && (
                                <div className="text-red-500 text-sm mt-1">{form.errors.reading_value}</div>
                            )}
                            
                            <p>Amount Due: KES {calculateAmountDue().toFixed(2)}</p>
                            {errors.status && <div className="text-red-500 text-sm mt-1">{errors.status}</div>}
                            <div className="flex justify-end gap-2">
                                <Button type="button" variant="outline" onClick={() => setShowModal(false)}>
                                    Cancel
                                </Button>
                                <Button type="submit" disabled={form.processing}>
                                    {form.processing ? "Saving..." : "Save"}
                                </Button>
                            </div>
                        </form>
                    </Modal>
                )}
            </div>
        </AppLayout>
    );
}
