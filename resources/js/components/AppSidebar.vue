<script setup lang="ts">
import CompanySwitcher from '@/components/CompanySwitcher.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/vue3';
import { BookOpen, Folder, LayoutGrid, ClipboardCheck } from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';

// Route helper for companies (matches dashboard pattern)
const companies = () => ({ url: '/companies', method: 'get' as const });
const users = () => ({ url: '/users', method: 'get' as const });
const members = () => ({ url: '/members', method: 'get' as const });
const membershipPlans = () => ({ url: '/membership-plans', method: 'get' as const });
const attendances = () => ({ url: '/attendances', method: 'get' as const });

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Companies',
        href: companies(),
        icon: LayoutGrid,
    },
    {
        title: 'Users',
        href: users(),
        icon: LayoutGrid,
    },
    {
        title: 'Members',
        href: members(),
        icon: LayoutGrid,
    },
    {
        title: 'Membership Plans',
        href: membershipPlans(),
        icon: LayoutGrid,
    },
    {
        title: 'Asistencias',
        href: attendances(),
        icon: ClipboardCheck,
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Github Repo',
        href: 'https://github.com/laravel/vue-starter-kit',
        icon: Folder,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#vue',
        icon: BookOpen,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <CompanySwitcher />
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
