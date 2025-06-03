import { Head, usePage, router } from "@inertiajs/react";
import React, { useState } from "react";
import { useReactTable, SortingState, PaginationState } from "@tanstack/react-table";
import { Table, TableHead, TableBody, TableRow, TableCell } from "@/components/ui/table";
import AppLayout from "@/layouts/app-layout";
import { type BreadcrumbItem } from "@/types";
import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label"
import { Input } from "@/components/ui/input";
import { Pencil, Trash, PlusCircle, EllipsisVertical, SendHorizontal, Wallet, Eye } from "lucide-react";
import { DropdownMenuItem, DropdownMenuContent, DropdownMenuTrigger, DropdownMenu } from "@/components/ui/dropdown-menu";
import Modal from "@/components/ui/modal";
import { debounce } from "lodash";
import Pagination from "@/components/pagination";
import { SlideOver, SlideOverPanel, SlideOverHeader, SlideOverBody } from "@/components/ui/slide-over";
import {
    Sheet,
    SheetClose,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
  } from "@/components/ui/sheet"
  import { Meter, columns} from "./columns";
  import { DataTable } from "./data-table";
  import { formatCurrency } from '@/lib/utils';


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
    resident?: {
        name: string;
    };
}

interface MeterProps {
    meters: {
        data: Meter[];
        links: any[];
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: "Meters",
        href: "/meters",
    },
];

export default function Meters({ meters }: MeterProps) {
    const { errors } = usePage().props;
    const [search, setSearch] = useState("");
    const [showModal, setShowModal] = useState(false);
    const [editMeter, setEditMeter] = useState<Meter | null>(null);
    const [showSlideOver, setShowSlideOver] = useState(false);
    const [selectedMeter, setSelectedMeter] = useState<Meter | null>(null);
    const [billsAndPayments, setBillsAndPayments] = useState<any[]>([]);
    const [sorting, setSorting] = useState<SortingState>([]);
    const [pagination, setPagination] = useState<PaginationState>({ pageIndex: 0, pageSize: 10 });

    const table = useReactTable({
        data: meters.data,
        columns,
        state: { sorting, pagination },
        onSortingChange: setSorting,
        onPaginationChange: setPagination,
        getPaginationRowModel: getPaginationRowModel(),
      });

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

    const handleDelete = (id: number) => {
        if (confirm("Are you sure you want to delete this meter?")) {
            router.delete(`/meters/${id}`);
        }
    };

    const handleSave = (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        const formData = new FormData(e.currentTarget);

        if (editMeter) {
            router.put(`/meters/${editMeter.id}`, formData);
        } else {
            router.post("/meters", formData);
        }

        setShowModal(false);
        setEditMeter(null);
    };

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

    const formatCurrency = (value: any) => Number(value ?? 0).toFixed(2);

    const statusClasses = {
        active: "bg-lime-400/20 text-lime-700 dark:bg-lime-400/10 dark:text-lime-300",
        replaced: "bg-amber-400/15 text-amber-700 dark:bg-amber-400/10 dark:text-amber-400",
        inactive: "bg-stone-400/15 text-stone-700 dark:bg-stone-400/10 dark:text-stone-400",
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
                            onChange={(e) => setSearch(e.target.value)}
                        />
                        <Button
                            onClick={() => {
                                setEditMeter(null);
                                setShowModal(true);
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
                    {table.getHeaderGroups().map((headerGroup) => (
                        <TableRow key={headerGroup.id}>
                        {headerGroup.headers.map((header) => (
                            <TableCell key={header.id}>
                            {header.isPlaceholder ? null : header.renderHeader()}
                            </TableCell>
                        ))}
                        </TableRow>
                    ))}
                    </TableHead>
                    <TableBody>
                    {table.getRowModel().rows.map((row) => (
                        <TableRow key={row.id}>
                        {row.getVisibleCells().map((cell) => (
                            <TableCell key={cell.id}>{cell.renderCell()}</TableCell>
                        ))}
                        </TableRow>
                    ))}
                    </TableBody>
                </Table>
                <Pagination
                    totalPages={table.getPageCount()}
                    currentPage={table.getState().pagination.pageIndex + 1}
                    onPageChange={(page) => table.setPageIndex(page - 1)}
                />
                

                {/* Add/Edit Meter Modal */}
                {showModal && (
                    <Modal onClose={() => setShowModal(false)}>
                        <form onSubmit={handleSave} className="p-6 space-y-4">
                            <h2 className="text-xl font-bold">
                                {editMeter ? "Edit Meter" : "Add Meter"}
                            </h2>
                            <Input
                                type="text"
                                name="meter_number"
                                placeholder="Meter Number"
                                defaultValue={editMeter?.meter_number || ""}
                                required
                            />
                            <Input
                                type="text"
                                name="location"
                                placeholder="Location"
                                defaultValue={editMeter?.location || ""}
                                required
                            />
                            <select
                                name="status"
                                defaultValue={editMeter?.status || "active"}
                                className="w-full p-2 border rounded"
                            >
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="replaced">Replaced</option>
                            </select>
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
                                        <p>Amount Due: KES {item.amount_due}</p>
                                        <p>Status: {item.status}</p>
                                        {item.payments && item.payments.length > 0 && (
                                            <div className="mt-2">
                                                <h4 className="font-bold">Payments:</h4>
                                                {item.payments.map((payment: any, idx: number) => (
                                                    <div key={idx} className="ml-2">
                                                        <p>Amount: KES {payment.amount}</p>
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

            </div>
        </AppLayout>
    );
}
