import { Head, usePage, router } from "@inertiajs/react";
import AppLayout from "@/layouts/app-layout";
import { type BreadcrumbItem, type Meter } from "@/types";
import { useState } from "react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Pencil, Trash, PlusCircle } from "lucide-react";
import Table from "@/components/Table";
import Modal from "@/components/ui/modal";

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: "Meters",
        href: "/meters",
    },
];

export default function Meters() {
    const { meters } = usePage<{ meters: { data: Meter[]; links: any } }>().props;

    const [search, setSearch] = useState("");
    const [showModal, setShowModal] = useState(false);
    const [editMeter, setEditMeter] = useState<Meter | null>(null);

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

                <Table
                    headers={["ID", "Meter Number", "Customer", "Location", "Status", "Actions"]}
                    data={meters.data
                        .filter((m) =>
                            m.meter_number.toLowerCase().includes(search.toLowerCase())
                        )
                        .map((meter) => [
                            meter.id,
                            meter.meter_number,
                            meter.customer.name,
                            meter.location,
                            <span
                                key={meter.id}
                                className={`px-2 py-1 rounded ${
                                    meter.status === "active"
                                        ? "bg-green-200 text-green-800"
                                        : "bg-red-200 text-red-800"
                                }`}
                            >
                                {meter.status}
                            </span>,
                            <div key={meter.id} className="flex gap-2">
                                <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={() => {
                                        setEditMeter(meter);
                                        setShowModal(true);
                                    }}
                                >
                                    <Pencil size={16} />
                                </Button>
                                <Button
                                    size="sm"
                                    variant="destructive"
                                    onClick={() => handleDelete(meter.id)}
                                >
                                    <Trash size={16} />
                                </Button>
                            </div>,
                        ])}
                />

                {/* Pagination */}
                <div className="mt-4 flex justify-between">
                    {meters.links.map((link: { url: string; label: string; active: boolean }, index: number) => (
                        <Button
                            key={index}
                            variant={link.active ? "default" : "outline"}
                            onClick={() => link.url && router.get(link.url)}
                            disabled={!link.url}
                        >
                            {link.label}
                        </Button>
                    ))}
                </div>

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
