import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import type { SharedData } from '@/types';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { BookOpen, DollarSign, FileChartColumnIncreasing, Folder, Gauge, LayoutGrid, Receipt, ScanBarcode, Users, Wallet, PlusCircle } from 'lucide-react';
import AppLogo from './app-logo';

const footerNavItems: NavItem[] = [
    // {
    //     title: 'Repository',
    //     url: 'https://github.com/laravel/react-starter-kit',
    //     icon: Folder,
    // },
    // {
    //     title: 'Documentation',
    //     url: 'https://laravel.com/docs/starter-kits',
    //     icon: BookOpen,
    // },
];

export function AppSidebar() {
    const { auth } = usePage<SharedData>().props;

    if (!auth.user) {
        return null;
    }

    const mainNavItems: NavItem[] = [
        {
            title: 'Dashboard',
            url: route('dashboard'),
            icon: LayoutGrid,
        },
    ];

    if (auth.can?.resident?.viewAny) {
        mainNavItems.push({
            title: 'Residents',
            url: route('residents.index'),
            icon: Users,
        });
    }

    // Add Meters if user can view
    if (auth.can?.meter?.viewAny) {
        mainNavItems.push({
            title: 'Meters',
            url: route('meters.index'),
            icon: ScanBarcode,
        });
    }

    // Add Meter Readings if user can view meters (typically same permission)
    if (auth.can?.meter?.viewAny) {
        mainNavItems.push({
            title: 'Meter Readings',
            url: route('meter-readings.index'),
            icon: Gauge,
        });
    }

    // Add Bills if user can view
    if (auth.can?.billing?.viewAny) {
        mainNavItems.push({
            title: 'Billing',
            url: route('billings.index'),
            icon: Receipt,
            items: [
                {
                    title: 'Bills',
                    url: route('billings.index'),
                    icon: Receipt,
                },
                {
                    title: 'Generate Bills',
                    url: route('billings.create'),
                    icon: PlusCircle,
                },
            ],
        });
    }

    // Add Payments if user can view
    if (auth.can?.payment?.viewAny) {
        mainNavItems.push({
            title: 'Payments',
            url: route('payments.index'),
            icon: Wallet,
        });
    }

    // Add Expenses if user can view
    // if (auth.can?.expense?.viewAny) {
    //     mainNavItems.push({
    //         title: 'Expenses',
    //         url: route('expenses.index'),
    //         icon: DollarSign,
    //     });
    // }

    // Add Reports (typically available to all authenticated users)
    mainNavItems.push({
        title: 'Reports',
        url: route('reports.index'),
        icon: FileChartColumnIncreasing,
    });

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={route('dashboard')} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
