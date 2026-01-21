<script setup lang="ts">
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
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { Building2, Mail, Pencil, Trash2, UserPlus, Search } from 'lucide-vue-next';
import FormPageHeader from '@/components/FormPageHeader.vue';
import { ref, computed } from 'vue';

interface User {
    id: number;
    name: string;
    email: string;
    company_id: number | null;
    company: {
        id: number;
        trade_name: string;
    } | null;
    created_at: string;
    updated_at: string;
}

interface Props {
    users: User[];
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Usuarios', href: '/users' },
];

const deleteDialogOpen = ref(false);
const selectedUser = ref<User | null>(null);

// Search
const searchQuery = ref('');

const openDeleteDialog = (user: User) => {
    selectedUser.value = user;
    deleteDialogOpen.value = true;
};

const deleteUser = () => {
    if (selectedUser.value) {
        router.delete(`/users/${selectedUser.value.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                deleteDialogOpen.value = false;
                selectedUser.value = null;
            },
        });
    }
};

// Filtered users
const filteredUsers = computed(() => {
    if (!searchQuery.value) {
        return props.users;
    }

    const query = searchQuery.value.toLowerCase();
    return props.users.filter(user =>
        user.name.toLowerCase().includes(query) ||
        user.email.toLowerCase().includes(query) ||
        user.company?.trade_name.toLowerCase().includes(query)
    );
});
</script>

<template>
    <Head title="Usuarios" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <FormPageHeader
                title="Usuarios"
                description="Gestiona los usuarios del sistema"
                :show-back="false"
            >
                <template #actions>
                    <Button @click="router.visit('/users/create')">
                        <UserPlus class="mr-2 h-4 w-4" />
                        Nuevo Usuario
                    </Button>
                </template>
            </FormPageHeader>

            <!-- Stats Card -->
            <div class="grid gap-4 md:grid-cols-3">
                <Card>
                    <CardHeader
                        class="flex flex-row items-center justify-between space-y-0 pb-2"
                    >
                        <CardTitle class="text-sm font-medium">
                            Total Usuarios
                        </CardTitle>
                        <UserPlus class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ filteredUsers.length }}</div>
                        <p class="text-xs text-muted-foreground">
                            Usuarios registrados
                        </p>
                    </CardContent>
                </Card>
            </div>

            <!-- Users Table -->
            <Card>
                <CardHeader>
                    <div class="flex items-start justify-between gap-4">
                        <!-- Title + Description (Left) -->
                        <div>
                            <CardTitle>Listado de Usuarios</CardTitle>
                            <CardDescription>
                                Mostrando {{ filteredUsers.length }} de {{ users.length }} usuarios
                            </CardDescription>
                        </div>

                        <!-- Search (Right) -->
                        <div class="relative w-[300px]">
                            <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                v-model="searchQuery"
                                placeholder="Buscar..."
                                class="pl-10"
                            />
                        </div>
                    </div>
                </CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Nombre</TableHead>
                                <TableHead>Email</TableHead>
                                <TableHead>Compañía</TableHead>
                                <TableHead>Fecha Registro</TableHead>
                                <TableHead class="text-right"
                                    >Acciones</TableHead
                                >
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-if="filteredUsers.length === 0">
                                <TableCell
                                    colspan="5"
                                    class="text-center text-muted-foreground"
                                >
                                    No hay usuarios que coincidan con la búsqueda
                                </TableCell>
                            </TableRow>
                            <TableRow v-for="user in filteredUsers" :key="user.id">
                                <!-- Nombre -->
                                <TableCell>
                                    <div class="font-medium">
                                        {{ user.name }}
                                    </div>
                                </TableCell>

                                <!-- Email -->
                                <TableCell>
                                    <div class="flex items-center gap-2">
                                        <Mail
                                            class="h-4 w-4 text-muted-foreground"
                                        />
                                        <span class="text-sm">{{
                                            user.email
                                        }}</span>
                                    </div>
                                </TableCell>

                                <!-- Compañía -->
                                <TableCell>
                                    <div class="flex items-center gap-2">
                                        <Building2
                                            class="h-4 w-4 text-muted-foreground"
                                        />
                                        <span class="text-sm">{{
                                            user.company?.trade_name ??
                                            'Sin compañía'
                                        }}</span>
                                    </div>
                                </TableCell>

                                <!-- Fecha Registro -->
                                <TableCell>
                                    <span class="text-sm text-muted-foreground">
                                        {{
                                            new Date(
                                                user.created_at,
                                            ).toLocaleDateString('es-PE')
                                        }}
                                    </span>
                                </TableCell>

                                <!-- Acciones -->
                                <TableCell class="text-right">
                                    <div class="flex justify-end gap-2">
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            @click="
                                                router.visit(
                                                    `/users/${user.id}/edit`,
                                                )
                                            "
                                        >
                                            <Pencil class="h-4 w-4" />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            @click="openDeleteDialog(user)"
                                        >
                                            <Trash2
                                                class="h-4 w-4 text-destructive"
                                            />
                                        </Button>
                                    </div>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>

            <!-- Delete Confirmation Dialog -->
            <AlertDialog v-model:open="deleteDialogOpen">
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>¿Estás seguro?</AlertDialogTitle>
                        <AlertDialogDescription>
                            Esta acción no se puede deshacer. Se eliminará
                            permanentemente el usuario
                            <strong>{{ selectedUser?.name }}</strong
                            >.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancelar</AlertDialogCancel>
                        <AlertDialogAction @click="deleteUser">
                            Eliminar
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </div>
    </AppLayout>
</template>
