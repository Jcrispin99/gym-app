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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { Clock, Save, User } from 'lucide-vue-next';
import { computed, ref } from 'vue';

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
    business_name: string;
    trade_name: string;
    ruc: string;
    logo_url: string | null;
    address: string | null;
    phone: string | null;
    email: string | null;
    ubigeo: string | null;
    urbanization: string | null;
    department: string | null;
    province: string | null;
    district: string | null;
    parent_id: number | null;
    branch_code: string | null;
    is_main_office: boolean;
    created_at: string;
    updated_at: string;
}

interface Props {
    company: Company;
    main_office: {
        id: number;
        trade_name: string;
    } | null;
    activities: Activity[];
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Compañías', href: '/companies' },
    {
        title: props.company.trade_name,
        href: `/companies/${props.company.id}/edit`,
    },
];

const form = useForm({
    business_name: props.company.business_name,
    trade_name: props.company.trade_name,
    ruc: props.company.ruc,
    logo: null as File | null,
    address: props.company.address || '',
    phone: props.company.phone || '',
    email: props.company.email || '',
    ubigeo: props.company.ubigeo || '',
    urbanization: props.company.urbanization || '',
    department: props.company.department || '',
    province: props.company.province || '',
    district: props.company.district || '',
    parent_id: props.company.parent_id,
    branch_code: props.company.branch_code || '',
    is_main_office: props.company.is_main_office,
});

const logoPreviewUrl = ref<string | null>(null);

const onLogoChange = (event: Event) => {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0] ?? null;
    form.logo = file;

    if (logoPreviewUrl.value) {
        URL.revokeObjectURL(logoPreviewUrl.value);
        logoPreviewUrl.value = null;
    }

    if (file) {
        logoPreviewUrl.value = URL.createObjectURL(file);
    }
};

const currentLogoUrl = computed(
    () => logoPreviewUrl.value ?? props.company.logo_url ?? null,
);

const submit = () => {
    form.put(`/companies/${props.company.id}`, {
        preserveScroll: true,
        forceFormData: true,
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
    <Head :title="`Editar - ${company.trade_name}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-4">
            <FormPageHeader
                :title="company.trade_name"
                :description="company.business_name"
                back-href="/companies"
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
                        <!-- Información General -->
                        <Card>
                            <CardHeader>
                                <CardTitle>Información General</CardTitle>
                                <CardDescription
                                    >Datos principales de la
                                    compañía</CardDescription
                                >
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div>
                                    <Label for="logo">Logo</Label>
                                    <div class="mt-2 flex items-center gap-4">
                                        <div
                                            class="flex h-14 w-14 items-center justify-center overflow-hidden rounded border bg-muted"
                                        >
                                            <img
                                                v-if="currentLogoUrl"
                                                :src="currentLogoUrl"
                                                alt="Logo"
                                                class="h-full w-full object-contain"
                                            />
                                        </div>
                                        <input
                                            id="logo"
                                            type="file"
                                            accept="image/*"
                                            @change="onLogoChange"
                                            class="block w-full text-sm"
                                        />
                                    </div>
                                    <p
                                        v-if="form.errors.logo"
                                        class="mt-1 text-sm text-red-500"
                                    >
                                        {{ form.errors.logo }}
                                    </p>
                                </div>

                                <div>
                                    <Label for="business_name"
                                        >Razón Social *</Label
                                    >
                                    <Input
                                        id="business_name"
                                        v-model="form.business_name"
                                        :class="{
                                            'border-red-500':
                                                form.errors.business_name,
                                        }"
                                    />
                                    <p
                                        v-if="form.errors.business_name"
                                        class="mt-1 text-sm text-red-500"
                                    >
                                        {{ form.errors.business_name }}
                                    </p>
                                </div>

                                <div>
                                    <Label for="trade_name"
                                        >Nombre Comercial *</Label
                                    >
                                    <Input
                                        id="trade_name"
                                        v-model="form.trade_name"
                                        :class="{
                                            'border-red-500':
                                                form.errors.trade_name,
                                        }"
                                    />
                                    <p
                                        v-if="form.errors.trade_name"
                                        class="mt-1 text-sm text-red-500"
                                    >
                                        {{ form.errors.trade_name }}
                                    </p>
                                </div>

                                <div>
                                    <Label for="ruc">RUC *</Label>
                                    <Input
                                        id="ruc"
                                        v-model="form.ruc"
                                        maxlength="11"
                                        :class="{
                                            'border-red-500': form.errors.ruc,
                                        }"
                                    />
                                    <p
                                        v-if="form.errors.ruc"
                                        class="mt-1 text-sm text-red-500"
                                    >
                                        {{ form.errors.ruc }}
                                    </p>
                                </div>

                                <div>
                                    <Label for="parent_id"
                                        >¿Es una sucursal?</Label
                                    >
                                    <Select v-model="form.parent_id">
                                        <SelectTrigger>
                                            <SelectValue
                                                placeholder="Seleccionar..."
                                            />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem :value="null"
                                                >No - Es casa matriz</SelectItem
                                            >
                                            <SelectItem
                                                v-if="main_office"
                                                :value="main_office.id"
                                            >
                                                Sí - Sucursal de
                                                {{ main_office.trade_name }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div v-if="form.parent_id">
                                    <Label for="branch_code"
                                        >Código de Sucursal *</Label
                                    >
                                    <Input
                                        id="branch_code"
                                        v-model="form.branch_code"
                                        :class="{
                                            'border-red-500':
                                                form.errors.branch_code,
                                        }"
                                    />
                                    <p
                                        v-if="form.errors.branch_code"
                                        class="mt-1 text-sm text-red-500"
                                    >
                                        {{ form.errors.branch_code }}
                                    </p>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Ubicación -->
                        <Card>
                            <CardHeader>
                                <CardTitle>Ubicación</CardTitle>
                                <CardDescription
                                    >Dirección y ubicación
                                    geográfica</CardDescription
                                >
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div>
                                    <Label for="address">Dirección</Label>
                                    <Input
                                        id="address"
                                        v-model="form.address"
                                    />
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <Label for="department"
                                            >Departamento</Label
                                        >
                                        <Input
                                            id="department"
                                            v-model="form.department"
                                        />
                                    </div>
                                    <div>
                                        <Label for="province">Provincia</Label>
                                        <Input
                                            id="province"
                                            v-model="form.province"
                                        />
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <Label for="district">Distrito</Label>
                                        <Input
                                            id="district"
                                            v-model="form.district"
                                        />
                                    </div>
                                    <div>
                                        <Label for="ubigeo">Ubigeo</Label>
                                        <Input
                                            id="ubigeo"
                                            v-model="form.ubigeo"
                                            maxlength="6"
                                        />
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Contacto -->
                        <Card>
                            <CardHeader>
                                <CardTitle>Contacto</CardTitle>
                                <CardDescription
                                    >Información de contacto</CardDescription
                                >
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div>
                                    <Label for="phone">Teléfono</Label>
                                    <Input id="phone" v-model="form.phone" />
                                </div>
                                <div>
                                    <Label for="email">Email</Label>
                                    <Input
                                        id="email"
                                        v-model="form.email"
                                        type="email"
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
                                <p class="font-medium">Tipo</p>
                                <p class="text-muted-foreground">
                                    {{
                                        company.is_main_office
                                            ? 'Casa Matriz'
                                            : 'Sucursal'
                                    }}
                                </p>
                            </div>
                            <div>
                                <p class="font-medium">Creado</p>
                                <p class="text-muted-foreground">
                                    {{ formatDate(company.created_at) }}
                                </p>
                            </div>
                            <div>
                                <p class="font-medium">Último cambio</p>
                                <p class="text-muted-foreground">
                                    {{ formatDate(company.updated_at) }}
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
                                        <User class="h-3 w-3" />
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
