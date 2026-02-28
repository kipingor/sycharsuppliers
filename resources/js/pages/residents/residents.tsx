import { Head, usePage, router, Link } from "@inertiajs/react";
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from "@/types";
import { useState } from "react";
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
    email: string;
    phone: string;
}

interface ResidentsProps {
    residents: {
        data: Resident[];
        links: any[];
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

export default function Residents({ residents, can }: ResidentsProps) {
    const { errors } = usePage().props;
    const [search, setSearch] = useState("");
    const [showAddResidentModal, setShowAddResidentModal] = useState(false);
    const [editResident, setEditResident] = useState<Resident | null>(null);

    const handleSearch = debounce((query: string) => {
        router.get(route('residents.index'), { search: query }, {
            only: ['residents'],
            replace: true,
            preserveState: true,
        });
    }, 300);

    const filteredResidents = residents.data.filter((c) =>
        c.name.toLowerCase().includes(search.toLowerCase())
    );

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
                <div className="flex justify-between items-center mb-4">
                    <h1 className="text-2xl font-bold">Residents</h1>
                    <div className="flex gap-2">
                        <Input
                            type="text"
                            placeholder="Search residents..."
                            value={search}
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
                                {filteredResidents.map((resident) => (
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
                    initialData={editResident ?? undefined}
                />
            </div>
        </AppLayout>
    );
}
