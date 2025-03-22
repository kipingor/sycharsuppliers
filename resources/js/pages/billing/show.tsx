import AppLayout from '@/layouts/app-layout';
import { Head } from '@inertiajs/react';
import { type BreadcrumbItem } from '@/types';

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

      </div>      
    </AppLayout>
  )
}