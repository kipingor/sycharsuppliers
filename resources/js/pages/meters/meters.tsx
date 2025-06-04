import { 
    Head, 
    usePage, 
    router 
} from "@inertiajs/react";
import AppLayout from "@/layouts/app-layout";
import { type BreadcrumbItem } from "@/types";
import { useState, useEffect } from "react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { 
    Pencil, 
    Trash, 
    PlusCircle, 
    EllipsisVertical, 
    SendHorizontal, 
    Wallet, 
    Eye 
} from "lucide-react";
import { 
    Table, 
    TableBody, 
    TableCell, 
    TableHead, 
    TableRow 
} from "@/components/table";
import { 
    DropdownMenuItem, 
    DropdownMenuContent, 
    DropdownMenuTrigger, 
    DropdownMenu 
} from "@/components/ui/dropdown-menu";
import { debounce } from "lodash";
import Pagination from "@/components/pagination";
import { 
    SlideOver, 
    SlideOverPanel, 
    SlideOverHeader, 
    SlideOverBody 
} from "@/components/ui/slide-over";
import { toast } from "sonner";
import MeterModal from "@/components/meters/meter-modal";
import AddResidentModal from "@/components/residents/add-residents-modal";
import { AddBillDialog } from "@/components/meters/add-bill-dialog";
import { AddPaymentDialog } from "@/components/meters/add-payment-dialog";
import { ResidentFormData } from "@/components/residents/add-residents-modal";
import { formatCurrency } from '@/lib/utils';

interface Resident {
    id: number;
    name: string;
}

interface Meter {
    id: number;
    meter_name: string;
    meter_number: string;
    location: string;
    status: 'active' | 'inactive' | 'replaced';
    total_units: number;
    total_billed: number;
    total_paid: number;
    balance_due: number;
    installation_date: string;
    resident_id?: number;
    resident?: {
        id: number;
        name: string;
    };
}

interface MeterProps {
    meters: {
        data: Meter[];
        links: any[];
    };
    residents: {
        data: {
            id: number;
            name: string;
        }[];
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: "Meters",
        href: "/meters",
    },
];

export default function Meters({ meters, residents }: MeterProps) {
    const { errors } = usePage().props;    
    const [search, setSearch] = useState("");
    const [showMeterModal, setShowMeterModal] = useState(false);
    const [editMeter, setEditMeter] = useState<Meter | null>(null);
    const [showSlideOver, setShowSlideOver] = useState(false);
    const [selectedMeter, setSelectedMeter] = useState<Meter | null>(null);
    const [billsAndPayments, setBillsAndPayments] = useState<any[]>([]);
    const [showAddBillDialog, setShowAddBillDialog] = useState(false);
    const [selectedMeterForBill, setSelectedMeterForBill] = useState<Meter | null>(null);
    const [showPaymentDialog, setShowPaymentDialog] = useState(false);
    const [selectedMeterForPayment, setSelectedMeterForPayment] = useState<Meter | null>(null);
    const [showAddResidentModal, setShowAddResidentModal] = useState(false);

    const handleSearch = debounce((query: string) => {
        router.reload({
            only: ['meters'],
            data: { search: query },
            replace: true,
        });
    }, 300);

    const filteredMeters = meters.data.filter((m: any) =>
        m.resident?.name?.toLowerCase()?.includes(search.toLowerCase())
    );

    const handleDelete = (id: number): void => {
        router.delete(`/meters/${id}`, {
            onBefore: () => confirm("Are you sure you want to delete this meter?"),
        });
    };

    const handleSave = (formData: FormData) => {
        // Add debugging to see what's being sent
        console.log('Form data being submitted:', Object.fromEntries(formData.entries()));

        if (editMeter) {
            formData.append('_method', 'PUT');
        
            router.post(`/meters/${editMeter.id}`, formData, {
                preserveScroll: true,
                onSuccess: () => {
                    setShowMeterModal(false);
                    setEditMeter(null);
                    // Optional: show success message
                    toast("Meter updated successfully!");
                },
                onError: (errors) => {
                    console.error("Update errors:", errors);
                    toast("Failed to update meter. Please check the form.");
                }
            });
        } else {
            router.post("/meters", formData, {
                preserveScroll: true,
                onSuccess: () => {
                    setShowMeterModal(false);
                    setEditMeter(null);
                    toast("Meter created successfully!");
                },
                onError: (errors) => {
                    console.error("Creation errors:", errors);
                    toast("Failed to create meter. Please check the form.");
                }
            });
        }
    
        setShowMeterModal(false);
        setEditMeter(null);
    };
    

    // const handleSave = (e: React.FormEvent<HTMLFormElement>) => {
    //     e.preventDefault();
    //     const formData = new FormData(e.currentTarget);

    //     if (editMeter) {
    //         router.put(`/meters/${editMeter.id}`, formData);
    //     } else {
    //         console.log(formData);
    //         router.post("/meters", formData);
    //     }

    //     setShowMeterModal(false);
    //     setEditMeter(null);
    // };

    const handleView = async (meter: Meter) => {
        setSelectedMeter(meter);
        setShowSlideOver(true);
        // Fetch billing and payments
        try {
            const response = await fetch(`/api/meters/${meter.id}/bills-payments`);
            const data = await response.json();
            console.log(data.bills);
            setBillsAndPayments(Array.isArray(data.bills) ? data.bills : []);
        } catch (error) {
            console.error("Failed to fetch bills and payments", error);
            setBillsAndPayments([]);
        }
    };

    const handleSendStatement = async (meter: Meter) => {
        try {
            await router.post(`/api/meters/${meter.id}/send-statement`);
            toast("Statement sent successfully!", {
                description: "I need to put the current date",
                action: {
                    label: "Undo",
                    onClick: () => console.log("Undo - need to create the function"),
                },
            });
        } catch (error) {
            toast("Failed to send statement.", {
                description: String(error)
            });
        }
    };

    const handleAddBill = (meter: Meter) => {
        setSelectedMeterForBill(meter);
        setShowAddBillDialog(true);
    };

    const handleRecordPayment = (meter: Meter) => {
        setSelectedMeterForPayment(meter);
        setShowPaymentDialog(true);
    };

    const formatCurrency = (value: any) => Number(value ?? 0).toFixed(2);

    const statusClasses = {
        active: "bg-lime-400/20 text-lime-700 dark:bg-lime-400/10 dark:text-lime-300",
        replaced: "bg-amber-400/15 text-amber-700 dark:bg-amber-400/10 dark:text-amber-400",
        inactive: "bg-stone-400/15 text-stone-700 dark:bg-stone-400/10 dark:text-stone-400",
    };

    const handleAddResident = (data: ResidentFormData) => {
        const formData = new FormData();
        Object.entries(data).forEach(([key, value]) => {
            formData.append(key, value);
        });
        
        router.post('/residents', formData, {
            preserveScroll: true,
            onSuccess: () => {
                // Reload the page to get fresh data including the new resident
                router.reload({ only: ['residents', 'meters'] });
                setShowAddResidentModal(false);
            },
            onError: (errors) => {
                console.error("Validation errors:", errors);
            }
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Meters" />
            
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="flex justify-between items-center mb-4">
                    <h1 className="text-2xl font-bold">Meters</h1>
                    <div className="flex gap-2">
                        <Input
                            type="text"
                            placeholder="Search meters..."
                            value={search}
                            onChange={(e) => {
                                setSearch(e.target.value);
                                handleSearch(e.target.value); // <- trigger reload here
                            }}
                        />
                        <Button
                            onClick={() => {
                                setEditMeter(null);
                                setShowMeterModal(true);
                                console.log(residents);
                            }}
                            className="flex items-center gap-2"
                        >
                            <PlusCircle size={18} />
                            Add Meter
                        </Button>                        
                    </div>
                </div>

                <Table>
                    <TableHead>
                        <TableRow>
                            {["ID", "Meter Number", "Resident", "Location", "Total Units", "Total Billed", "Total Paid", "Balance Due", "Status", "Actions"].map((header) => (
                                <TableCell key={header} className="text-left font-medium text-xs">
                                    {header}
                                </TableCell>
                            ))}
                        </TableRow>
                    </TableHead>
                    <TableBody>
                        {filteredMeters.map((meter) => (
                            <TableRow key={meter.id}>
                                <TableCell className="text-xs">{meter.id}</TableCell>
                                <TableCell className="text-xs">{meter.meter_number}</TableCell>
                                <TableCell className="font-bold text-xs">{meter.resident?.name || 'N/A'}</TableCell>
                                <TableCell className="w-2/9 px-1 text-xs">{meter.location}</TableCell>
                                <TableCell className="text-xs">{meter.total_units}</TableCell>
                                <TableCell className="text-xs">{formatCurrency(meter.total_billed)}</TableCell>
                                <TableCell className="text-xs">{formatCurrency(meter.total_paid)}</TableCell>
                                <TableCell className="text-xs">{formatCurrency(meter.balance_due)}</TableCell>
                                <TableCell className="text-xs">
                                    <span className={`inline-flex items-center gap-x-1.5 rounded-md px-1.5 py-0.5 text-sm/5 font-medium sm:text-xs/5 ${statusClasses[meter.status] || statusClasses.active}`}>
                                        {meter.status ?? 'Active'}
                                    </span>
                                </TableCell>
                                <TableCell className='place-items-center text-xs'>
                                    <DropdownMenu>
                                        <DropdownMenuTrigger>
                                            <span className="relative isolate inline-flex items-baseline justify-center">
                                                <EllipsisVertical size={16} />
                                            </span>                                            
                                        </DropdownMenuTrigger>                                        
                                        <DropdownMenuContent>                                            
                                            <DropdownMenuItem className="text-xs" onClick={() => handleView(meter)}>
                                                <Eye /> View
                                            </DropdownMenuItem>                                            
                                            <DropdownMenuItem className="text-xs" onClick={() => handleSendStatement(meter)}>
                                                <SendHorizontal size={16} /> Email Statement
                                            </DropdownMenuItem>
                                            <DropdownMenuItem className="text-xs" onClick={() => handleAddBill(meter)}>
                                                <Wallet /> Add Bill
                                            </DropdownMenuItem>
                                            <DropdownMenuItem className="text-xs" onClick={() => handleRecordPayment(meter)}>
                                                <Wallet /> Record Payment
                                            </DropdownMenuItem>
                                            <DropdownMenuItem className="text-xs" onClick={() => {
                                                setEditMeter(meter);
                                                setShowMeterModal(true);
                                            }}>
                                                <Pencil size={16} /> Edit
                                            </DropdownMenuItem>
                                            <DropdownMenuItem className="text-xs" onClick={() => handleDelete(meter.id)}>
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
                <Pagination links={meters.links} />
                

                {/* Replace the old modal with the new component */}
                <MeterModal
                    show={showMeterModal}
                    onClose={() => setShowMeterModal(false)}
                    onSubmit={handleSave}
                    editMeter={editMeter}
                    residents={residents}
                    onAddResident={() => setShowAddResidentModal(true)}
                />

                <AddResidentModal
                show={showAddResidentModal}
                onClose={() => setShowAddResidentModal(false)}
                onSubmit={handleAddResident}
                />

                {/* SlideOver Component */}
                <SlideOver open={showSlideOver} onClose={() => setShowSlideOver(false)}>
                    <SlideOverPanel className="p-6 max-w-lg">
                        <SlideOverHeader title={selectedMeter?.meter_number || "Meter Details"} onClose={() => setShowSlideOver(false)} />
                        <SlideOverBody>
                            {billsAndPayments.length === 0 ? (
                                <p className="p-6">No bills or payments found.</p>
                            ) : (
                                billsAndPayments.map((item, index) => (
                                    <div key={index} className="p-2 border-b">
                                        <p>Amount Due: {formatCurrency(item.amount_due)}</p>
                                        <p>Status: {item.status}</p>
                                        {item.payments && item.payments.length > 0 && (
                                            <div className="mt-2">
                                                <h4 className="font-bold">Payments:</h4>
                                                {item.payments.map((payment: any, idx: number) => (
                                                    <div key={idx} className="ml-2">
                                                        <p>Amount: {formatCurrency(payment.amount)}</p>
                                                        <p>Method: {payment.method}</p>
                                                        <p>Transaction ID: {payment.transaction_id}</p>
                                                    </div>
                                                ))}
                                            </div>
                                        )}
                                    </div>
                                ))
                            )}
                        </SlideOverBody>
                    </SlideOverPanel>
                </SlideOver>

                {/* Add Bill Dialog */}
                {selectedMeterForBill && (
                    <AddBillDialog
                        open={showAddBillDialog}
                        onOpenChange={setShowAddBillDialog}
                        meter={selectedMeterForBill}
                    />
                )}

                {/* Add Payment Dialog */}
                {selectedMeterForPayment && (
                    <AddPaymentDialog
                        open={showPaymentDialog}
                        onOpenChange={setShowPaymentDialog}
                        meter={selectedMeterForPayment}
                    />
                )}

            </div>
        </AppLayout>
    );
}
