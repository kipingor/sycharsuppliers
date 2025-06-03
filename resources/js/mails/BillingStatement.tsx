import React from 'react';
import { formatCurrency } from '@/lib/utils';

export default function BillingStatement({ resident, meter, billing, details }) {
  return (
    <div>
      <h1>Water Billing Statement</h1>
      <p><strong>Resident:</strong> {resident.name}</p>
      <p><strong>Meter Number:</strong> {meter.meter_number}</p>
      <p><strong>Meter Name:</strong> {meter.meter_name}</p>
      <p><strong>Amount Due:</strong> {formatCurrency(billing.amount_due)}</p>

      <h3>Meter Reading</h3>
      <p><strong>Previous:</strong> {details.previous_reading_value}</p>
      <p><strong>Current:</strong> {details.current_reading_value}</p>
      <p><strong>Units Used:</strong> {details.units_used}</p>

      <p>Thanks,<br />Sychar Suppliers</p>
    </div>
  );
}
