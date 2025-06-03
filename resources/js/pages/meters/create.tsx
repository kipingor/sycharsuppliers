import { Head, useForm } from "@inertiajs/react";
import AppLayout from "@/layouts/app-layout";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { FormEvent } from "react";
import { type BreadcrumbItem } from "@/types";

interface Resident {
    id: number;
    name: string;
}

interface CreateMeterProps {
    residents: Resident[];
}

const CreateMeter = ({ residents }: CreateMeterProps) => {
    const { data, setData, post, processing, errors } = useForm({
        resident_id: "",
        meter_number: "",
        location: "",
        installation_date: "",
        status: true,
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post("/meters");
    };

    const breadcrumbs: BreadcrumbItem[] = [
        { title: "Meters", href: "/meters" },
        { title: "Create", href: "/meters/create" },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Create Meter" />

            <div className="max-w-lg mx-auto bg-white p-6 rounded-lg shadow">
                <h1 className="text-2xl font-bold mb-4">Add Meter</h1>

                <form onSubmit={handleSubmit} className="space-y-4">
                    <div>
                        <label className="block text-sm font-medium">Resident</label>
                        <select
                            className="w-full p-2 border rounded-md"
                            value={data.resident_id}
                            onChange={(e) => setData("resident_id", e.target.value)}
                            required
                        >
                            <option value="">Select a Resident</option>
                            {residents.map((resident) => (
                                <option key={resident.id} value={resident.id}>
                                    {resident.name}
                                </option>
                            ))}
                        </select>
                        {errors.resident_id && <p className="text-red-500 text-sm">{errors.resident_id}</p>}
                    </div>

                    <div>
                        <label className="block text-sm font-medium">Meter Number</label>
                        <Input
                            type="text"
                            name="meter_number"
                            value={data.meter_number}
                            onChange={(e) => setData("meter_number", e.target.value)}
                            required
                        />
                        {errors.meter_number && <p className="text-red-500 text-sm">{errors.meter_number}</p>}
                    </div>

                    <div>
                        <label className="block text-sm font-medium">Location</label>
                        <Input
                            type="text"
                            name="location"
                            value={data.location}
                            onChange={(e) => setData("location", e.target.value)}
                        />
                        {errors.location && <p className="text-red-500 text-sm">{errors.location}</p>}
                    </div>

                    <div>
                        <label className="block text-sm font-medium">Installation Date</label>
                        <Input
                            type="date"
                            name="installation_date"
                            value={data.installation_date}
                            onChange={(e) => setData("installation_date", e.target.value)}
                        />
                        {errors.installation_date && <p className="text-red-500 text-sm">{errors.installation_date}</p>}
                    </div>

                    <div>
                        <label className="block text-sm font-medium">Status</label>
                        <select
                            className="w-full p-2 border rounded-md"
                            value={data.status ? "1" : "0"}
                            onChange={(e) => setData("status", e.target.value === "1")}
                        >
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>

                    <div className="flex justify-end gap-2">
                        <Button type="button" variant="outline" href="/meters">Cancel</Button>
                        <Button type="submit" disabled={processing}>
                            {processing ? "Saving..." : "Save"}
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
};

export default CreateMeter;
