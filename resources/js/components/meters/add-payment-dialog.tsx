import { Button } from "@/components/ui/button";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { useState } from "react";
import { router } from "@inertiajs/react";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import { toast } from "sonner";
import type { Meter } from "@/types/models";

interface AddPaymentDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    meter: Meter;
}

export function AddPaymentDialog({ open, onOpenChange, meter }: AddPaymentDialogProps) {
    const [amount, setAmount] = useState<number>(0);
    const [method, setMethod] = useState<string>("M-Pesa");
    const [transactionId, setTransactionId] = useState<string>("");
    const [paymentDate, setPaymentDate] = useState<string>(new Date().toISOString().split('T')[0]);

    const generateCashTransactionId = () => {
        const timestamp = new Date().getTime();
        const random = Math.random().toString(36).substring(2, 8).toUpperCase();
        return `CASH-${timestamp}-${random}`;
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        
        const formData = {
            meter_id: meter.id,
            amount,
            method,
            transaction_id: transactionId,
            payment_date: paymentDate,
            status: 'completed'
        };

        router.post('/payments', formData, {
            onSuccess: () => {
                onOpenChange(false);
                // Reset form
                setAmount(0);
                setTransactionId("");
                setPaymentDate(new Date().toISOString().split('T')[0]);
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-[525px]">
                <form onSubmit={handleSubmit}>
                    <DialogHeader>
                        <DialogTitle>Record Payment</DialogTitle>
                        <DialogDescription>
                            Record a payment for meter {meter.meter_number}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-4 py-4">
                        <div className="grid grid-cols-4 items-center gap-4">
                            <Label htmlFor="meter" className="text-right">
                                Meter
                            </Label>
                            <Input
                                id="meter"
                                value={meter.meter_number}
                                className="col-span-3"
                                disabled
                            />
                        </div>
                        <div className="grid grid-cols-4 items-center gap-4">
                            <Label htmlFor="resident" className="text-right">
                                Account
                            </Label>
                            <Input
                                id="account"
                                value={meter.account?.name || 'N/A'}
                                className="col-span-3"
                                disabled
                            />
                        </div>
                        <div className="grid grid-cols-4 items-center gap-4">
                            <Label htmlFor="amount" className="text-right">
                                Amount
                            </Label>
                            <Input
                                id="amount"
                                type="number"
                                value={amount}
                                onChange={(e) => setAmount(Number(e.target.value))}
                                className="col-span-3"
                                required
                            />
                        </div>
                        <div className="grid grid-cols-4 items-center gap-4">
                            <Label htmlFor="method" className="text-right">
                                Method
                            </Label>
                            <Select value={method} onValueChange={(value) => {
                                setMethod(value);
                                if (value === 'Cash') {
                                    setTransactionId(generateCashTransactionId());
                                } 
                            }}>
                                <SelectTrigger className="col-span-3">
                                    <SelectValue placeholder="Select payment method" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="M-Pesa">M-Pesa</SelectItem>
                                    <SelectItem value="Bank Transfer">Bank Transfer</SelectItem>
                                    <SelectItem value="Cash">Cash</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="grid grid-cols-4 items-center gap-4">
                            <Label htmlFor="transactionId" className="text-right">
                                Transaction ID
                            </Label>
                            <Input
                                id="transactionId"
                                value={transactionId}
                                onChange={(e) => setTransactionId(e.target.value)}
                                className="col-span-3"
                                required
                            />
                        </div>
                        <div className="grid grid-cols-4 items-center gap-4">
                            <Label htmlFor="paymentDate" className="text-right">
                                Payment Date
                            </Label>
                            <Input
                                id="paymentDate"
                                type="date"
                                value={paymentDate}
                                onChange={(e) => setPaymentDate(e.target.value)}
                                className="col-span-3"
                                required
                            />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button type="submit">Record Payment</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
} 