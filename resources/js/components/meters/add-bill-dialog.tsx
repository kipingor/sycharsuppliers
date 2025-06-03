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
import { useEffect, useState } from "react";
import { useForm } from 'laravel-precognition-react-inertia';
import { formatCurrency } from '@/lib/utils';

interface AddBillDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    meter: {
        id: number;
        meter_number: string;
        meter_name: string;
        resident?: {
            name: string;
            email?: string;
        };
    };
}

export function AddBillDialog({ open, onOpenChange, meter }: AddBillDialogProps) {
    const [fetchedPreviousReading, setFetchedPreviousReading] = useState<number | null>(null);
    const [calculatedAmountDue, setCalculatedAmountDue] = useState<number>(0);
    const [isLoadingPreviousReading, setIsLoadingPreviousReading] = useState<boolean>(false);
    const [errorPreviousReading, setErrorPreviousReading] = useState<string | null>(null);

    const { data, setData, post, processing, errors, reset, setError, clearErrors } = useForm('post', '/billing', {
        meter_id: meter.id,
        reading_value: 0,
        price_per_unit: 300,
    });

    useEffect(() => {
        if (open) {
            // Reset form state when dialog opens
            setData({
                meter_id: meter.id,
                reading_value: 0,
                price_per_unit: 300,
            });
            setFetchedPreviousReading(null);
            setCalculatedAmountDue(0);
            setIsLoadingPreviousReading(true);
            setErrorPreviousReading(null);
            clearErrors();

            fetch(`/api/meter-readings/last/${meter.id}`, {
                method: 'GET',
                credentials: 'include',
                headers: { 'Accept': 'application/json' },
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Failed to fetch last reading');
                }
                return response.json();
            })
            .then(apiData => {
                setFetchedPreviousReading(parseFloat(apiData?.reading_value || '0'));
            })
            .catch(err => {
                console.error('Failed to fetch last reading:', err);
                setErrorPreviousReading('Could not load previous reading.');
                setFetchedPreviousReading(0); // Fallback or handle as error
            })
            .finally(() => {
                setIsLoadingPreviousReading(false);
            });
        }
    // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, meter.id]);

    useEffect(() => {
        if (fetchedPreviousReading !== null && data.reading_value >= fetchedPreviousReading) {
            const units = data.reading_value - fetchedPreviousReading;
            setCalculatedAmountDue(units * data.price_per_unit);
        } else if (fetchedPreviousReading !== null && data.reading_value < fetchedPreviousReading) {
            setCalculatedAmountDue(0); // Or indicate an error state for amount due
        } else {
            setCalculatedAmountDue(0);
        }
    }, [data.reading_value, data.price_per_unit, fetchedPreviousReading]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        clearErrors('reading_value'); 

        if (isLoadingPreviousReading) return; // Prevent submission while loading

        if (fetchedPreviousReading === null && !errorPreviousReading) {
             setError('reading_value', 'Previous reading is still loading or failed to load.');
            return;
        }
        
        if (errorPreviousReading){
            setError('reading_value', 'Cannot submit bill due to an error fetching previous reading.');
            return;
        }

        if (fetchedPreviousReading !== null && data.reading_value < fetchedPreviousReading) {
            setError('reading_value', 'Current reading cannot be less than previous reading.');
            return;
        }

        post('/billing', {
            onSuccess: () => {
                onOpenChange(false);
                // Form fields will be reset by the useEffect hook when `open` becomes true again
            },
            // onError will automatically populate `form.errors`
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-[525px]">
                <form onSubmit={handleSubmit}>
                    <DialogHeader>
                        <DialogTitle>Add New Bill</DialogTitle>
                        <DialogDescription>
                            Create a new bill for meter {meter.meter_number} ({meter.meter_name})
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-4 py-4">
                        <div className="grid grid-cols-4 items-center gap-4">
                            <Label htmlFor="meterDisplay" className="text-right">
                                Meter
                            </Label>
                            <Input
                                id="meterDisplay"
                                value={`${meter.meter_number} - ${meter.resident?.name || 'N/A'}`}
                                className="col-span-3"
                                disabled
                            />
                        </div>
                        
                        {isLoadingPreviousReading && <p className="col-span-4 text-center">Loading previous reading...</p>}
                        {errorPreviousReading && <p className="col-span-4 text-center text-red-500">{errorPreviousReading}</p>}

                        {fetchedPreviousReading !== null && !isLoadingPreviousReading && !errorPreviousReading && (
                            <div className="grid grid-cols-4 items-center gap-4">
                                <Label htmlFor="previousReadingDisplay" className="text-right">
                                    Previous Reading
                                </Label>
                                <Input
                                    id="previousReadingDisplay"
                                    type="number"
                                    value={fetchedPreviousReading}
                                    className="col-span-3"
                                    disabled
                                />
                            </div>
                        )}

                        <div className="grid grid-cols-4 items-center gap-4">
                            <Label htmlFor="currentReading" className="text-right">
                                Current Reading
                            </Label>
                            <Input
                                id="currentReading"
                                type="number"
                                value={data.reading_value}
                                onChange={(e) => setData('reading_value', Number(e.target.value))}
                                className="col-span-3"
                                required
                                disabled={isLoadingPreviousReading || errorPreviousReading !== null}
                            />
                        </div>
                        {errors.reading_value && <div className="col-span-4 text-red-500 text-sm text-right">{errors.reading_value}</div>}
                        
                        <div className="grid grid-cols-4 items-center gap-4">
                            <Label htmlFor="pricePerUnit" className="text-right">
                                Price per Unit
                            </Label>
                            <Input
                                id="pricePerUnit"
                                type="number"
                                value={data.price_per_unit}
                                onChange={(e) => setData('price_per_unit', Number(e.target.value))}
                                className="col-span-3"
                                required
                                disabled={isLoadingPreviousReading || errorPreviousReading !== null}
                            />
                        </div>
                        {errors.price_per_unit && <div className="col-span-4 text-red-500 text-sm text-right">{errors.price_per_unit}</div>}

                        <div className="grid grid-cols-4 items-center gap-4">
                            <Label htmlFor="total" className="text-right">
                                Total Amount
                            </Label>
                            <Input
                                id="total"
                                value={formatCurrency(calculatedAmountDue)}
                                className="col-span-3"
                                disabled
                            />
                        </div>
                         {errors.meter_id && <div className="col-span-4 text-red-500 text-sm text-right">{errors.meter_id}</div>}
                    </div>
                    <DialogFooter>
                        <Button 
                            type="submit" 
                            disabled={processing || isLoadingPreviousReading || errorPreviousReading !== null || (fetchedPreviousReading !== null && data.reading_value < fetchedPreviousReading)}
                        >
                            {processing ? "Adding Bill..." : "Add Bill"}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
} 