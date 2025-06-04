import { useEffect, useState } from "react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import Modal from "@/components/ui/modal";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";

// Dummy resident type (replace with actual from your backend if available)
interface Resident {
    id: number;
    name: string;
}

interface Meter {
    id: number;
    meter_name: string;
    meter_number: string;
    location: string;
    status: 'active' | 'inactive' | 'replaced';
    installation_date: string;
    resident_id?: number;
    resident?: {
        name: string;
    }
}

interface MeterModalProps {
    show: boolean;
    onClose: () => void;
    onSubmit: (formData: FormData) => void;
    editMeter: Meter | null;
    residents: {
        data: Resident[];
    } | Resident[]; // Handle both possible structures
    onAddResident: () => void; // Function to open "Add Resident" modal
}

export default function MeterModal({
    show,
    onClose,
    onSubmit,
    editMeter,
    residents,
    onAddResident
}: MeterModalProps) {
    const [meterNumber, setMeterNumber] = useState("");
    const [meterName, setMeterName] = useState("");
    const [location, setLocation] = useState("");
    const [status, setStatus] = useState<'active' | 'inactive' | 'replaced'>("active");
    const [installationDate, setInstallationDate] = useState("");
    const [residentId, setResidentId] = useState<string>("");

    const residentsData = Array.isArray(residents) ? residents : residents?.data || [];

    useEffect(() => {
        if (!editMeter) {
            const randomMeter = Math.floor(10000000 + Math.random() * 90000000).toString();
            setMeterNumber(randomMeter);
            setMeterName("");
            setLocation("");
            setStatus("active");
            setInstallationDate("");
            setResidentId("");
        } else {
            setMeterNumber(editMeter.meter_number || "");
            setMeterName(editMeter.meter_name || "");
            setLocation(editMeter.location || "");
            setStatus(editMeter.status || "active");
            setInstallationDate(editMeter.installation_date || "");
            setResidentId(editMeter.resident_id?.toString() || "");
        }
    }, [editMeter, show]);

    if (!show) return null;

    return (
        <Modal onClose={onClose}>
            <form
                onSubmit={(e) => {
                    e.preventDefault();

                    const formData = new FormData();
                    formData.append("meter_number", meterNumber);
                    formData.append("meter_name", meterName);
                    formData.append("location", location);
                    formData.append("status", status);
                    formData.append("installation_date", installationDate);
                    if (residentId && residentId !== "") {
                        formData.append("resident_id", residentId);
                    }

                    // Debug logging
                    console.log('Submitting form with data:', {
                        meter_number: meterNumber,
                        meter_name: meterName,
                        location: location,
                        status: status,
                        installation_date: installationDate,
                        resident_id: residentId,
                        isEdit: !!editMeter,
                        editMeterId: editMeter?.id
                    });

                    onSubmit(formData); // You may refine the typing if needed
                }}
                className="p-6 space-y-4"
            >
                <h2 className="text-xl font-bold">
                    {editMeter ? "Edit Meter" : "Add Meter"}
                </h2>

                {/* Select Resident */}
                <div>                    
                    {editMeter ? (
                        <span>{editMeter?.resident?.name || 'No Resident Assigned'}</span>
                    ) : (
                        <>
                            <label className="block mb-1 font-medium">Select Resident</label>
                            <div className="flex items-center gap-2">
                                <Select 
                                    value={residentId} 
                                    onValueChange={(value) => setResidentId(value || "")}
                                >
                                    <SelectTrigger className="w-full">
                                        <SelectValue placeholder="Select Resident" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="No Resident">No Resident</SelectItem>
                                        {residentsData.map((resident) => (
                                            <SelectItem key={resident.id} value={resident.id.toString()}>
                                                {resident.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <Button type="button" onClick={onAddResident} variant="outline">
                                    + Add
                                </Button>
                            </div>
                        </>
                    )}                        
                    
                </div>

                {/* Meter Number */}
                <div>
                    <label className="block mb-1 font-medium">Meter Number</label>
                    <Input
                        type="text"
                        name="meter_number"
                        placeholder="Meter Number"
                        value={meterNumber}
                        onChange={(e) => setMeterNumber(e.target.value)}
                        required
                    />
                </div>

                {/* Meter Name */}
                <div>
                    <label className="block mb-1 font-medium">Meter Name</label>
                    <Input
                        type="text"
                        name="meter_name"
                        placeholder="Meter Name"
                        value={meterName}
                        onChange={(e) => setMeterName(e.target.value)}
                        required
                    />
                </div>

                {/* Location */}
                <div>
                    <label className="block mb-1 font-medium">Location</label>
                    <Input
                        type="text"
                        name="location"
                        placeholder="Location"
                        value={location}
                        onChange={(e) => setLocation(e.target.value)}
                        required
                    />
                </div>

                {/* Installation Date */}
                <div>
                    <label className="block mb-1 font-medium">Installation Date</label>
                    <Input
                        type="date"
                        name="installation_date"
                        value={installationDate}
                        onChange={(e) => setInstallationDate(e.target.value)}
                        required
                    />
                </div>

                {/* Status */}
                <div>
                    <label className="block mb-1 font-medium">Status</label>
                    <Select value={status} onValueChange={(value) => setStatus(value as 'active' | 'inactive' | 'replaced')}>
                        <SelectTrigger className="w-full">
                            <SelectValue placeholder="Select Status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="active">Active</SelectItem>
                            <SelectItem value="inactive">Inactive</SelectItem>
                            <SelectItem value="replaced">Replaced</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                {/* Action Buttons */}
                <div className="flex justify-end gap-2">
                    <Button type="button" variant="outline" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button type="submit">Save</Button>
                </div>
            </form>
        </Modal>
    );
}
