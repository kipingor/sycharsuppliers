import { LucideIcon } from 'lucide-react';
import type { Config } from 'ziggy-js';

export interface PermissionSet {
    viewAny?: boolean;
    view?: boolean;
    create?: boolean;
    update?: boolean;
    delete?: boolean;
    restore?: boolean;
    forceDelete?: boolean;
    generate?: boolean;
    void?: boolean;
    rebill?: boolean;
    reconcile?: boolean;
}

export interface Auth {
    user: User;
    can?: {
        billing?: PermissionSet;
        meter?: PermissionSet;
        payment?: PermissionSet;
        resident?: PermissionSet;
        expense?: PermissionSet;
        employee?: PermissionSet;
    };
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavGroup {
    title: string;
    items: NavItem[];
}

export interface NavItem {
    title: string;
    url: string;
    icon?: LucideIcon | null;
    isActive?: boolean;
    items?: NavItem[];
}

export interface FlashMessages {
    success?: string;
    error?: string;
    warning?: string;
    info?: string;
}

export interface SharedData {
    name: string;
    auth: Auth;
    flash?: FlashMessages;
    ziggy: Config & { location: string };
    quote: { message: string; author: string };
    errors?: Record<string, string | string[]>;
    [key: string]: unknown;
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown; // This allows for additional properties...
}

export interface PageProps {
    auth: {
        user: {
            id: number;
            name: string;
            email: string;
        };
    };
}
