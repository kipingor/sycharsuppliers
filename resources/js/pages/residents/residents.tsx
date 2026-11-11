import { Head, usePage, router, Link } from "@inertiajs/react";
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from "@/types";
import { useEffect, useMemo, useState } from "react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Pencil, Trash, PlusCircle, EllipsisVertical, Eye } from "lucide-react";
import { DropdownMenuItem, DropdownMenuContent, DropdownMenuTrigger, DropdownMenu } from "@/components/ui/dropdown-menu";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/table";
import AddResidentModal from "@/components/residents/add-residents-modal";
import { ResidentFormData } from "@/components/residents/add-residents-modal";
import { toast } from "sonner";
import { debounce } from "lodash";
import Pagination from "@/components/pagination";

interface Resident {
    id: number;
    name: string;
    email?: string | null;
    phone?: string | null;
    address?: string | null;
}

interface ResidentsProps {
    residents: {
        data: Resident[];
        links: any[];
    };
    filters: {
        search?: string;
    };
    can: {
        create: boolean;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: "Residents",
        href: route('residents.index'),
    },
];

export default function Residents({ residents, filters, can }: ResidentsProps) {
    const { errors } = usePage().props;
    const [search, setSearch] = useState(filters.search || "");
    const [showAddResidentModal, setShowAddResidentModal] = useState(false);
    const [editResident, setEditResident] = useState<Resident | null>(null);

    const handleSearch = useMemo(
        () =>
            debounce((query: string) => {
                router.get(route('residents.index'), { search: query || undefined }, {
                    only: ['residents', 'filters'],
                    replace: true,
                    preserveState: true,
                });
            }, 300),
        [],
    );

    useEffect(() => () => handleSearch.cancel(), [handleSearch]);

    const handleDelete = (id: number) => {
        if (confirm("Are you sure you want to delete this resident?")) {
            router.delete(route('residents.destroy', id), {
                preserveScroll: true,
                onSuccess: () => {
                    // Success handled by Inertia
                },
                onError: () => {
                    alert('Failed to delete resident');
                },
            });
        }
    };

    const handleAddResident = (data: ResidentFormData) => {
        const formData = new FormData();
        Object.entries(data).forEach(([key, value]) => {
            formData.append(key, value);
        });

        if (editResident) {
            formData.append('_method', 'PUT');

            router.post(route('residents.update', editResident.id), formData, {
                preserveScroll: true,
                onSuccess: () => {
                    setShowAddResidentModal(false);
                    setEditResident(null);
                    toast("Resident updated successfully!");
                },
                onError: (errors) => {
                    console.error("Update errors: ", errors);
                    toast("Failed to update the resident, please check the form");
                }
            });
        } else {
            router.post(route('residents.store'), formData, {
                preserveScroll: true,
                onSuccess: () => {
                    router.reload({ only: ['residents'] });
                    setShowAddResidentModal(false);
                },
                onError: (errors) => {
                    console.error("Validation errors:", errors);
                }
            });
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Residents" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <h1 className="text-2xl font-bold">Residents</h1>
                    <div className="flex min-w-0 flex-col gap-2 sm:flex-row">
                        <Input
                            type="text"
                            placeholder="Search residents..."
                            value={search}
                            className="w-full sm:w-64"
                            onChange={(e) => {
                                setSearch(e.target.value);
                                handleSearch(e.target.value);
                            }}
                        />
                        {can.create && (
                            <Button
                                onClick={() => {
                                    setEditResident(null);
                                    setShowAddResidentModal(true);
                                }}
                                className="flex items-center gap-2"
                            >
                                <PlusCircle size={18} />
                                Add Resident
                            </Button>
                        )}
                    </div>
                </div>

                {residents.data.length === 0 ? (
                    <div className="text-center py-12">
                        <p className="text-gray-500 dark:text-gray-400">No residents found.</p>
                        {can.create && (
                            <Button
                                onClick={() => setShowAddResidentModal(true)}
                                className="mt-4"
                            >
                                Add Your First Resident
                            </Button>
                        )}
                    </div>
                ) : (
                    <>
                        <div className="hidden md:block">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    {['ID', 'Name', 'Email', 'Phone', 'Actions'].map((header) => (
                                        <TableHead key={header} className="text-left font-medium">
                                            {header}
                                        </TableHead>
                                    ))}
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {residents.data.map((resident) => (
                                    <TableRow key={resident.id}>
                                        <TableCell>{resident.id}</TableCell>
                                        <TableCell>{resident.name}</TableCell>
                                        <TableCell>{resident.email}</TableCell>
                                        <TableCell>{resident.phone}</TableCell>
                                        <TableCell>
                                            <DropdownMenu>
                                                <DropdownMenuTrigger>
                                                    <EllipsisVertical size={16} />
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent>
                                                    <DropdownMenuItem asChild>
                                                        <Link href={route('residents.show', resident.id)}>
                                                            <Eye size={16} className="mr-2" /> View
                                                        </Link>
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem asChild>
                                                        <Link href={route('residents.edit', resident.id)}>
                                                            <Pencil size={16} className="mr-2" /> Edit
                                                        </Link>
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem onClick={() => handleDelete(resident.id)}>
                                                        <Trash size={16} className="mr-2" /> Delete
                                                    </DropdownMenuItem>
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                        </div>

                        <div className="space-y-3 md:hidden">
                            {residents.data.map((resident) => (
                                <div key={resident.id} className="rounded-lg border bg-card p-4 shadow-sm">
                                    <div className="flex items-start justify-between gap-3">
                                        <div className="min-w-0">
                                            <Link href={route('residents.show', resident.id)} className="font-semibold hover:underline">
                                                {resident.name}
                                            </Link>
                                            <p className="mt-1 break-all text-sm text-muted-foreground">{resident.email || 'No email'}</p>
                                        </div>
                                        <DropdownMenu>
                                            <DropdownMenuTrigger asChild>
                                                <Button variant="ghost" size="icon" aria-label={`Actions for ${resident.name}`}>
                                                    <EllipsisVertical size={18} />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end">
                                                <DropdownMenuItem asChild>
                                                    <Link href={route('residents.show', resident.id)}><Eye size={16} className="mr-2" /> View</Link>
                                                </DropdownMenuItem>
                                                <DropdownMenuItem asChild>
                                                    <Link href={route('residents.edit', resident.id)}><Pencil size={16} className="mr-2" /> Edit</Link>
                                                </DropdownMenuItem>
                                                <DropdownMenuItem onClick={() => handleDelete(resident.id)}><Trash size={16} className="mr-2" /> Delete</DropdownMenuItem>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </div>
                                    <div className="mt-3 grid grid-cols-1 gap-2 text-sm sm:grid-cols-2">
                                        <p><span className="text-muted-foreground">Phone:</span> {resident.phone || '—'}</p>
                                        <p className="sm:col-span-2"><span className="text-muted-foreground">Address:</span> {resident.address || '—'}</p>
                                    </div>
                                </div>
                            ))}
                        </div>

                        {/* Pagination */}
                        <Pagination links={residents.links} />
                    </>
                )}

                {/* Add/Edit Resident Modal */}
                <AddResidentModal
                    show={showAddResidentModal}
                    onClose={() => {
                        setShowAddResidentModal(false);
                        setEditResident(null);
                    }}
                    onSubmit={handleAddResident}
                    initialData={editResident ? {
                        name: editResident.name,
                        email: editResident.email ?? '',
                        phone: editResident.phone ?? '',
                        address: editResident.address ?? '',
                    } : undefined}
                />
            </div>
        </AppLayout>
    );
}
