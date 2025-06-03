import { Head, useForm, Link } from "@inertiajs/react";
import AppLayout from "@/layouts/app-layout";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { FormEvent } from "react";
import { type BreadcrumbItem } from "@/types";

const breadcrumbs: BreadcrumbItem[] = [
    { title: "Residents", href: "/residents" },
    { title: "Create", href: "/residents/create" },
];

export default function CreateResident() {
    const { data, setData, post, processing, errors } = useForm({
        name: "",
        email: "",
        phone: "",
        address: "",
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post("/residents");
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Create Resident" />

            <div className="max-w-lg mx-auto bg-white p-6 rounded-lg shadow">
                <h1 className="text-2xl font-bold mb-4">Create Resident</h1>
                
                <form onSubmit={handleSubmit} className="space-y-4">
                    <div>
                        <label className="block text-sm font-medium">Name</label>
                        <Input 
                            type="text" 
                            name="name" 
                            value={data.name} 
                            onChange={(e) => setData("name", e.target.value)} 
                            required 
                        />
                        {errors.name && <p className="text-red-500 text-sm">{errors.name}</p>}
                    </div>

                    <div>
                        <label className="block text-sm font-medium">Email</label>
                        <Input 
                            type="email" 
                            name="email" 
                            value={data.email} 
                            onChange={(e) => setData("email", e.target.value)} 
                        />
                        {errors.email && <p className="text-red-500 text-sm">{errors.email}</p>}
                    </div>

                    <div>
                        <label className="block text-sm font-medium">Phone</label>
                        <Input 
                            type="text" 
                            name="phone" 
                            value={data.phone} 
                            onChange={(e) => setData("phone", e.target.value)} 
                            required 
                        />
                        {errors.phone && <p className="text-red-500 text-sm">{errors.phone}</p>}
                    </div>

                    <div>
                        <label className="block text-sm font-medium">Address</label>
                        <Input 
                            type="text" 
                            name="address" 
                            value={data.address} 
                            onChange={(e) => setData("address", e.target.value)} 
                        />
                        {errors.address && <p className="text-red-500 text-sm">{errors.address}</p>}
                    </div>

                    <div className="flex justify-end gap-2">
                        <Link href="/residents" className="inline-flex items-center justify-center rounded-md text-sm font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 border border-input bg-background hover:bg-accent hover:text-accent-foreground h-10 px-4 py-2">
                            Cancel
                        </Link>
                        <Button type="submit" disabled={processing}>
                            {processing ? "Saving..." : "Save"}
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
