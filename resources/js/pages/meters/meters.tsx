import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { type Account, type Meter } from '@/types/models';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

interface Paginated<T> {
    data: T[];
    links: any[];
}

import { AddBillDialog } from '@/components/meters/add-bill-dialog';
import { AddPaymentDialog } from '@/components/meters/add-payment-dialog';
import MeterModal from '@/components/meters/meter-modal';
import Pagination from '@/components/pagination';
import { Table, TableBody, TableCell, TableHead, TableRow } from '@/components/table';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { SlideOver, SlideOverBody, SlideOverHeader, SlideOverPanel } from '@/components/ui/slide-over';
import { formatCurrency } from '@/lib/utils';
import { debounce } from 'lodash';
import { EllipsisVertical, Eye, Pencil, PlusCircle, SendHorizontal, Trash, Wallet } from 'lucide-react';
import { toast } from 'sonner';

interface MeterProps {
    meters: {
        data: Meter[];
        links: any[];
    };
    filters: {
        search?: string;
        status?: string;
        type?: string;
        meter_type?: string;
        account_id?: number;
        bulk_only?: boolean;
        individual_only?: boolean;
    };
    accounts: Account[];
    can: {
        create: boolean;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Meters',
        href: route('meters.index'),
    },
];

// export default function Meters({ meters, filters: initialFilters, accounts, can }: MeterProps) {
//     const { errors } = usePage().props;
//     const [search, setSearch] = useState<string>(initialFilters.search || "");

export default function Meters() {
    const { meters, filters, can } = usePage<{
        meters: Paginated<Meter>;
        filters: {
            search?: string;
        };
        can: Record<string, boolean>;
    }>().props;

    const search = filters.search ?? '';

    const handleSearch = debounce((value: string) => {
        router.get(
            route('meters.index'),
            { search: value },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    }, 300);

    const [showMeterModal, setShowMeterModal] = useState(false);
    const [editMeter, setEditMeter] = useState<Meter | null>(null);
    const [showSlideOver, setShowSlideOver] = useState(false);
    const [selectedMeter, setSelectedMeter] = useState<Meter | null>(null);
    const [billsAndPayments, setBillsAndPayments] = useState<any[]>([]);
    const [showAddBillDialog, setShowAddBillDialog] = useState(false);
    const [selectedMeterForBill, setSelectedMeterForBill] = useState<Meter | null>(null);
    const [showPaymentDialog, setShowPaymentDialog] = useState(false);
    const [selectedMeterForPayment, setSelectedMeterForPayment] = useState<Meter | null>(null);

    const handleDelete = (id: number): void => {
        if (confirm('Are you sure you want to delete this meter?')) {
            router.delete(route('meters.destroy', id), {
                preserveScroll: true,
                onSuccess: () => {
                    // Success handled by Inertia
                    toast('Meter deleted successfully!');
                },
                onError: () => {
                    toast('Failed to delete meter. Please try again.');
                    // alert('Failed to delete meter');
                },
            });
        }
    };

    const handleSave = (formData: FormData) => {
        if (editMeter) {
            formData.append('_method', 'PUT');
            router.post(route('meters.update', editMeter.id), formData, {
                preserveScroll: true,
                onSuccess: () => {
                    setShowMeterModal(false);
                    setEditMeter(null);
                    toast('Meter updated successfully!');
                },
                onError: (errors) => {
                    console.error('Update errors: ', errors);
                    toast('Failed to update meter. Please check the form.');
                },
            });
        } else {
            router.post(route('meters.store'), formData, {
                preserveScroll: true,
                onSuccess: () => {
                    setShowMeterModal(false);
                    setEditMeter(null);
                    toast('Meter created successfully!');
                },
                onError: (errors) => {
                    console.error('Creation errors:', errors);
                    toast('Failed to create meter. Please check the form.');
                },
            });
        }
    };

    // const handleView = async (meter: Meter) => {
    //     setSelectedMeter(meter);
    //     setShowSlideOver(true);
    //     // TODO: Replace with Inertia router or proper API route
    //     try {
    //         const response = await fetch(`/api/meters/${meter.id}/bills-payments`);
    //         const data = await response.json();
    //         setBillsAndPayments(Array.isArray(data.bills) ? data.bills : []);
    //     } catch (error) {
    //         console.error("Failed to fetch bills and payments", error);
    //         setBillsAndPayments([]);
    //     }
    // };

    const handleSendStatement = async (meter: Meter) => {
        // TODO: Replace with proper route - this API route may not exist
        try {
            await router.post(`/api/meters/${meter.id}/send-statement`);
            toast('Statement sent successfully!');
        } catch (error) {
            toast('Failed to send statement.', {
                description: String(error),
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

    const statusClasses = {
        active: 'bg-lime-400/20 text-lime-700 dark:bg-lime-400/10 dark:text-lime-300',
        replaced: 'bg-amber-400/15 text-amber-700 dark:bg-amber-400/10 dark:text-amber-400',
        inactive: 'bg-stone-400/15 text-stone-700 dark:bg-stone-400/10 dark:text-stone-400',
        faulty: 'bg-red-400/15 text-red-700 dark:bg-red-400/10 dark:text-red-400',
    };

    // NOTE: MeterModal expects residents prop but backend doesn't send it
    // Using empty array as fallback - this needs to be resolved
    const residentsFallback = { data: [] };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Meters" />

            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="mb-4 flex items-center justify-between">
                    <h1 className="text-2xl font-bold">Meters</h1>
                    <div className="flex gap-2">
                        <Input
                            value={search}
                            placeholder="Search by meter number or resident name..."
                            onChange={(e) => handleSearch(e.target.value)}
                        />
                        {can.create && (
                            <Button
                                onClick={() => {
                                    setEditMeter(null);
                                    setShowMeterModal(true);
                                }}
                                className="flex items-center gap-2"
                            >
                                <PlusCircle size={18} />
                                Add Meter
                            </Button>
                        )}
                        <Button
                            variant="outline"
                            onClick={() => {
                                window.open('/api/meters/download-statements', '_blank');
                            }}
                            className="flex items-center gap-2"
                        >
                            <SendHorizontal size={16} />
                            Download All Statements
                        </Button>
                    </div>
                </div>

                {meters.data.length === 0 ? (
                    <div className="py-12 text-center">
                        <p className="text-gray-500 dark:text-gray-400">No meters found.</p>
                        {can.create && (
                            <Button onClick={() => setShowMeterModal(true)} className="mt-4">
                                Add Your First Meter
                            </Button>
                        )}
                    </div>
                ) : (
                    <>
                        <Table>
                            <TableHead>
                                <TableRow>
                                    {['ID', 'Meter Number', 'Meter Name', 'Account', 'Status', 'Actions'].map((header) => (
                                        <TableCell key={header} className="text-left text-xs font-medium">
                                            {header}
                                        </TableCell>
                                    ))}
                                </TableRow>
                            </TableHead>
                            <TableBody>
                                {meters.data.map((meter) => (
                                    <TableRow key={meter.id}>
                                        <TableCell className="text-xs">{meter.id}</TableCell>
                                        <TableCell className="text-xs">{meter.meter_number}</TableCell>
                                        <TableCell className="text-xs font-medium">{meter.meter_name}</TableCell>
                                        <TableCell className="text-xs">
                                            {meter.account?.name || 'N/A'}
                                            {meter.account?.account_number && (
                                                <span className="ml-1 text-gray-500">({meter.account.account_number})</span>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-xs">
                                            <span
                                                className={`inline-flex items-center gap-x-1.5 rounded-md px-1.5 py-0.5 text-sm/5 font-medium sm:text-xs/5 ${statusClasses[meter.status ?? 'active'] || statusClasses.active}`}
                                            >
                                                {meter.status ?? 'active'}
                                            </span>
                                        </TableCell>
                                        <TableCell className="place-items-center text-xs">
                                            <DropdownMenu>
                                                <DropdownMenuTrigger>
                                                    <EllipsisVertical size={16} />
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent>
                                                    <DropdownMenuItem asChild>
                                                        <Link href={route('meters.show', meter.id)}>
                                                            <Eye size={16} className="mr-2" /> View
                                                        </Link>
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem className="text-xs" onClick={() => handleSendStatement(meter)}>
                                                        <SendHorizontal size={16} className="mr-2" /> Email Statement
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem className="text-xs" onClick={() => handleAddBill(meter)}>
                                                        <Wallet size={16} className="mr-2" /> Add Bill
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem className="text-xs" onClick={() => handleRecordPayment(meter)}>
                                                        <Wallet size={16} className="mr-2" /> Record Payment
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem asChild>
                                                        <Link href={route('meters.edit', meter.id)}>
                                                            <Pencil size={16} className="mr-2" /> Edit
                                                        </Link>
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem className="text-xs" onClick={() => handleDelete(meter.id)}>
                                                        <Trash size={16} className="mr-2" /> Delete
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
                    </>
                )}

                {/* Meter Modal - NOTE: residents prop missing from backend */}
                <MeterModal
                    show={showMeterModal}
                    onClose={() => {
                        setShowMeterModal(false);
                        setEditMeter(null);
                    }}
                    onSubmit={handleSave}
                    editMeter={editMeter as any}
                    residents={{ data: [] }} // Fallback empty residents
                    onAddResident={() => {}} // fix: Provide required onAddResident prop
                />

                {/* SlideOver Component */}
                <SlideOver open={showSlideOver} onClose={() => setShowSlideOver(false)}>
                    <SlideOverPanel className="max-w-lg p-6">
                        <SlideOverHeader title={selectedMeter?.meter_number || 'Meter Details'} onClose={() => setShowSlideOver(false)} />
                        <SlideOverBody>
                            {billsAndPayments.length === 0 ? (
                                <p className="p-6">No bills or payments found.</p>
                            ) : (
                                billsAndPayments.map((item, index) => (
                                    <div key={index} className="border-b p-2">
                                        <p className="text-sm">
                                            <span className="font-medium">Period:</span> {item.billing_period}
                                        </p>
                                        <p className="text-sm">
                                            <span className="font-medium">Total Amount:</span> {formatCurrency(item.total_amount)}
                                        </p>
                                        {item.paid_amount && item.paid_amount > 0 && (
                                            <p className="text-sm text-green-600">
                                                <span className="font-medium">Paid:</span> {formatCurrency(item.paid_amount)}
                                            </p>
                                        )}
                                        <p className="text-sm">
                                            <span className="font-medium">Balance:</span> {formatCurrency(item.balance || item.total_amount)}
                                        </p>
                                        <p className="text-sm">
                                            <span className="font-medium">Status:</span>
                                            <span
                                                className={`ml-2 inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ${
                                                    item.status === 'paid'
                                                        ? 'bg-green-50 text-green-700'
                                                        : item.status === 'pending'
                                                          ? 'bg-blue-50 text-blue-700'
                                                          : item.status === 'overdue'
                                                            ? 'bg-red-50 text-red-700'
                                                            : 'bg-gray-50 text-gray-700'
                                                }`}
                                            >
                                                {item.status.replace('_', ' ')}
                                            </span>
                                        </p>
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
                {selectedMeterForBill && <AddBillDialog open={showAddBillDialog} onOpenChange={setShowAddBillDialog} meter={selectedMeterForBill} />}

                {/* Add Payment Dialog */}
                {selectedMeterForPayment && (
                    <AddPaymentDialog open={showPaymentDialog} onOpenChange={setShowPaymentDialog} meter={selectedMeterForPayment} />
                )}
            </div>
        </AppLayout>
    );
}
