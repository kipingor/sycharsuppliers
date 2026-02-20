import { X } from "lucide-react";
import BillPresentation from './bill-presentation';

interface BillModalProps {
    onClose: () => void;
    children: React.ReactNode;
    residentName: string;
    residentEmail: string;
    billNumber: string;
    billingDate: string;
    meterName: string;
    previousReading: number;
    currentReading: number;
    units: number;
    pricePerUnit: number;
    total: number;
    paid: number;
    due: number;
}

const BillModal: React.FC<BillModalProps> = ({ 
    onClose, 
    children,
    residentName,
    residentEmail,
    billNumber,
    billingDate,
    meterName,
    previousReading,
    currentReading,
    units,
    pricePerUnit,
    total,
    paid,
    due,
}) => {
    return (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div className="bg-white rounded-lg shadow-lg p-6 w-full max-w-2xl relative">
                <button
                    className="absolute top-4 right-4 text-gray-500 hover:text-gray-700"
                    onClick={onClose}
                    title="Close modal"
                >
                    <X size={24} />
                </button>
                
                <div className="overflow-y-auto max-h-[90vh]">
                    <BillPresentation
                        residentName={residentName}
                        residentEmail={residentEmail}
                        billNumber={billNumber}
                        billingDate={billingDate}
                        meterName={meterName}
                        previousReading={previousReading}
                        currentReading={currentReading}
                        units={units}
                        pricePerUnit={pricePerUnit}
                        total={total}
                        paid={paid}
                        due={due}
                    />
                    {children}
                </div>
            </div>
        </div>
    );
};

export default BillModal;