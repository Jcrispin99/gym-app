<script setup lang="ts">
import { Checkbox } from '@/components/ui/checkbox';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { router } from '@inertiajs/vue3';
import { Building2, Check, ChevronsUpDown } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

interface Company {
    id: number;
    trade_name: string;
    branch_code: string | null;
    district: string;
    is_branch?: boolean;
    logo_url?: string | null;
}

interface CompaniesData {
    main_office: Company | null;
    branches: Company[];
}

const companies = ref<CompaniesData>({
    main_office: null,
    branches: [],
});

const selectedCompanyIds = ref<number[]>([]);
const tempSelectedIds = ref<number[]>([]);
const dropdownOpen = ref(false);

// Lista jerárquica: matriz primero, luego sucursales
const hierarchicalCompanies = computed(() => {
    const list: Company[] = [];

    if (companies.value.main_office) {
        list.push({
            ...companies.value.main_office,
            is_branch: false,
        });
    }

    companies.value.branches.forEach((branch) => {
        list.push({
            ...branch,
            is_branch: true,
        });
    });

    return list;
});

const selectedCompanies = computed(() => {
    return hierarchicalCompanies.value.filter((c) =>
        selectedCompanyIds.value.includes(c.id),
    );
});

const displayText = computed(() => {
    const count = selectedCompanyIds.value.length;
    if (count === 0) return 'Seleccionar compañías';
    if (count === 1) {
        const company = selectedCompanies.value[0];
        return company?.trade_name || 'Seleccionar';
    }
    return `${count} compañías seleccionadas`;
});

const displaySubtext = computed(() => {
    const count = selectedCompanyIds.value.length;
    if (count === 0) return 'Ninguna seleccionada';
    if (count === 1) {
        const company = selectedCompanies.value[0];
        return company?.branch_code
            ? `Sucursal ${company.branch_code}`
            : 'Casa Matriz';
    }
    return selectedCompanies.value
        .map((c) => c.branch_code || 'Matriz')
        .join(', ');
});

const displayLogoUrl = computed(() => {
    if (selectedCompanyIds.value.length === 1) {
        return selectedCompanies.value[0]?.logo_url ?? null;
    }
    return null;
});

const loadCompanies = async () => {
    try {
        const response = await fetch('/api/companies');
        const data = await response.json();
        companies.value = data;

        // Set default selected companies from session
        const sessionIds = sessionStorage.getItem('selected_company_ids');
        if (sessionIds) {
            selectedCompanyIds.value = JSON.parse(sessionIds);
        } else if (data.main_office) {
            // Default: select main office only
            selectedCompanyIds.value = [data.main_office.id];
        }

        // Initialize temp selection
        tempSelectedIds.value = [...selectedCompanyIds.value];
    } catch (error) {
        console.error('Error loading companies:', error);
    }
};

const toggleCompany = (companyId: number) => {
    const index = tempSelectedIds.value.indexOf(companyId);

    if (index > -1) {
        // Prevent deselecting if it's the last one
        if (tempSelectedIds.value.length === 1) {
            return; // Must have at least 1 selected
        }
        tempSelectedIds.value.splice(index, 1);
    } else {
        tempSelectedIds.value.push(companyId);
    }
};

const onOpenChange = (open: boolean) => {
    dropdownOpen.value = open;

    if (open) {
        // Reset temp selection to current when opening
        tempSelectedIds.value = [...selectedCompanyIds.value];
    } else {
        // Apply changes when closing (if changed)
        applySelectionIfChanged();
    }
};

const applySelectionIfChanged = () => {
    // Check if selection changed
    if (
        tempSelectedIds.value.length !== selectedCompanyIds.value.length ||
        !tempSelectedIds.value.every((id) =>
            selectedCompanyIds.value.includes(id),
        )
    ) {
        if (tempSelectedIds.value.length === 0) {
            return; // Should never happen
        }

        selectedCompanyIds.value = [...tempSelectedIds.value];
        sessionStorage.setItem(
            'selected_company_ids',
            JSON.stringify(selectedCompanyIds.value),
        );

        // Use Inertia router - handles CSRF automatically
        router.post(
            '/api/companies/switch',
            { company_ids: selectedCompanyIds.value },
            {
                preserveState: true,
                onSuccess: () => router.reload(),
            },
        );
    }
};

onMounted(() => {
    loadCompanies();
});
</script>

<template>
    <SidebarMenu>
        <SidebarMenuItem>
            <DropdownMenu
                v-model:open="dropdownOpen"
                @update:open="onOpenChange"
            >
                <DropdownMenuTrigger as-child>
                    <SidebarMenuButton
                        size="lg"
                        class="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground"
                    >
                        <div
                            class="flex aspect-square size-8 items-center justify-center rounded-lg bg-primary text-sidebar-primary-foreground"
                        >
                            <img
                                v-if="displayLogoUrl"
                                :src="displayLogoUrl"
                                alt="Logo"
                                class="h-full w-full object-contain"
                            />
                            <Building2 v-else class="size-4" />
                        </div>
                        <div
                            class="grid flex-1 text-left text-sm leading-tight"
                        >
                            <span class="truncate font-semibold">
                                {{ displayText }}
                            </span>
                            <span
                                class="truncate text-xs text-muted-foreground"
                            >
                                {{ displaySubtext }}
                            </span>
                        </div>
                        <ChevronsUpDown class="ml-auto" />
                    </SidebarMenuButton>
                </DropdownMenuTrigger>
                <DropdownMenuContent
                    class="w-[--radix-dropdown-menu-trigger-width] min-w-56 rounded-lg"
                    align="start"
                    side="bottom"
                    :side-offset="4"
                >
                    <DropdownMenuLabel class="text-xs text-muted-foreground">
                        Compañías y Sucursales
                    </DropdownMenuLabel>

                    <!-- Lista jerárquica -->
                    <DropdownMenuItem
                        v-for="company in hierarchicalCompanies"
                        :key="company.id"
                        @click.prevent="toggleCompany(company.id)"
                        @select.prevent
                        :class="[
                            'cursor-pointer gap-2 p-2',
                            company.is_branch ? 'pl-8' : '',
                        ]"
                    >
                        <Checkbox
                            :id="`company-${company.id}`"
                            :model-value="tempSelectedIds.includes(company.id)"
                            class="pointer-events-none"
                        />
                        <div
                            class="flex size-6 items-center justify-center rounded-sm border bg-background"
                        >
                            <img
                                v-if="company.logo_url"
                                :src="company.logo_url"
                                alt="Logo"
                                class="h-full w-full object-contain"
                            />
                            <Building2 v-else class="size-4 shrink-0" />
                        </div>
                        <div class="flex-1">
                            <div class="font-medium">
                                {{ company.trade_name }}
                            </div>
                            <div class="text-xs text-muted-foreground">
                                {{
                                    company.branch_code
                                        ? `${company.branch_code} · ${company.district}`
                                        : 'Casa Matriz'
                                }}
                            </div>
                        </div>
                        <Check
                            v-if="tempSelectedIds.includes(company.id)"
                            class="ml-auto size-4 text-primary"
                        />
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </SidebarMenuItem>
    </SidebarMenu>
</template>
