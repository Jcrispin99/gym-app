<script setup lang="ts">
import CompanySwitcher from '@/components/CompanySwitcher.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { urlIsActive } from '@/lib/utils';
import { dashboard } from '@/routes';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import {
    BookOpen,
    Building2,
    ChevronDown,
    ClipboardCheck,
    CreditCard,
    Dumbbell,
    Folder,
    LayoutDashboard,
    Package,
    PackageOpen,
    Settings,
    Tags,
    Users,
    UserRound,
    Warehouse,
    Layers3,
} from 'lucide-vue-next';
import { ref } from 'vue';
import AppLogo from './AppLogo.vue';

const page = usePage();

// Collapsible state
const gymOpen = ref(true);
const inventoryOpen = ref(true);
const systemOpen = ref(true);

// Route helpers
const companies = () => ({ url: '/companies', method: 'get' as const });
const users = () => ({ url: '/users', method: 'get' as const });
const members = () => ({ url: '/members', method: 'get' as const });
const membershipPlans = () => ({ url: '/membership-plans', method: 'get' as const });
const attendances = () => ({ url: '/attendances', method: 'get' as const });

// Inventario routes (placeholder - to be implemented)
const categories = () => ({ url: '/categories', method: 'get' as const });
const products = () => ({ url: '/products', method: 'get' as const });
const attributes = () => ({ url: '/attributes', method: 'get' as const });
const warehouses = () => ({ url: '/warehouses', method: 'get' as const });
const variants = () => ({ url: '/variants', method: 'get' as const });

// Dashboard
const dashboardItem: NavItem = {
    title: 'Dashboard',
    href: dashboard(),
    icon: LayoutDashboard,
};

// Gym section
const gymItems: NavItem[] = [
    {
        title: 'Miembros',
        href: members(),
        icon: Users,
    },
    {
        title: 'Planes',
        href: membershipPlans(),
        icon: CreditCard,
    },
    {
        title: 'Asistencias',
        href: attendances(),
        icon: ClipboardCheck,
    },
];

// Inventario section
const inventoryItems: NavItem[] = [
    {
        title: 'Categorías',
        href: categories(),
        icon: Folder,
    },
    {
        title: 'Productos',
        href: products(),
        icon: Package,
    },
    {
        title: 'Atributos',
        href: attributes(),
        icon: Tags,
    },
    {
        title: 'Almacén',
        href: warehouses(),
        icon: Warehouse,
    },
    {
        title: 'Variantes',
        href: variants(),
        icon: Layers3,
    },
];

// Sistema section
const systemItems: NavItem[] = [
    {
        title: 'Compañías',
        href: companies(),
        icon: Building2,
    },
    {
        title: 'Usuarios',
        href: users(),
        icon: UserRound,
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
            <!-- Dashboard -->
            <SidebarGroup class="px-2 py-0">
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            as-child
                            :is-active="urlIsActive(dashboardItem.href, page.url)"
                            :tooltip="dashboardItem.title"
                        >
                            <Link :href="dashboardItem.href">
                                <component :is="dashboardItem.icon" />
                                <span>{{ dashboardItem.title }}</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarGroup>

            <!-- Gym Section - Collapsible -->
            <Collapsible v-model:open="gymOpen" class="group/collapsible">
                <SidebarGroup class="px-2 py-0">
                    <SidebarMenu>
                        <SidebarMenuItem>
                            <CollapsibleTrigger as-child>
                                <SidebarMenuButton :tooltip="'Gimnasio'">
                                    <Dumbbell class="h-4 w-4" />
                                    <span>Gimnasio</span>
                                    <ChevronDown class="ml-auto h-4 w-4 transition-transform group-data-[state=open]/collapsible:rotate-180" />
                                </SidebarMenuButton>
                            </CollapsibleTrigger>
                            <CollapsibleContent>
                                <SidebarMenuSub>
                                    <SidebarMenuSubItem v-for="item in gymItems" :key="item.title">
                                        <SidebarMenuSubButton
                                            as-child
                                            :is-active="urlIsActive(item.href, page.url)"
                                        >
                                            <Link :href="item.href">
                                                <component :is="item.icon" class="h-4 w-4" />
                                                <span>{{ item.title }}</span>
                                            </Link>
                                        </SidebarMenuSubButton>
                                    </SidebarMenuSubItem>
                                </SidebarMenuSub>
                            </CollapsibleContent>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarGroup>
            </Collapsible>

            <!-- Inventario Section - Collapsible -->
            <Collapsible v-model:open="inventoryOpen" class="group/collapsible">
                <SidebarGroup class="px-2 py-0">
                    <SidebarMenu>
                        <SidebarMenuItem>
                            <CollapsibleTrigger as-child>
                                <SidebarMenuButton :tooltip="'Inventario'">
                                    <PackageOpen class="h-4 w-4" />
                                    <span>Inventario</span>
                                    <ChevronDown class="ml-auto h-4 w-4 transition-transform group-data-[state=open]/collapsible:rotate-180" />
                                </SidebarMenuButton>
                            </CollapsibleTrigger>
                            <CollapsibleContent>
                                <SidebarMenuSub>
                                    <SidebarMenuSubItem v-for="item in inventoryItems" :key="item.title">
                                        <SidebarMenuSubButton
                                            as-child
                                            :is-active="urlIsActive(item.href, page.url)"
                                        >
                                            <Link :href="item.href">
                                                <component :is="item.icon" class="h-4 w-4" />
                                                <span>{{ item.title }}</span>
                                            </Link>
                                        </SidebarMenuSubButton>
                                    </SidebarMenuSubItem>
                                </SidebarMenuSub>
                            </CollapsibleContent>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarGroup>
            </Collapsible>

            <!-- Sistema Section - Collapsible -->
            <Collapsible v-model:open="systemOpen" class="group/collapsible">
                <SidebarGroup class="px-2 py-0">
                    <SidebarMenu>
                        <SidebarMenuItem>
                            <CollapsibleTrigger as-child>
                                <SidebarMenuButton :tooltip="'Sistema'">
                                    <Settings class="h-4 w-4" />
                                    <span>Sistema</span>
                                    <ChevronDown class="ml-auto h-4 w-4 transition-transform group-data-[state=open]/collapsible:rotate-180" />
                                </SidebarMenuButton>
                            </CollapsibleTrigger>
                            <CollapsibleContent>
                                <SidebarMenuSub>
                                    <SidebarMenuSubItem v-for="item in systemItems" :key="item.title">
                                        <SidebarMenuSubButton
                                            as-child
                                            :is-active="urlIsActive(item.href, page.url)"
                                        >
                                            <Link :href="item.href">
                                                <component :is="item.icon" class="h-4 w-4" />
                                                <span>{{ item.title }}</span>
                                            </Link>
                                        </SidebarMenuSubButton>
                                    </SidebarMenuSubItem>
                                </SidebarMenuSub>
                            </CollapsibleContent>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarGroup>
            </Collapsible>
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>

