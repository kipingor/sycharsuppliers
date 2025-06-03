import AppLayout from '@/layouts/app-layout';
import { Head } from '@inertiajs/react';
import { type BreadcrumbItem } from '@/types';

interface Bill {
    id: number;
    meter_id: number;
    amount_due: number;
    status: 'pending' | 'paid' | 'unpaid' | 'overdue' | 'partially paid' | 'void';
    meter: {
        resident?: {
            name: string;
        };
    };
    details?: {
        current_reading_value: number;
    };
}

interface BillProps {
  bill: {
      data:bill[];
      links: any[];
  };
}

const breadcrumbs: BreadcrumbItem[] = [
    {
      title: 'Water Bill',
      href: '/billing/${bill}',
    },
  ];

export default function Bill({ bill }) {
  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="Water Bill" />
      <div className="mx-auto max-w-6xl">
      {bill.meter.resident?.name || 'N/A'}
      </div>      
    </AppLayout>
  )
}