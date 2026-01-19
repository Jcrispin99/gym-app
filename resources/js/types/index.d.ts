import { InertiaLinkProps } from '@inertiajs/vue3';
import type { LucideIcon } from 'lucide-vue-next';

export interface Auth {
    user: User;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavItem {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon;
    isActive?: boolean;
}

export type SidebarItemType = 'header' | 'link' | 'group';

export interface SidebarHeaderItem {
    type: 'header';
    title: string;
}

export interface SidebarLinkItem {
    type: 'link';
    title: string;
    icon?: string | null;
    href: string;
    isActive: boolean;
}

export interface SidebarGroupItem {
    type: 'group';
    title: string;
    icon?: string | null;
    isActive: boolean;
    items: SidebarLinkItem[];
}

export type SidebarItem = SidebarHeaderItem | SidebarLinkItem | SidebarGroupItem;

export type AppPageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    sidebarOpen: boolean;
    sidebar: SidebarItem[];
};

export interface User {
    id: number;
    name: string;
    email: string;
    user_type?: 'staff' | 'customer' | 'provider';
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
}

export type BreadcrumbItemType = BreadcrumbItem;
