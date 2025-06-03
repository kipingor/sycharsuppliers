import { Head, usePage, router } from "@inertiajs/react";
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from "@/types";
import { useState } from "react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Pencil, Trash, PlusCircle, EllipsisVertical } from "lucide-react";
import { DropdownMenuItem, DropdownMenuContent, DropdownMenuTrigger, DropdownMenu } from "@/components/ui/dropdown-menu";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/table";
import Modal from "@/components/ui/modal";

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
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: "Residents",
        href: "/residents",
    },
];

export default function Residents({ residents }: ResidentsProps) {
    const { errors } = usePage().props;
    // const { residents } = usePage<{ residents: { data: Resident[]; links: any } }>().props;

    const [search, setSearch] = useState("");
    const [showModal, setShowModal] = useState(false);
    const [editResident, setEditResident] = useState<Resident | null>(null);
    const filteredResidents = residents.data.filter((c) =>
        c.name.toLowerCase().includes(search.toLowerCase())
    );

    const handleDelete = (id: number) => {
        if (confirm("Are you sure you want to delete this resident?")) {
            router.delete(`/residents/${id}`);
        }
    };

    const handleSave = (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        const formData = new FormData(e.currentTarget);

        if (editResident) {
            router.put(`/residents/${editResident.id}`, formData);
        } else {
            router.post("/residents", formData);
        }

        setShowModal(false);
        setEditResident(null);
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
                            onChange={(e) => setSearch(e.target.value)}
                        />
                        <Button
                            onClick={() => {
                                setEditResident(null);
                                setShowModal(true);
                            }}
                            className="flex items-center gap-2"
                        >
                            <PlusCircle size={18} />
                            Add Resident
                        </Button>
                    </div>
                </div>

                <Table>
                    <TableHead>
                        <TableRow>
                        {['ID', 'Name', 'Email', 'Phone', 'Actions'].map((header) => (
                            <TableCell key={header} className="text-left font-medium">
                            {header}
                            </TableCell>
                        ))}
                        </TableRow>
                    </TableHead>
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
                                <DropdownMenuItem onClick={() => console.log('View', resident.id)}>
                                    View
                                </DropdownMenuItem>
                                <DropdownMenuItem onClick={() => {
                                        setEditResident(resident);
                                        setShowModal(true);
                                    }}>
                                    <Pencil size={16} /> Edit
                                </DropdownMenuItem>
                                <DropdownMenuItem onClick={() => handleDelete(resident.id)}>
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
                <div className="mt-4 flex justify-center gap-2">
                    {residents.links.map((link: { url: string; label: string; active: boolean }, index: number) => (
                        <Button
                            key={index}
                            variant={link.active ? "default" : "outline"}
                            onClick={() => link.url && router.get(link.url)}
                            disabled={!link.url}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ))}
                </div>

                {/* Add/Edit Resident Modal */}
                {showModal && (
                    <Modal onClose={() => setShowModal(false)}>
                        <form onSubmit={handleSave} className="p-6 space-y-4">
                            <h2 className="text-xl font-bold">
                                {editResident ? "Edit Resident" : "Add Resident"}
                            </h2>
                            <Input
                                type="text"
                                name="name"
                                placeholder="Name"
                                defaultValue={editResident?.name || ""}
                                required
                            />
                            <Input
                                type="email"
                                name="email"
                                placeholder="Email"
                                defaultValue={editResident?.email || ""}
                                required
                            />
                            <Input
                                type="text"
                                name="phone"
                                placeholder="Phone"
                                defaultValue={editResident?.phone || ""}
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
