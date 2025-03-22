import { Head, usePage, router } from "@inertiajs/react";
import AppLayout from "@/layouts/app-layout";
import { type BreadcrumbItem } from "@/types";
import { useState } from "react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Pencil, Trash, PlusCircle, EllipsisVertical } from "lucide-react";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/table";
import { DropdownMenuItem, DropdownMenuContent, DropdownMenuTrigger, DropdownMenu } from "@/components/ui/dropdown-menu";
import Modal from "@/components/ui/modal";
import { debounce } from "lodash";
import Pagination from "@/components/pagination";


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
    customer?: {
        name: string;
    };
}

interface MeterProps {
    meters: {
        data:Meter[];
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

    const handleSearch = debounce((query: string) => {
        router.reload({
            only: ['meters'],
            data: { search: query },
            replace: true,
        });
    }, 300);

    const filteredMeters = meters.data.filter((m: any) =>
        m.customer?.name?.toLowerCase()?.includes(search.toLowerCase())
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
                        <TableRow>
                            {["ID", "Meter Number", "Customer", "Location", "Total Units", "Total Billed", "Total Paid", "Balance Due", "Status", "Actions"].map((header) => (
                                <TableCell key={header} className="text-left font-medium">
                                    {header}
                                </TableCell>
                            ))}
                        </TableRow>
                    </TableHead>
                    <TableBody>
                        {filteredMeters.map((meter) => (
                            <TableRow key={meter.id}>
                                <TableCell>{meter.id}</TableCell>
                                <TableCell>{meter.meter_number}</TableCell>
                                <TableCell>{meter.customer?.name || 'N/A'}</TableCell>
                                <TableCell>{meter.location}</TableCell>
                                <TableCell>{meter.total_units}</TableCell>
                                <TableCell>{`KES ${formatCurrency(meter.total_billed)}`}</TableCell>
                                <TableCell>{`KES ${formatCurrency(meter.total_paid)}`}</TableCell>
                                <TableCell>{`KES ${formatCurrency(meter.balance_due)}`}</TableCell>
                                <TableCell>
                                    <span className={`inline-flex items-center gap-x-1.5 rounded-md px-1.5 py-0.5 text-sm/5 font-medium sm:text-xs/5 ${statusClasses[meter.status] || statusClasses.active}`}>
                                        {meter.status ?? 'Active'}
                                    </span>
                                </TableCell>
                                <TableCell>
                                    <DropdownMenu>
                                        <DropdownMenuTrigger>
                                            <EllipsisVertical size={16} />
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent>
                                            <DropdownMenuItem onClick={() => console.log('View', meter.id)}>
                                                View
                                            </DropdownMenuItem>
                                            <DropdownMenuItem onClick={() => console.log('Record Payment', meter.id)}>
                                                Record Payment
                                            </DropdownMenuItem>
                                            <DropdownMenuItem onClick={() => {
                                                                        setEditMeter(meter);
                                                                        setShowModal(true);
                                                                    }}>
                                                <Pencil size={16} /> Edit
                                            </DropdownMenuItem>
                                            <DropdownMenuItem onClick={() => handleDelete(meter.id)}>
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
            </div>
        </AppLayout>
    );
}
