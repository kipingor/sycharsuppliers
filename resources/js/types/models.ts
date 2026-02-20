/**
 * Shared TypeScript models for the Water Billing System
 * These interfaces are used across multiple components and pages
 */

export interface Resident {
    id: number;
    name: string;
    email?: string;
    phone?: string;
}

export interface Account {
    id: number;
    name: string;
    email?: string;
    phone?: string;
    address?: string;
    account_number?: string;
    status?: 'active' | 'inactive' | 'closed';
    activated_at?: Date;
    suspended_at?: Date;
}

export interface Meter {
    id: number;
    meter_name: string;
    meter_number: string;
    location?: string;
    status?: 'active' | 'inactive' | 'replaced' | 'faulty';
    total_units?: number;
    total_billed?: number;
    total_paid?: number;
    balance_due?: number;    
    installed_at?: Date;
    tariff_id?: number;
    account_id?: number;    
    account?: Account;    
    type?: string;
    meter_type?: string;
    parent_meter_id?: number;
    allocation_percentage?: string;
}

export interface BillingDetail {
    id: number;
    billing_id: number;
    previous_reading: number;
    current_reading: number;
    units_used: number;
    rate: number;
    amount: number;
}

export interface Bill {
    id: number;
    account_id?: number;
    meter_id?: number;
    billing_period: string;
    total_amount: number;
    amount_paid?: number;
    amount_due?: number;
    status: 'pending' | 'paid' | 'overdue' | 'partially_paid' | 'voided';
    account?: Account;
    meter?: Meter;
    details?: BillingDetail[];
    created_at?: string;
    updated_at?: string;
    // Legacy fields for backward compatibility
    previous_reading?: number;
    current_reading?: number;
}

export interface Payment {
    id: number;
    account_id: number;
    billing_id?: number;
    amount: number;
    payment_date: string;
    method: 'Cash' | 'Bank Transfer' | 'M-Pesa' | 'Card' | 'Cheque';
    reference?: string;
    transaction_id?: string;
    status: 'pending' | 'completed' | 'failed' | 'reversed';
    
    // Reconciliation fields (MISSING)
    reconciliation_status?: 'pending' | 'reconciled' | 'partially_reconciled';
    reconciled_at?: string;
    reconciled_by?: number;
    
    account?: Account;
    allocations?: PaymentAllocation[];
}

export interface PaymentAllocation {
    id: number;
    payment_id: number;
    billing_id: number;
    allocated_amount: number;
    allocation_date: string;
    notes?: string;
    payment?: Payment;
    billing?: Bill;
}

export interface CarryForwardBalance {
    id: number;
    account_id: number;
    balance: number;
    balance_type: 'debit' | 'credit';
    effective_date: string;
    account?: Account;
}

export interface Statement {
    meter: Meter;
    totalDue: number;
    totalPaid: number;
    balance: number;
}

export interface Tariff {
    id: number;
    name: string;
    rate: number;
    unit: string;
    effective_date: string;
}
