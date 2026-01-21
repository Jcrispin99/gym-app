<script setup lang="ts">
import FormPageHeader from '@/components/FormPageHeader.vue';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectTrigger } from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { Edit, Filter, Search, Trash2, UserPlus, Users } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Company {
    id: number;
    trade_name: string;
}

interface Member {
    id: number;
    document_number: string;
    first_name: string;
    last_name: string;
    email: string | null;
    phone: string | null;
    status: string;
    user_id: number | null;
    company?: Company;
    created_at: string;
}

interface Props {
    members: Member[];
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Miembros', href: '/members' },
];

const deleteDialogOpen = ref(false);
const memberToDelete = ref<Member | null>(null);

// Search and multi-select filters
const searchQuery = ref('');
const selectedStatuses = ref<string[]>([]);
const selectedPortalFilters = ref<string[]>([]);

const openDeleteDialog = (member: Member) => {
    memberToDelete.value = member;
    deleteDialogOpen.value = true;
};

const deleteMember = () => {
    if (memberToDelete.value) {
        router.delete(`/members/${memberToDelete.value.id}`, {
            onSuccess: () => {
                deleteDialogOpen.value = false;
                memberToDelete.value = null;
            },
        });
    }
};

const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString('es-PE', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

const getStatusBadge = (status: string) => {
    const badges: Record<string, string> = {
        active: 'bg-green-100 text-green-800',
        inactive: 'bg-gray-100 text-gray-800',
        suspended: 'bg-red-100 text-red-800',
    };
    return badges[status] || badges.active;
};

// Filtered members with multi-select checkboxes
const filteredMembers = computed(() => {
    let filtered = props.members;

    // Search filter
    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        filtered = filtered.filter(
            (member) =>
                member.first_name.toLowerCase().includes(query) ||
                member.last_name.toLowerCase().includes(query) ||
                member.document_number.toLowerCase().includes(query) ||
                member.email?.toLowerCase().includes(query),
        );
    }

    // Status filter (multiple selection)
    if (selectedStatuses.value.length > 0) {
        filtered = filtered.filter((m) =>
            selectedStatuses.value.includes(m.status),
        );
    }

    // Portal access filter (multiple selection)
    if (selectedPortalFilters.value.length > 0) {
        let portalFiltered: Member[] = [];
        if (selectedPortalFilters.value.includes('with_portal')) {
            portalFiltered = [
                ...portalFiltered,
                ...filtered.filter((m) => m.user_id !== null),
            ];
        }
        if (selectedPortalFilters.value.includes('without_portal')) {
            portalFiltered = [
                ...portalFiltered,
                ...filtered.filter((m) => m.user_id === null),
            ];
        }
        // Remove duplicates
        filtered = portalFiltered.filter(
            (item, index, self) =>
                index === self.findIndex((t) => t.id === item.id),
        );
    }

    return filtered;
});

const activeFiltersCount = computed(() => {
    return selectedStatuses.value.length + selectedPortalFilters.value.length;
});
</script>

<template>
    <Head title="Miembros" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <FormPageHeader
                title="Miembros"
                description="Gestiona los miembros del gimnasio"
                :show-back="false"
            >
                <template #actions>
                    <Button @click="router.visit('/members/create')">
                        <UserPlus class="mr-2 h-4 w-4" />
                        Nuevo Miembro
                    </Button>
                </template>
            </FormPageHeader>

            <!-- Stats Cards - Compact (3 cards) -->
            <div class="grid gap-4 md:grid-cols-3">
                <Card>
                    <CardHeader
                        class="flex flex-row items-center justify-between space-y-0 pb-2"
                    >
                        <CardTitle class="text-sm font-medium">
                            Total Miembros
                        </CardTitle>
                        <Users class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">
                            {{ filteredMembers.length }}
                        </div>
                        <p class="text-xs text-muted-foreground">Registrados</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader
                        class="flex flex-row items-center justify-between space-y-0 pb-2"
                    >
                        <CardTitle class="text-sm font-medium">
                            Activos
                        </CardTitle>
                        <Users class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">
                            {{
                                filteredMembers.filter(
                                    (m) => m.status === 'active',
                                ).length
                            }}
                        </div>
                        <p class="text-xs text-muted-foreground">
                            miembros activos
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader
                        class="flex flex-row items-center justify-between space-y-0 pb-2"
                    >
                        <CardTitle class="text-sm font-medium">
                            Suspendidos
                        </CardTitle>
                        <Users class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">
                            {{
                                filteredMembers.filter(
                                    (m) => m.status === 'suspended',
                                ).length
                            }}
                        </div>
                        <p class="text-xs text-muted-foreground">Suspendidos</p>
                    </CardContent>
                </Card>
            </div>

            <!-- Members Table -->
            <Card>
                <CardHeader>
                    <div class="flex items-start justify-between gap-4">
                        <!-- Title + Description (Left) -->
                        <div>
                            <CardTitle>Listado de Miembros</CardTitle>
                            <CardDescription>
                                Mostrando {{ filteredMembers.length }} de
                                {{ members.length }} miembros
                            </CardDescription>
                        </div>

                        <!-- Search + Filter (Right) -->
                        <div class="flex gap-2">
                            <!-- Search Bar -->
                            <div class="relative w-[300px]">
                                <Search
                                    class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                                />
                                <Input
                                    v-model="searchQuery"
                                    placeholder="Buscar..."
                                    class="pl-10"
                                />
                            </div>

                            <!-- Multi-Select Filter -->
                            <Select>
                                <SelectTrigger class="w-[180px]">
                                    <div class="flex items-center gap-2">
                                        <Filter class="h-4 w-4" />
                                        <span>Filtros</span>
                                        <Badge
                                            v-if="activeFiltersCount > 0"
                                            variant="secondary"
                                            class="ml-auto h-5 px-1.5"
                                        >
                                            {{ activeFiltersCount }}
                                        </Badge>
                                    </div>
                                </SelectTrigger>
                                <SelectContent class="w-[220px]">
                                    <!-- Status Filters -->
                                    <div class="px-2 py-1.5">
                                        <p
                                            class="mb-2 text-xs font-semibold text-muted-foreground"
                                        >
                                            Estado
                                        </p>
                                        <div class="space-y-2">
                                            <label
                                                class="flex cursor-pointer items-center gap-2"
                                            >
                                                <Checkbox
                                                    :checked="
                                                        selectedStatuses.includes(
                                                            'active',
                                                        )
                                                    "
                                                    @update:checked="
                                                        (checked: boolean) => {
                                                            selectedStatuses =
                                                                checked
                                                                    ? [
                                                                          ...selectedStatuses,
                                                                          'active',
                                                                      ]
                                                                    : selectedStatuses.filter(
                                                                          (s) =>
                                                                              s !==
                                                                              'active',
                                                                      );
                                                        }
                                                    "
                                                />
                                                <span class="text-sm"
                                                    >Activos</span
                                                >
                                            </label>
                                            <label
                                                class="flex cursor-pointer items-center gap-2"
                                            >
                                                <Checkbox
                                                    :checked="
                                                        selectedStatuses.includes(
                                                            'inactive',
                                                        )
                                                    "
                                                    @update:checked="
                                                        (checked: boolean) => {
                                                            selectedStatuses =
                                                                checked
                                                                    ? [
                                                                          ...selectedStatuses,
                                                                          'inactive',
                                                                      ]
                                                                    : selectedStatuses.filter(
                                                                          (s) =>
                                                                              s !==
                                                                              'inactive',
                                                                      );
                                                        }
                                                    "
                                                />
                                                <span class="text-sm"
                                                    >Inactivos</span
                                                >
                                            </label>
                                            <label
                                                class="flex cursor-pointer items-center gap-2"
                                            >
                                                <Checkbox
                                                    :checked="
                                                        selectedStatuses.includes(
                                                            'suspended',
                                                        )
                                                    "
                                                    @update:checked="
                                                        (checked: boolean) => {
                                                            selectedStatuses =
                                                                checked
                                                                    ? [
                                                                          ...selectedStatuses,
                                                                          'suspended',
                                                                      ]
                                                                    : selectedStatuses.filter(
                                                                          (s) =>
                                                                              s !==
                                                                              'suspended',
                                                                      );
                                                        }
                                                    "
                                                />
                                                <span class="text-sm"
                                                    >Suspendidos</span
                                                >
                                            </label>
                                        </div>
                                    </div>

                                    <div class="my-2 border-t"></div>

                                    <!-- Portal Filters -->
                                    <div class="px-2 py-1.5">
                                        <p
                                            class="mb-2 text-xs font-semibold text-muted-foreground"
                                        >
                                            Acceso Portal
                                        </p>
                                        <div class="space-y-2">
                                            <label
                                                class="flex cursor-pointer items-center gap-2"
                                            >
                                                <Checkbox
                                                    :checked="
                                                        selectedPortalFilters.includes(
                                                            'with_portal',
                                                        )
                                                    "
                                                    @update:checked="
                                                        (checked: boolean) => {
                                                            selectedPortalFilters =
                                                                checked
                                                                    ? [
                                                                          ...selectedPortalFilters,
                                                                          'with_portal',
                                                                      ]
                                                                    : selectedPortalFilters.filter(
                                                                          (f) =>
                                                                              f !==
                                                                              'with_portal',
                                                                      );
                                                        }
                                                    "
                                                />
                                                <span class="text-sm"
                                                    >Con acceso</span
                                                >
                                            </label>
                                            <label
                                                class="flex cursor-pointer items-center gap-2"
                                            >
                                                <Checkbox
                                                    :checked="
                                                        selectedPortalFilters.includes(
                                                            'without_portal',
                                                        )
                                                    "
                                                    @update:checked="
                                                        (checked: boolean) => {
                                                            selectedPortalFilters =
                                                                checked
                                                                    ? [
                                                                          ...selectedPortalFilters,
                                                                          'without_portal',
                                                                      ]
                                                                    : selectedPortalFilters.filter(
                                                                          (f) =>
                                                                              f !==
                                                                              'without_portal',
                                                                      );
                                                        }
                                                    "
                                                />
                                                <span class="text-sm"
                                                    >Sin acceso</span
                                                >
                                            </label>
                                        </div>
                                    </div>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                </CardHeader>
                <CardContent>
                    <Table v-if="filteredMembers.length > 0">
                        <TableHeader>
                            <TableRow>
                                <TableHead>Documento</TableHead>
                                <TableHead>Nombre</TableHead>
                                <TableHead>Email</TableHead>
                                <TableHead>Teléfono</TableHead>
                                <TableHead>Compañía</TableHead>
                                <TableHead>Portal</TableHead>
                                <TableHead>Estado</TableHead>
                                <TableHead>Registro</TableHead>
                                <TableHead class="text-right"
                                    >Acciones</TableHead
                                >
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow
                                v-for="member in filteredMembers"
                                :key="member.id"
                            >
                                <TableCell class="font-medium">
                                    {{ member.document_number }}
                                </TableCell>
                                <TableCell>
                                    {{ member.first_name }}
                                    {{ member.last_name }}
                                </TableCell>
                                <TableCell>
                                    {{ member.email || '-' }}
                                </TableCell>
                                <TableCell>
                                    {{ member.phone || '-' }}
                                </TableCell>
                                <TableCell>
                                    {{ member.company?.trade_name || '-' }}
                                </TableCell>
                                <TableCell>
                                    <span
                                        v-if="member.user_id"
                                        class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800"
                                    >
                                        ✓ Activo
                                    </span>
                                    <span
                                        v-else
                                        class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600"
                                    >
                                        Sin acceso
                                    </span>
                                </TableCell>
                                <TableCell>
                                    <span
                                        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                        :class="getStatusBadge(member.status)"
                                    >
                                        {{ member.status }}
                                    </span>
                                </TableCell>
                                <TableCell>
                                    {{ formatDate(member.created_at) }}
                                </TableCell>
                                <TableCell class="text-right">
                                    <div class="flex justify-end gap-2">
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            @click="
                                                router.visit(
                                                    `/members/${member.id}/edit`,
                                                )
                                            "
                                        >
                                            <Edit class="h-4 w-4" />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            @click="openDeleteDialog(member)"
                                        >
                                            <Trash2
                                                class="h-4 w-4 text-red-500"
                                            />
                                        </Button>
                                    </div>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                    <div v-else class="py-10 text-center text-muted-foreground">
                        No hay miembros registrados
                    </div>
                </CardContent>
            </Card>

            <!-- Delete Confirmation Dialog -->
            <AlertDialog v-model:open="deleteDialogOpen">
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>¿Estás seguro?</AlertDialogTitle>
                        <AlertDialogDescription>
                            Esta acción eliminará permanentemente al miembro
                            <strong v-if="memberToDelete">
                                {{ memberToDelete.first_name }}
                                {{ memberToDelete.last_name }} </strong
                            >. Esta acción no se puede deshacer.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancelar</AlertDialogCancel>
                        <AlertDialogAction @click="deleteMember">
                            Eliminar
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </div>
    </AppLayout>
</template>
