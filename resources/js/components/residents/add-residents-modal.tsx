import { useEffect, useState } from "react";
import Modal from "@/components/ui/modal";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";

interface Resident {
    id: number;
    name: string;
    email: string;
    phone: number;
}


interface AddResidentModalProps {
    show: boolean;
    onClose: () => void;
    onSubmit: (formData: ResidentFormData) => void;
    initialData?: Partial<ResidentFormData>;
}

export interface ResidentFormData {
    name: string;
    email: string;
    phone: string;
    address: string;
}

export default function AddResidentModal({ show, onClose, onSubmit, initialData }: AddResidentModalProps) {
    const [formData, setFormData] = useState<ResidentFormData>({
        name: '',
        email: '',
        phone: '',
        address: '',
    });

    useEffect(() => {
        if (show && initialData) {
            setFormData({
                name: initialData.name || '',
                email: initialData.email || '',
                phone: initialData.phone || '',
                address: initialData.address || '',
            });
        } else if (show) {
            // reset if adding new
            setFormData({
                name: '',
                email: '',
                phone: '',
                address: '',
            });
        }
    }, [show, initialData]);

    const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const { name, value } = e.target;
        setFormData(prev => ({ ...prev, [name]: value }));
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        onSubmit(formData);
        setFormData({ name: '', email: '', phone: '', address: '' });
    };

    if (!show) return null;

    return (
        <Modal onClose={onClose}>
            <form onSubmit={handleSubmit} className="p-6 space-y-4">
                <h2 className="text-xl font-bold">
                    {initialData ? "Edit Resident" : "Add New Resident"}
                </h2>

                <Input
                    name="name"
                    placeholder="Full Name"
                    value={formData.name}
                    onChange={handleChange}
                    required
                />
                <Input
                    name="email"
                    type="email"
                    placeholder="Email"
                    value={formData.email}
                    onChange={handleChange}
                    required
                />
                <Input
                    name="phone"
                    placeholder="Phone"
                    value={formData.phone}
                    onChange={handleChange}
                    required
                />
                <Input
                    name="address"
                    placeholder="Address"
                    value={formData.address}
                    onChange={handleChange}
                />

                <div className="flex justify-end gap-2">
                    <Button type="button" variant="outline" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button type="submit">Save Resident</Button>
                </div>
            </form>
        </Modal>
    );
}
