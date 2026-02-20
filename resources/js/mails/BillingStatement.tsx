import React from 'react';
import { formatCurrency } from '@/lib/utils';

interface BillingStatementProps {
    resident: {
        name: string;
        email?: string;
        phone?: string;
    };
    meter: {
        meter_number: string;
        meter_name: string;
        location?: string;
    };
    billing: {
        id: number;
        billing_period: string;
        total_amount: number;
        status: string;
        issued_at: string;
        due_date: string;
        paid_amount?: number;
        balance?: number;
    };
    details: {
        previous_reading: number;
        current_reading: number;
        units: number;
        rate: number;
        amount: number;
    }[];
    total_billed?: number;
    total_paid?: number;
    balance_due?: number;
}

export default function BillingStatement({ 
    resident, 
    meter, 
    billing, 
    details,
    total_billed,
    total_paid,
    balance_due 
}: BillingStatementProps) {
    return (
        <div style={{ fontFamily: 'Arial, sans-serif', maxWidth: '600px', margin: '0 auto' }}>
            <h1 style={{ color: '#1f2937', borderBottom: '2px solid #3b82f6', paddingBottom: '10px' }}>
                Water Billing Statement
            </h1>
            
            <div style={{ marginTop: '20px' }}>
                <h2 style={{ fontSize: '16px', color: '#374151' }}>Customer Information</h2>
                <p><strong>Name:</strong> {resident.name}</p>
                {resident.email && <p><strong>Email:</strong> {resident.email}</p>}
                {resident.phone && <p><strong>Phone:</strong> {resident.phone}</p>}
            </div>

            <div style={{ marginTop: '20px' }}>
                <h2 style={{ fontSize: '16px', color: '#374151' }}>Meter Information</h2>
                <p><strong>Meter Number:</strong> {meter.meter_number}</p>
                <p><strong>Meter Name:</strong> {meter.meter_name}</p>
                {meter.location && <p><strong>Location:</strong> {meter.location}</p>}
            </div>

            <div style={{ marginTop: '20px' }}>
                <h2 style={{ fontSize: '16px', color: '#374151' }}>Billing Details</h2>
                <p><strong>Billing Period:</strong> {billing.billing_period}</p>
                <p><strong>Issue Date:</strong> {new Date(billing.issued_at).toLocaleDateString()}</p>
                <p><strong>Due Date:</strong> {new Date(billing.due_date).toLocaleDateString()}</p>
            </div>

            {details && details.length > 0 && (
                <div style={{ marginTop: '20px' }}>
                    <h2 style={{ fontSize: '16px', color: '#374151' }}>Meter Readings</h2>
                    <table style={{ width: '100%', borderCollapse: 'collapse' }}>
                        <thead>
                            <tr style={{ backgroundColor: '#f3f4f6' }}>
                                <th style={{ padding: '8px', textAlign: 'left', border: '1px solid #d1d5db' }}>Previous</th>
                                <th style={{ padding: '8px', textAlign: 'left', border: '1px solid #d1d5db' }}>Current</th>
                                <th style={{ padding: '8px', textAlign: 'left', border: '1px solid #d1d5db' }}>Units</th>
                                <th style={{ padding: '8px', textAlign: 'right', border: '1px solid #d1d5db' }}>Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            {details.map((detail, index) => (
                                <tr key={index}>
                                    <td style={{ padding: '8px', border: '1px solid #d1d5db' }}>{detail.previous_reading}</td>
                                    <td style={{ padding: '8px', border: '1px solid #d1d5db' }}>{detail.current_reading}</td>
                                    <td style={{ padding: '8px', border: '1px solid #d1d5db' }}>{detail.units}</td>
                                    <td style={{ padding: '8px', textAlign: 'right', border: '1px solid #d1d5db' }}>
                                        {formatCurrency(detail.amount)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            <div style={{ 
                marginTop: '30px', 
                padding: '15px', 
                backgroundColor: '#eff6ff', 
                borderRadius: '8px',
                border: '1px solid #3b82f6'
            }}>
                <h2 style={{ fontSize: '18px', color: '#1f2937', marginTop: 0 }}>Amount Summary</h2>
                <p style={{ fontSize: '20px', margin: '10px 0' }}>
                    <strong>Total Amount:</strong> {formatCurrency(billing.total_amount)}
                </p>
                
                {billing.paid_amount && billing.paid_amount > 0 && (
                    <p style={{ fontSize: '16px', color: '#059669', margin: '10px 0' }}>
                        <strong>Amount Paid:</strong> {formatCurrency(billing.paid_amount)}
                    </p>
                )}
                
                <p style={{ 
                    fontSize: '22px', 
                    margin: '10px 0', 
                    color: '#dc2626',
                    fontWeight: 'bold'
                }}>
                    <strong>Balance Due:</strong> {formatCurrency(billing.balance || billing.total_amount)}
                </p>
            </div>

            {balance_due !== undefined && (
                <div style={{ marginTop: '20px', padding: '10px', backgroundColor: '#fef2f2', borderRadius: '4px' }}>
                    <p><strong>Account Balance:</strong> {formatCurrency(balance_due)}</p>
                    {total_billed !== undefined && <p><strong>Total Billed:</strong> {formatCurrency(total_billed)}</p>}
                    {total_paid !== undefined && <p><strong>Total Paid:</strong> {formatCurrency(total_paid)}</p>}
                </div>
            )}

            <div style={{ marginTop: '30px', paddingTop: '20px', borderTop: '1px solid #d1d5db' }}>
                <p style={{ color: '#6b7280', fontSize: '14px' }}>
                    Thank you for your business!
                </p>
                <p style={{ color: '#6b7280', fontSize: '14px', marginTop: '10px' }}>
                    <strong>Sychar Suppliers</strong><br />
                    For inquiries, please contact us.
                </p>
            </div>
        </div>
    );
}