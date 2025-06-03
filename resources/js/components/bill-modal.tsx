import { X } from "lucide-react";
import { formatCurrency } from '@/lib/utils';

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
            <div className="bg-white rounded-lg shadow-lg p-6 w-full max-w-xl">
                <button
                    className="absolute top-2 right-2 text-white hover:text-gray-500"
                    onClick={onClose}
                >
                    <X />
                    
                </button>
                <div className="max-w-2xl mx-auto p-6 bg-white border border-gray-200 rounded-lg shadow-md">
                    {/* Header */}
                    <div className="text-center mb-8">
                        <h1 className="text-3xl font-bold text-sky-950">Sychar Suppliers</h1>
                        <p className="text-sm text-sky-950">(+254)0772059705 | sales@sycharsuppliers.com</p>
                    </div>
                
                    {/* Bill Info */}
                    <div className="mb-6">
                        <h2 className="text-xl font-semibold">WATER BILL</h2>
                        <div className="text-sm text-gray-600 mt-2">
                            <p><strong>Invoice to:</strong> {residentName}</p>
                            <p>{residentEmail}</p>
                        </div>
                        <div className="mt-4">
                            <p><strong>Bill Number:</strong> {billNumber}</p>
                            <p><strong>Billing Date:</strong> {billingDate}</p>
                            <p><strong>Meter:</strong> {meterName}</p>
                        </div>
                    </div>
                
                    {/* Readings Table */}
                    <table className="w-full text-sm text-left border-t border-b border-sky-600">
                        <thead>
                            <tr className="bg-sky-100">
                                <th className="px-4 py-2">Previous Reading</th>
                                <th className="px-4 py-2">Current Reading</th>
                                <th className="px-4 py-2">Units</th>
                                <th className="px-4 py-2">Price/Unit</th>
                                <th className="px-4 py-2">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td className="px-4 py-2">{previousReading}</td>
                                <td className="px-4 py-2">{currentReading}</td>
                                <td className="px-4 py-2">{units}</td>
                                <td className="px-4 py-2">{formatCurrency(pricePerUnit)}</td>
                                <td className="px-4 py-2">{formatCurrency(total)}</td>
                            </tr>
                        </tbody>
                    </table>
                
                    {/* Payments and Due Amount */}
                    <div className="mt-6 text-sm">
                        <p><strong>Paid:</strong>{formatCurrency(paid)}</p>
                        <p><strong>Due:</strong>{formatCurrency(due)}</p>
                    </div>
                
                    {/* Footer */}
                    <div className="mt-8 text-xs text-gray-600">
                        <p>All cheques payable to Sychar Suppliers.</p>
                        <p>Direct Deposit to NCBA Bank: ACCOUNT NUMBER: 1001821276, GALLERIA BRANCH.</p>
                        <p>PayBill No: 880100 | Account: 1001821276.</p>
                        <p>Water supply may be discontinued if payment is not made.</p>
                    </div>
                </div>

                {children}
            </div>
        </div>
    );
};

export default BillModal;