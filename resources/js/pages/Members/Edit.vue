<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import {Card, CardContent, CardDescription, CardHeader, CardTitle} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Save, Clock, User as UserIcon, Lock, UserPlus } from 'lucide-vue-next';
import type { BreadcrumbItem } from '@/types';

interface Company {
    id: number;
    trade_name: string;
}

interface Activity {
    description: string;
    event: string;
    properties: any;
    created_at: string;
    causer: {
        name: string;
        email: string;
    } | null;
}

interface Member {
    id: number;
    company_id: number;
    user_id: number | null;
    document_type: string;
    document_number: string;
    first_name: string;
    last_name: string;
    email: string | null;
    phone: string | null;
    mobile: string | null;
    address: string | null;
    district: string | null;
    province: string | null;
    department: string | null;
    birth_date: string | null;
    gender: string | null;
    emergency_contact_name: string | null;
    emergency_contact_phone: string | null;
    blood_type: string | null;
    medical_notes: string | null;
    allergies: string | null;
    status: string;
    created_at: string;
    updated_at: string;
}

interface Props {
    member: Member;
    companies: Company[];
    activities: Activity[];
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Miembros', href: '/members' },
    { title: `${props.member.first_name} ${props.member.last_name}`, href: `/members/${props.member.id}/edit` },
];

const form = useForm({
    company_id: props.member.company_id,
    document_type: props.member.document_type,
    document_number: props.member.document_number,
    first_name: props.member.first_name,
    last_name: props.member.last_name,
    email: props.member.email || '',
    phone: props.member.phone || '',
    mobile: props.member.mobile || '',
    address: props.member.address || '',
    district: props.member.district || '',
    province: props.member.province || '',
    department: props.member.department || '',
    birth_date: props.member.birth_date || '',
    gender: props.member.gender || '',
    emergency_contact_name: props.member.emergency_contact_name || '',
    emergency_contact_phone: props.member.emergency_contact_phone || '',
    blood_type: props.member.blood_type || '',
    medical_notes: props.member.medical_notes || '',
    allergies: props.member.allergies || '',
    status: props.member.status,
});

const portalForm = useForm({
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.put(`/members/${props.member.id}`);
};

const activatePortal = () => {
    portalForm.post(`/members/${props.member.id}/activate-portal`, {
        onSuccess: () => {
            portalForm.reset();
        },
    });
};

const formatDate = (date: string) => {
    return new Date(date).toLocaleString('es-PE', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const hasPortalAccess = props.member.user_id !== null;
</script>

<template>
    <Head :title="`Editar: ${member.first_name} ${member.last_name}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-4">
            <!-- Header -->
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight">
                        Editar Miembro
                    </h1>
                    <p class="text-muted-foreground">
                        {{ member.first_name }} {{ member.last_name }}
                    </p>
                </div>
                <Button variant="outline" @click="router.visit('/members')">
                    <ArrowLeft class="mr-2 h-4 w-4" />
                    Volver
                </Button>
            </div>

            <!-- Tabbed Layout -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Main Content with Tabs (Left) -->
                <div class="lg:col-span-2">
                    <Tabs default-value="member" class="w-full">
                        <TabsList class="grid w-full grid-cols-2">
                            <TabsTrigger value="member">
                                <UserPlus class="mr-2 h-4 w-4" />
                                Datos del Miembro
                            </TabsTrigger>
                            <TabsTrigger value="portal">
                                <Lock class="mr-2 h-4 w-4" />
                                Acceso Portal
                            </TabsTrigger>
                        </TabsList>

                        <!-- TAB 1: Member Data -->
                        <TabsContent value="member" class="space-y-6">
                            <form @submit.prevent="submit" class="space-y-6">
                                <!-- Personal Info -->
                                <Card>
                                    <CardHeader>
                                        <CardTitle>Información Personal</CardTitle>
                                    </CardHeader>
                                    <CardContent class="space-y-4">
                                        <div class="grid grid-cols-2 gap-4">
                                            <div>
                                                <Label for="document_type">Tipo Documento *</Label>
                                                <Select v-model="form.document_type">
                                                    <SelectTrigger>
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem value="DNI">DNI</SelectItem>
                                                        <SelectItem value="RUC">RUC</SelectItem>
                                                        <SelectItem value="CE">Carnet Extranjería</SelectItem>
                                                        <SelectItem value="Passport">Pasaporte</SelectItem>
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                            
                                            <div>
                                                <Label for="document_number">Número Documento *</Label>
                                                <Input
                                                    id="document_number"
                                                    v-model="form.document_number"
                                                    :class="{ 'border-red-500': form.errors.document_number }"
                                                />
                                                <p v-if="form.errors.document_number" class="text-sm text-red-500 mt-1">
                                                    {{ form.errors.document_number }}
                                                </p>
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-2 gap-4">
                                            <div>
                                                <Label for="first_name">Nombres *</Label>
                                                <Input
                                                    id="first_name"
                                                    v-model="form.first_name"
                                                    :class="{ 'border-red-500': form.errors.first_name }"
                                                />
                                                <p v-if="form.errors.first_name" class="text-sm text-red-500 mt-1">
                                                    {{ form.errors.first_name }}
                                                </p>
                                            </div>

                                            <div>
                                                <Label for="last_name">Apellidos *</Label>
                                                <Input
                                                    id="last_name"
                                                    v-model="form.last_name"
                                                    :class="{ 'border-red-500': form.errors.last_name }"
                                                />
                                                <p v-if="form.errors.last_name" class="text-sm text-red-500 mt-1">
                                                    {{ form.errors.last_name }}
                                                </p>
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-3 gap-4">
                                            <div>
                                                <Label for="birth_date">Fecha Nacimiento</Label>
                                                <Input
                                                    id="birth_date"
                                                    type="date"
                                                    v-model="form.birth_date"
                                                />
                                            </div>

                                            <div>
                                                <Label for="gender">Género</Label>
                                                <Select v-model="form.gender">
                                                    <SelectTrigger>
                                                        <SelectValue placeholder="Seleccionar..." />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem value="M">Masculino</SelectItem>
                                                        <SelectItem value="F">Femenino</SelectItem>
                                                        <SelectItem value="Other">Otro</SelectItem>
                                                    </SelectContent>
                                                </Select>
                                            </div>

                                            <div>
                                                <Label for="blood_type">Tipo Sangre</Label>
                                                <Input
                                                    id="blood_type"
                                                    v-model="form.blood_type"
                                                    placeholder="Ej: O+"
                                                />
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-2 gap-4">
                                            <div>
                                                <Label for="company_id">Compañía/Sucursal *</Label>
                                                <Select v-model="form.company_id">
                                                    <SelectTrigger :class="{ 'border-red-500': form.errors.company_id }">
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem 
                                                            v-for="company in companies" 
                                                            :key="company.id"
                                                            :value="company.id"
                                                        >
                                                            {{ company.trade_name }}
                                                        </SelectItem>
                                                    </SelectContent>
                                                </Select>
                                            </div>

                                            <div>
                                                <Label for="status">Estado *</Label>
                                                <Select v-model="form.status">
                                                    <SelectTrigger>
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem value="active">Activo</SelectItem>
                                                        <SelectItem value="inactive">Inactivo</SelectItem>
                                                        <SelectItem value="suspended">Suspendido</SelectItem>
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>

                                <!-- Contact Info -->
                                <Card>
                                    <CardHeader>
                                        <CardTitle>Contacto</CardTitle>
                                    </CardHeader>
                                    <CardContent class="space-y-4">
                                        <div class="grid grid-cols-3 gap-4">
                                            <div>
                                                <Label for="email">Email</Label>
                                                <Input
                                                    id="email"
                                                    type="email"
                                                    v-model="form.email"
                                                />
                                            </div>
                                            <div>
                                                <Label for="phone">Teléfono</Label>
                                                <Input id="phone" v-model="form.phone" />
                                            </div>
                                            <div>
                                                <Label for="mobile">Celular</Label>
                                                <Input id="mobile" v-model="form.mobile" />
                                            </div>
                                        </div>

                                        <div>
                                            <Label for="address">Dirección</Label>
                                            <Input id="address" v-model="form.address" />
                                        </div>

                                        <div class="grid grid-cols-3 gap-4">
                                            <div>
                                                <Label for="district">Distrito</Label>
                                                <Input id="district" v-model="form.district" />
                                            </div>
                                            <div>
                                                <Label for="province">Provincia</Label>
                                                <Input id="province" v-model="form.province" />
                                            </div>
                                            <div>
                                                <Label for="department">Departamento</Label>
                                                <Input id="department" v-model="form.department" />
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>

                                <!-- Emergency Contact -->
                                <Card>
                                    <CardHeader>
                                        <CardTitle>Contacto de Emergencia</CardTitle>
                                    </CardHeader>
                                    <CardContent class="space-y-4">
                                        <div class="grid grid-cols-2 gap-4">
                                            <div>
                                                <Label for="emergency_contact_name">Nombre</Label>
                                                <Input
                                                    id="emergency_contact_name"
                                                    v-model="form.emergency_contact_name"
                                                />
                                            </div>
                                            <div>
                                                <Label for="emergency_contact_phone">Teléfono</Label>
                                                <Input
                                                    id="emergency_contact_phone"
                                                    v-model="form.emergency_contact_phone"
                                                />
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>

                                <!-- Medical Info -->
                                <Card>
                                    <CardHeader>
                                        <CardTitle>Información Médica</CardTitle>
                                    </CardHeader>
                                    <CardContent class="space-y-4">
                                        <div>
                                            <Label for="medical_notes">Notas Médicas</Label>
                                            <Textarea
                                                id="medical_notes"
                                                v-model="form.medical_notes"
                                                placeholder="Condiciones médicas, lesiones, etc."
                                            />
                                        </div>

                                        <div>
                                            <Label for="allergies">Alergias</Label>
                                            <Textarea
                                                id="allergies"
                                                v-model="form.allergies"
                                                placeholder="Alergias conocidas"
                                            />
                                        </div>
                                    </CardContent>
                                </Card>

                                <!-- Actions -->
                                <div class="flex justify-end gap-2">
                                    <Button type="button" variant="outline" @click="router.visit('/members')">
                                        Cancelar
                                    </Button>
                                    <Button type="submit" :disabled="form.processing">
                                        <Save class="mr-2 h-4 w-4" />
                                        Guardar Cambios
                                    </Button>
                                </div>
                            </form>
                        </TabsContent>

                        <!-- TAB 2: Portal Access -->
                        <TabsContent value="portal" class="space-y-6">
                            <Card>
                                <CardHeader>
                                    <CardTitle>Acceso al Portal de Miembros</CardTitle>
                                    <CardDescription>
                                        Permite que el miembro acceda al portal con su propio usuario
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <div v-if="hasPortalAccess" class="space-y-4">
                                        <div class="rounded-lg border border-green-200 bg-green-50 p-4">
                                            <div class="flex items-center gap-2">
                                                <Lock class="h-5 w-5 text-green-600" />
                                                <p class="font-medium text-green-900">Acceso al portal activado</p>
                                            </div>
                                            <p class="mt-2 text-sm text-green-700">
                                                Este miembro ya tiene acceso al portal del gimnasio.
                                            </p>
                                        </div>
                                    </div>

                                    <form v-else @submit.prevent="activatePortal" class="space-y-4">
                                        <div class="rounded-lg border border-blue-200 bg-blue-50 p-4 mb-4">
                                            <p class="text-sm text-blue-900">
                                                <strong>Email:</strong> {{ member.email || 'Sin email registrado' }}
                                            </p>
                                            <p class="text-xs text-blue-700 mt-1">
                                                Se usará este email para el login
                                            </p>
                                        </div>

                                        <div>
                                            <Label for="password">Contraseña *</Label>
                                            <Input
                                                id="password"
                                                type="password"
                                                v-model="portalForm.password"
                                                :class="{ 'border-red-500': portalForm.errors.password }"
                                                :disabled="!member.email"
                                            />
                                            <p v-if="portalForm.errors.password" class="text-sm text-red-500 mt-1">
                                                {{ portalForm.errors.password }}
                                            </p>
                                        </div>

                                        <div>
                                            <Label for="password_confirmation">Confirmar Contraseña *</Label>
                                            <Input
                                                id="password_confirmation"
                                                type="password"
                                                v-model="portalForm.password_confirmation"
                                                :disabled="!member.email"
                                            />
                                        </div>

                                        <div v-if="!member.email" class="rounded-lg border border-yellow-200 bg-yellow-50 p-4">
                                            <p class="text-sm text-yellow-900">
                                                ⚠️ Este miembro no tiene email registrado. Por favor, agrega un email en la pestaña "Datos del Miembro" primero.
                                            </p>
                                        </div>

                                        <div class="flex justify-end">
                                            <Button 
                                                type="submit" 
                                                :disabled="portalForm.processing || !member.email"
                                            >
                                                <UserPlus class="mr-2 h-4 w-4" />
                                                Activar Acceso al Portal
                                            </Button>
                                        </div>
                                    </form>
                                </CardContent>
                            </Card>
                        </TabsContent>
                    </Tabs>
                </div>

                <!-- Activity Log Sidebar (Right) -->
                <div class="lg:col-span-1">
                    <Card class="sticky top-4">
                        <CardHeader>
                            <CardTitle>Historial de Cambios</CardTitle>
                            <CardDescription>Últimas 20 actividades</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="space-y-4">
                                <div
                                    v-for="(activity, index) in activities"
                                    :key="index"
                                    class="flex gap-3 text-sm"
                                >
                                    <div class="flex-shrink-0">
                                        <Clock class="h-4 w-4 text-muted-foreground" />
                                    </div>
                                    <div class="flex-1 space-y-1">
                                        <p class="font-medium">{{ activity.description }}</p>
                                        <p class="text-xs text-muted-foreground">
                                            {{ formatDate(activity.created_at) }}
                                        </p>
                                        <p v-if="activity.causer" class="text-xs text-muted-foreground flex items-center gap-1">
                                            <UserIcon class="h-3 w-3" />
                                            {{ activity.causer.name }}
                                        </p>
                                    </div>
                                </div>

                                <div v-if="activities.length === 0" class="text-center text-sm text-muted-foreground py-4">
                                    No hay actividades registradas
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
