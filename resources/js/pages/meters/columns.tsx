import {
    ColumnDef,
    ColumnFiltersState,
    SortingState,
    VisibilityState,
    flexRender,
    getCoreRowModel,
    getFilteredRowModel,
    getPaginationRowModel,
    getSortedRowModel,
    useReactTable,
} from "@tanstack/react-table";
import { Pencil, Trash, PlusCircle, EllipsisVertical, SendHorizontal, Wallet, Eye,  ArrowUpDown, ChevronDown, MoreHorizontal } from "lucide-react";
import { DropdownMenuItem, DropdownMenuContent, DropdownMenuTrigger, DropdownMenu } from "@/components/ui/dropdown-menu";
import { Checkbox } from "@/components/ui/checkbox";
import { Button } from "@/components/ui/button";
import { formatCurrency } from '@/lib/utils';

export type Meter = {
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

export const columns: ColumnDef<Meter>[] = [
    {
        id: "select",
        header: ({ table }) => (
            <Checkbox
                checked={
                    table.getIsAllPageRowsSelected() || 
                    (table.getIsSomePageRowsSelected() && "indeterminate")
                }
                onCheckedChange={(value) => table.toggleAllPageRowsSelected(!!value)}
                aria-lable="Select all"
            />
        ),
        cell: ({ row }) => (
            <Checkbox
                checked={row.getIsSelected()}
                onCheckedChange={(value) => row.toggleSelected(!!value)}
                aria-label="Select row"
            />
        ),
        enableSorting: false,
        enableHiding: false,
    },
    {
        accessorKey: "meter_number",
        header: "Meter Number",
        cell: ({ row }) => (
            <div className="font-bold">{row.getValue("meter_number")}</div>
        )
    },
    {
        accessorKey: "resident.name",
        header: ({ column }) => {
            return (
                <Button
                variant="ghost"
                onClick={() => column.toggleSorting(column.getIsSorted() === "asc")}
                >
                    Resident
                    <ArrowUpDown />
                </Button>
            )
        },
        header: "Resident",
        cell: ({ row }) => {row.getValue("resident?.name") ?? "N/A"},
    },
    {
        accessorKey: "location",
        header: "Location",
    },
    {
        accessorKey: "total_units",
        header: "Total Units",
    },
    {
        accessorKey: "total_billed",
        header: () => <div className="text-right">Total Billed</div>,
        cell: ({ row }) => {
            const billed = parseFloat(row.getValue(total_billed))

            // Format the amount as kenya shillings
            const formatted = new Intl.NumberFormat("Africa/Nairobi", {
                style: "currency",
                currency: "KES",
            }).format(billed)

            return <div className="text-right font-medium">{formatted}</div>
        },        
    },
    {
        accessorKey: "total_paid",
        header: () => <div className="text-right">Total Paid</div>,
        cell: ({ value }: any) => `KES ${value.toFixed(2)}`,
    },
    {
        accessorKey: "balance_due",
        header: () => <div className="text-right">Balance Due</div>,
        cell: ({ value }: any) => `KES ${value.toFixed(2)}`,
    },
    {
        accessorKey: "status",
        header: "Status",
        cell: ({ row }) => {
            const statusClasses = {
                active: "bg-lime-400/20 text-lime-700",
                replaced: "bg-amber-400/15 text-amber-700",
                inactive: "bg-stone-400/15 text-stone-700",
            };
            return (
                <span
                    className={`inline-flex px-2 py-1 rounded text-sm font-medium ${statusClasses[row.getValue("status")]}`}
                >
                    {row.getValue("status")}
                </span>
            );
        },
    },
    {
        id: "actions",        
        header: "Actions",
        enableHiding: false,
        cell: ({ row }) => {
            const meter = row.original

            return (
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="ghost" className="h-8 w-8 p-0">
                            <span className="sr-only">Open menu</span>
                            <MoreHorizontal />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuItem
                        onClick={() => navigator.clipboard.writeText(meter.id)}
                        >
                            Copy Meter id
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem>Edit</DropdownMenuItem>
                        <DropdownMenuItem>Delete</DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            )
        },
    },
];