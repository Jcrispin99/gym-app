<script setup lang="ts">
import FormPageHeader from '@/components/FormPageHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { Clock, Save, User as UserIcon } from 'lucide-vue-next';

interface Activity {
    description: string;
    event: string;
    properties: Record<string, any>;
    created_at: string;
    causer: {
        name: string;
        email: string;
    } | null;
}

interface Company {
    id: number;
    trade_name: string;
}

interface User {
    id: number;
    name: string;
    email: string;
    company_id: number;
    company_ids: number[];
    created_at: string;
    updated_at: string;
}

interface Props {
    user: User;
    companies: Company[];
    activities: Activity[];
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Usuarios', href: '/users' },
    { title: props.user.name, href: `/users/${props.user.id}/edit` },
];

const form = useForm({
    name: props.user.name,
    email: props.user.email,
    company_ids: props.user.company_ids || [],
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.put(`/users/${props.user.id}`, {
        preserveScroll: true,
    });
};

const formatDate = (date: string) => {
    return new Date(date).toLocaleString('es-PE');
};

const getEventBadgeVariant = (event: string) => {
    switch (event) {
        case 'created':
            return 'default';
        case 'updated':
            return 'secondary';
        case 'deleted':
            return 'destructive';
        default:
            return 'outline';
    }
};

const getEventLabel = (event: string) => {
    switch (event) {
        case 'created':
            return 'Creado';
        case 'updated':
            return 'Actualizado';
        case 'deleted':
            return 'Eliminado';
        default:
            return event;
    }
};
</script>

<template>
    <Head :title="`Editar - ${user.name}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-4">
            <FormPageHeader
                :title="user.name"
                :description="user.email"
                back-href="/users"
            >
                <template #actions>
                    <Button @click="submit" :disabled="form.processing">
                        <Save class="mr-2 h-4 w-4" />
                        {{
                            form.processing ? 'Guardando...' : 'Guardar Cambios'
                        }}
                    </Button>
                </template>
            </FormPageHeader>

            <!-- Odoo-style Layout -->
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <!-- Main Form (Left) -->
                <div class="lg:col-span-2">
                    <form @submit.prevent="submit" class="space-y-6">
                        <!-- Información del Usuario -->
                        <Card>
                            <CardHeader>
                                <CardTitle>Información del Usuario</CardTitle>
                                <CardDescription
                                    >Datos principales del
                                    usuario</CardDescription
                                >
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div>
                                    <Label for="name">Nombre Completo *</Label>
                                    <Input
                                        id="name"
                                        v-model="form.name"
                                        :class="{
                                            'border-red-500': form.errors.name,
                                        }"
                                    />
                                    <p
                                        v-if="form.errors.name"
                                        class="mt-1 text-sm text-red-500"
                                    >
                                        {{ form.errors.name }}
                                    </p>
                                </div>

                                <div>
                                    <Label for="email">Email *</Label>
                                    <Input
                                        id="email"
                                        v-model="form.email"
                                        type="email"
                                        :class="{
                                            'border-red-500': form.errors.email,
                                        }"
                                    />
                                    <p
                                        v-if="form.errors.email"
                                        class="mt-1 text-sm text-red-500"
                                    >
                                        {{ form.errors.email }}
                                    </p>
                                </div>

                                <!-- Compañías (Multi-select con checkboxes) -->
                                <div>
                                    <Label>Compañías * (mínimo 1)</Label>
                                    <div
                                        class="mt-2 max-h-48 space-y-2 overflow-y-auto rounded-md border p-4"
                                    >
                                        <div
                                            v-for="company in companies"
                                            :key="company.id"
                                            class="flex items-center space-x-2"
                                        >
                                            <input
                                                :id="`company-${company.id}`"
                                                type="checkbox"
                                                :value="company.id"
                                                v-model="form.company_ids"
                                                class="h-4 w-4 rounded border-gray-300"
                                            />
                                            <label
                                                :for="`company-${company.id}`"
                                                class="cursor-pointer text-sm leading-none font-medium peer-disabled:cursor-not-allowed peer-disabled:opacity-70"
                                            >
                                                {{ company.trade_name }}
                                            </label>
                                        </div>
                                    </div>
                                    <p
                                        v-if="form.errors.company_ids"
                                        class="mt-1 text-sm text-red-500"
                                    >
                                        {{ form.errors.company_ids }}
                                    </p>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Cambiar Contraseña -->
                        <Card>
                            <CardHeader>
                                <CardTitle>Cambiar Contraseña</CardTitle>
                                <CardDescription
                                    >Dejar en blanco para mantener la
                                    actual</CardDescription
                                >
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div>
                                    <Label for="password"
                                        >Nueva Contraseña</Label
                                    >
                                    <Input
                                        id="password"
                                        v-model="form.password"
                                        type="password"
                                        placeholder="Mínimo 8 caracteres"
                                        :class="{
                                            'border-red-500':
                                                form.errors.password,
                                        }"
                                    />
                                    <p
                                        v-if="form.errors.password"
                                        class="mt-1 text-sm text-red-500"
                                    >
                                        {{ form.errors.password }}
                                    </p>
                                </div>

                                <div>
                                    <Label for="password_confirmation"
                                        >Confirmar Nueva Contraseña</Label
                                    >
                                    <Input
                                        id="password_confirmation"
                                        v-model="form.password_confirmation"
                                        type="password"
                                        placeholder="Confirma la nueva contraseña"
                                    />
                                </div>
                            </CardContent>
                        </Card>
                    </form>
                </div>

                <!-- Sidebar (Right) with Activity Log -->
                <div class="space-y-6">
                    <!-- Info Card -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Información</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-3 text-sm">
                            <div>
                                <p class="font-medium">Estado</p>
                                <Badge variant="default">Activo</Badge>
                            </div>
                            <div>
                                <p class="font-medium">Creado</p>
                                <p class="text-muted-foreground">
                                    {{ formatDate(user.created_at) }}
                                </p>
                            </div>
                            <div>
                                <p class="font-medium">Último cambio</p>
                                <p class="text-muted-foreground">
                                    {{ formatDate(user.updated_at) }}
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Activity Log -->
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <Clock class="h-4 w-4" />
                                Historial de Cambios
                            </CardTitle>
                            <CardDescription
                                >Últimos 20 cambios registrados</CardDescription
                            >
                        </CardHeader>
                        <CardContent>
                            <div
                                v-if="activities.length === 0"
                                class="py-4 text-center text-muted-foreground"
                            >
                                No hay cambios registrados
                            </div>
                            <div v-else class="space-y-4">
                                <div
                                    v-for="(activity, index) in activities"
                                    :key="index"
                                    class="border-l-2 border-muted pb-4 pl-4 last:pb-0"
                                >
                                    <div class="mb-1 flex items-center gap-2">
                                        <Badge
                                            :variant="
                                                getEventBadgeVariant(
                                                    activity.event,
                                                )
                                            "
                                            size="sm"
                                        >
                                            {{ getEventLabel(activity.event) }}
                                        </Badge>
                                        <span
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{
                                                formatDate(activity.created_at)
                                            }}
                                        </span>
                                    </div>

                                    <div
                                        v-if="activity.causer"
                                        class="mb-2 flex items-center gap-1 text-xs text-muted-foreground"
                                    >
                                        <UserIcon class="h-3 w-3" />
                                        {{ activity.causer.name }}
                                    </div>

                                    <div
                                        v-if="activity.properties.attributes"
                                        class="space-y-1 text-sm"
                                    >
                                        <template
                                            v-for="(value, key) in activity
                                                .properties.attributes"
                                            :key="key"
                                        >
                                            <div
                                                v-if="
                                                    activity.properties.old &&
                                                    activity.properties.old[
                                                        key
                                                    ] !== value
                                                "
                                            >
                                                <span class="font-medium"
                                                    >{{ key }}:</span
                                                >
                                                <span
                                                    class="text-red-500 line-through"
                                                    >{{
                                                        activity.properties.old[
                                                            key
                                                        ]
                                                    }}</span
                                                >
                                                →
                                                <span class="text-green-500">{{
                                                    value
                                                }}</span>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
