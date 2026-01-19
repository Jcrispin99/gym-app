<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import type { AppPageProps, NavItem, SidebarItem } from '@/types';
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
    Percent,
    Settings,
    ShoppingCart,
    Snowflake,
    Store,
    Tags,
    TrendingUp,
    UserRound,
    Users,
    Warehouse,
} from 'lucide-vue-next';
import { computed, reactive, watchEffect } from 'vue';
import AppLogo from './AppLogo.vue';

const page = usePage<AppPageProps>();

const sidebarItems = computed<SidebarItem[]>(() => page.props.sidebar ?? []);

const iconMap = {
    BookOpen,
    Building2,
    ClipboardCheck,
    CreditCard,
    Dumbbell,
    Folder,
    LayoutDashboard,
    Package,
    PackageOpen,
    Percent,
    Settings,
    ShoppingCart,
    Snowflake,
    Store,
    Tags,
    TrendingUp,
    Users,
    UserRound,
    Warehouse,
};

const resolveIcon = (name?: string | null) => {
    if (!name) return null;
    return (iconMap as Record<string, any>)[name] ?? null;
};

const openGroups = reactive<Record<string, boolean>>({});

watchEffect(() => {
    for (const item of sidebarItems.value) {
        if (item.type !== 'group') continue;
        openGroups[item.title] = item.isActive;
    }
});

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
            <SidebarMenu>
                <SidebarMenuItem>
                    <AppLogo />
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <template
                v-for="item in sidebarItems"
                :key="`${item.type}:${item.title}`"
            >
                <SidebarGroup v-if="item.type === 'header'" class="px-2 py-0">
                    <SidebarGroupLabel>{{ item.title }}</SidebarGroupLabel>
                </SidebarGroup>

                <SidebarGroup
                    v-else-if="item.type === 'link'"
                    class="px-2 py-0"
                >
                    <SidebarMenu>
                        <SidebarMenuItem>
                            <SidebarMenuButton
                                as-child
                                :is-active="item.isActive"
                                :tooltip="item.title"
                            >
                                <Link :href="item.href">
                                    <component
                                        v-if="resolveIcon(item.icon)"
                                        :is="resolveIcon(item.icon)"
                                    />
                                    <span>{{ item.title }}</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarGroup>

                <Collapsible
                    v-else
                    v-model:open="openGroups[item.title]"
                    class="group/collapsible"
                >
                    <SidebarGroup class="px-2 py-0">
                        <SidebarMenu>
                            <SidebarMenuItem>
                                <CollapsibleTrigger as-child>
                                    <SidebarMenuButton
                                        :tooltip="item.title"
                                        :is-active="item.isActive"
                                    >
                                        <component
                                            v-if="resolveIcon(item.icon)"
                                            :is="resolveIcon(item.icon)"
                                            class="h-4 w-4"
                                        />
                                        <span>{{ item.title }}</span>
                                        <ChevronDown
                                            class="ml-auto h-4 w-4 transition-transform group-data-[state=open]/collapsible:rotate-180"
                                        />
                                    </SidebarMenuButton>
                                </CollapsibleTrigger>
                                <CollapsibleContent>
                                    <SidebarMenuSub>
                                        <SidebarMenuSubItem
                                            v-for="sub in item.items"
                                            :key="sub.title"
                                        >
                                            <SidebarMenuSubButton
                                                as-child
                                                :is-active="sub.isActive"
                                            >
                                                <Link :href="sub.href">
                                                    <component
                                                        v-if="
                                                            resolveIcon(
                                                                sub.icon,
                                                            )
                                                        "
                                                        :is="
                                                            resolveIcon(
                                                                sub.icon,
                                                            )
                                                        "
                                                        class="h-4 w-4"
                                                    />
                                                    <span>{{ sub.title }}</span>
                                                </Link>
                                            </SidebarMenuSubButton>
                                        </SidebarMenuSubItem>
                                    </SidebarMenuSub>
                                </CollapsibleContent>
                            </SidebarMenuItem>
                        </SidebarMenu>
                    </SidebarGroup>
                </Collapsible>
            </template>
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
