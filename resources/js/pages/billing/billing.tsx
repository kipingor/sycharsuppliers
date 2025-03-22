import { Head, usePage, router, Deferred } from "@inertiajs/react";
import { Fragment, useEffect, useState } from "react";
import { Pencil, Trash, PlusCircle, EllipsisVertical } from "lucide-react";
import { useForm } from 'laravel-precognition-react-inertia';
import AppLayout from "@/layouts/app-layout";
import { type BreadcrumbItem } from "@/types";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { DropdownMenuItem, DropdownMenuContent, DropdownMenuTrigger, DropdownMenu } from "@/components/ui/dropdown-menu";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/table";
import { Select } from '@headlessui/react';
import Modal from "@/components/ui/modal";
import { debounce } from "lodash";
import Pagination from "@/components/pagination";

interface Meter {
    id: number;
    meter_name: string;
    customer?: {
        name: string;
    };
}

interface Bill {
    id: number;
    meter_id: number;
    amount_due: number;
    status: 'pending' | 'paid' | 'unpaid' | 'overdue' | 'partially paid' | 'void';
    meter: {
        customer?: {
            name: string;
        };
    };
    details?: {
        current_reading_value: number;
    };
}

interface BillsProps {
    bills: {
        data: Bill[];
        links: any[];
    };
    meters: Meter[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { 
        title: "Bills", 
        href: "/bills" 
    },
];

export default function Bills({ bills, meters }: BillsProps) {    
    const { errors, props } = usePage().props;
    const [search, setSearch] = useState<string>("");
    const [showModal, setShowModal] = useState<boolean>(false);
    const [editBill, setEditBill] = useState<any | null>(null);
    const [lastReading, setLastReading] = useState<number>(0);
    const [amountDue, setAmountDue] = useState(0);

    const handleSearch = debounce((query: string) => {
        router.reload({
            only: ['bills'],
            data: { search: query },
            replace: true,
        });
    }, 300);

    const filteredBills = bills.data.filter((b: any) =>
        b.meter?.customer?.name?.toLowerCase()?.includes(search.toLowerCase())
    );

    const statusClasses = {
        paid: "bg-lime-400/20 text-lime-700 dark:bg-lime-400/10 dark:text-lime-300",
        pending: "bg-sky-400/15 text-sky-700 dark:bg-sky-400/10 dark:text-sky-400",
        overdue: "bg-pink-400/15 text-pink-700 dark:bg-pink-400/10 dark:text-pink-400",
        unpaid: "bg-amber-400/15 text-amber-700 dark:bg-amber-400/10 dark:text-amber-400",
        'partially paid': "bg-blue-400/15 text-blue-700 dark:bg-blue-400/10 dark:text-blue-400",
        void: "bg-gray-400/15 text-gray-700 dark:bg-gray-400/10 dark:text-gray-400",
        default: "bg-stone-400/15 text-stone-700 dark:bg-stone-400/10 dark:text-stone-400",
    };

    const handleDelete = (id: number) => {
        if (confirm("Are you sure you want to delete this bill?")) {
            form.delete(`/billing/${id}`, {
                onSuccess: () => form.reset(),
            });
        }
    };

    const handleValidation = (e: React.ChangeEvent<HTMLInputElement>) => {
        form.setData('reading_value', e.target.value);
        form.validate('reading_value');
    };

    const form = useForm('post', '/billing', {
        meter_id: '',
        reading_value: '',
    });

    const handleSubmit = (e: React.ChangeEvent<HTMLInputElement>) => {
        e.preventDefault();
        
        // Dynamic method and URL based on edit mode
        const method = editBill ? 'put' : 'post';
        const url = editBill ? `/billing/${editBill.id}` : '/billing';

        form.submit(method, url, {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setShowModal(false);
                setEditBill(null);
            },
        });
        
    };

    const openEditModal = (bill: any) => {
        setEditBill(bill);
    
        form.setData({
            meter_id: bill.meter_id,
            reading_value: bill.details?.current_reading_value ?? '',
        });
    
        setShowModal(true);
    };

    const [selectedMeter, setSelectedMeter] = useState(meters[0])
    

    const calculateAmountDue = async (meterId: string, readingValue: string) => {
        try {
            const response = await fetch(`/api/meter-readings/last/${meterId}`, {
                method: 'GET',
                credentials: 'include', // This ensures the auth cookie is sent
                headers: {
                    'Accept': 'application/json',
                },
            });
            if (!response.ok) throw new Error("Failed to fetch last reading");
            
            const lastReadingData = await response.json();
            const previous = parseFloat(lastReadingData?.reading_value || '0');
            const current = parseFloat(readingValue);
            const unitPrice = 300;
          
            return (current - previous) * unitPrice;
        } catch (error) {
            console.error('Error calculating amount due:', error);
            return 0;
        }
    };

    useEffect(() => {
        const fetchAmountDue = async () => {
            if (form.data.meter_id && form.data.reading_value) {
                const amount = await calculateAmountDue(form.data.meter_id, form.data.reading_value);
                setAmountDue(amount);
            }
        };
        fetchAmountDue();
    }, [form.data.meter_id, form.data.reading_value]);

    

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
                            onChange={(e) => {
                                setSearch(e.target.value);
                                handleSearch(e.target.value);
                            }}
                        />
                        <Button
                            onClick={() => setShowModal(true)}
                            className="flex items-center gap-2"
                        >
                            <PlusCircle size={18} /> Add Bill
                        </Button>
                    </div>
                </div>

                <Table>
                    <TableHead>
                        <TableRow>
                        {['ID', 'Customer', 'Amount Due', 'Status', 'Actions'].map((header) => (
                            <TableCell key={header} className="text-left font-medium">
                            {header}
                            </TableCell>
                        ))}
                        </TableRow>
                    </TableHead>
                    <TableBody>
                        {filteredBills.map((bill) => (
                        <TableRow key={bill.id}>
                            <TableCell>{bill.id}</TableCell>
                            <TableCell>{bill.meter.customer?.name || 'N/A'}</TableCell>
                            <TableCell>{`KES ${bill.amount_due.toFixed(2)}`}</TableCell>
                            <TableCell>
                            <span className={`inline-flex items-center gap-x-1.5 rounded-md px-1.5 py-0.5 text-sm/5 font-medium sm:text-xs/5 ${statusClasses[bill.status] || statusClasses.default}`}>
                                {bill.status}
                            </span>
                            </TableCell>
                            <TableCell>
                            <DropdownMenu>
                                <DropdownMenuTrigger>
                                <EllipsisVertical size={16} />
                                </DropdownMenuTrigger>
                                <DropdownMenuContent>
                                <DropdownMenuItem onClick={() => console.log('View', bill.id)}>
                                    View
                                </DropdownMenuItem>
                                <DropdownMenuItem onClick={() => console.log('Record Payment', bill.meter_id)}>
                                    Record Payment
                                </DropdownMenuItem>
                                <DropdownMenuItem onClick={() => openEditModal(bill)}>
                                    <Pencil size={16} /> Edit
                                </DropdownMenuItem>
                                <DropdownMenuItem onClick={() => handleDelete(bill.id)}>
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
                <Pagination links={bills.links} />

                {/* Add/Edit Bill Modal */}
                {showModal && (
                    <Modal onClose={() => setShowModal(false)}>
                        <form onSubmit={handleSubmit} className="p-6 space-y-4">
                            <h2 className="text-xl font-bold">{editBill ? "Edit Bill" : "Add Bill"}</h2>
                            <Select value={form.data.meter_id?.toString()} // Ensures value is a string
                                onChange={(e) => form.setData('meter_id', e.target.value)}
                                required>
                            <option value="none">Select a meter</option>
                            {meters.map((meter) => (
                                <option value={meter.id}>{meter.meter_name} - {meter.customer?.name || "N/A"}</option>
                            ))}
                            </Select>                                               
                            {errors.meter_id && <div className="text-red-500 text-sm mt-1">{errors.meter_id}</div>}
                            <Input
                                id="reading_value"
                                type="number"
                                placeholder="Current Reading"
                                value={form.data.reading_value}
                                onChange={handleValidation}
                                onBlur={handleValidation}
                                required
                            />
                            {form.invalid('reading_value') && (
                                <div className="text-red-500 text-sm mt-1">{form.errors.reading_value}</div>
                            )}
                            <p>Amount Due: KES <span className={amountDue <= 0 ? 'text-pink-700' : 'text-sky-800'}>{amountDue.toFixed(2)}</span></p>
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
