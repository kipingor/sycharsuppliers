import { SidebarGroup, SidebarGroupLabel, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/react';

export function NavMain({ items = [] }: { items: NavItem[] }) {
    const page = usePage();
    
    // Helper to determine if nav item is active
    const isActive = (itemUrl: string): boolean => {
        const currentPath = page.url;
        
        // Exact match
        if (currentPath === itemUrl) {
            return true;
        }
        
        // Check if current path starts with item url (for nested routes)
        // e.g., /billings/1 should activate /billings nav item
        if (itemUrl !== '/' && itemUrl !== '/dashboard') {
            return currentPath.startsWith(itemUrl);
        }
        
        return false;
    };
    
    return (
        <SidebarGroup className="px-2 py-0">
            <SidebarGroupLabel>Platform</SidebarGroupLabel>
            <SidebarMenu>
                {items.map((item) => (
                    <SidebarMenuItem key={item.title}>
                        <SidebarMenuButton asChild isActive={isActive(item.url)}>
                            <Link href={item.url} prefetch>
                                {item.icon && <item.icon />}
                                <span>{item.title}</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                ))}
            </SidebarMenu>
        </SidebarGroup>
    );
}