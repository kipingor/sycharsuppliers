import React from 'react';
import BillPresentation from './bill-presentation';

interface BillProps {
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

const Bill: React.FC<BillProps> = ({
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
  );
};

export default Bill;
