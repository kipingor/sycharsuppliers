import { Head, useForm } from "@inertiajs/react";
import AppLayout from "@/layouts/app-layout";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { FormEvent } from "react";
import { type BreadcrumbItem } from "@/types";

const breadcrumbs: BreadcrumbItem[] = [
    { title: "Customers", href: "/customers" },
    { title: "Edit", href: "#" },
];

export default function EditCustomer({ customer }: { customer: any }) {
    const { data, setData, put, processing, errors } = useForm({
        name: customer.name,
        email: customer.email || "",
        phone: customer.phone,
        address: customer.address || "",
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        put(`/customers/${customer.id}`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit Customer - ${customer.name}`} />

            <div className="max-w-lg mx-auto bg-white p-6 rounded-lg shadow">
                <h1 className="text-2xl font-bold mb-4">Edit Customer</h1>
                
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
                        <Button type="button" variant="outline" href="/customers">Cancel</Button>
                        <Button type="submit" disabled={processing}>
                            {processing ? "Saving..." : "Update"}
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
