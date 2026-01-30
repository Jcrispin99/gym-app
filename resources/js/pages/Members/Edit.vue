<script setup lang="ts">
import { ref } from 'vue';
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
import { Badge } from '@/components/ui/badge';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
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
import { Head, router, useForm } from '@inertiajs/vue3';
import FormPageHeader from '@/components/FormPageHeader.vue';
import {
    Save,
    Clock,
    User as UserIcon,
    Lock,
    UserPlus,
    CreditCard,
    Plus,
    Check,
    Calendar,
    MoreVertical,
    Snowflake,
    X,
} from 'lucide-vue-next';
import type { BreadcrumbItem } from '@/types';

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

interface MembershipPlan {
    id: number;
    name: string;
    description: string | null;
    duration_days: number;
    price: number | string;
    max_entries_per_month: number | null;
    time_restricted: boolean;
    allowed_time_start: string | null;
    allowed_time_end: string | null;
    allows_freezing: boolean;
    max_freeze_days: number;
}

interface MembershipSubscription {
    id: number;
    membership_plan_id: number;
    plan: MembershipPlan;
    start_date: string;
    end_date: string;
    amount_paid: number | string;
    payment_method: string;
    payment_reference: string | null;
    status: 'active' | 'frozen' | 'expired' | 'cancelled';
    entries_this_month: number;
    remaining_freeze_days: number;
    created_at: string;
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
    subscriptions?: MembershipSubscription[];
    created_at: string;
    updated_at: string;
}

interface Props {
    member: Member;
    activities: Activity[];
    membershipPlans: MembershipPlan[];
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

const subscriptionForm = useForm({
    partner_id: props.member.id,
    membership_plan_id: null as number | null,
    start_date: '',
    payment_method: 'efectivo',
    payment_reference: '',
    amount_paid: 0,
    notes: '',
});

const showSubscriptionModal = ref(false);
const selectedPlan = ref<MembershipPlan | null>(null);

const showFreezeModal = ref(false);
const showCancelDialog = ref(false);
const selectedSubscription = ref<MembershipSubscription | null>(null);

const freezeForm = useForm({
    subscription_id: null as number | null,
    days: 1,
    reason: '',
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

const openSubscriptionModal = () => {
    subscriptionForm.reset();
    selectedPlan.value = null;
    showSubscriptionModal.value = true;
};

const selectPlan = (plan: MembershipPlan) => {
    selectedPlan.value = plan;
    subscriptionForm.membership_plan_id = plan.id;
    const price = typeof plan.price === 'string' ? parseFloat(plan.price) : plan.price;
    subscriptionForm.amount_paid = price;
};

const createSubscription = () => {
    subscriptionForm.post('/subscriptions', {
        onSuccess: () => {
            showSubscriptionModal.value = false;
            subscriptionForm.reset();
            selectedPlan.value = null;
        },
    });
};

const openFreezeModal = (subscription: MembershipSubscription) => {
    selectedSubscription.value = subscription;
    freezeForm.subscription_id = subscription.id;
    freezeForm.days = 1;
    freezeForm.reason = '';
    showFreezeModal.value = true;
};

const freezeSubscription = () => {
    if (!selectedSubscription.value) return;
    
    freezeForm.post(`/subscriptions/${selectedSubscription.value.id}/freeze`, {
        onSuccess: () => {
            showFreezeModal.value = false;
            freezeForm.reset();
            selectedSubscription.value = null;
        },
    });
};

const unfreezeSubscription = (subscription: MembershipSubscription) => {
    router.post(`/subscriptions/${subscription.id}/unfreeze`);
};

const openCancelDialog = (subscription: MembershipSubscription) => {
    selectedSubscription.value = subscription;
    showCancelDialog.value = true;
};

const cancelSubscription = () => {
    if (!selectedSubscription.value) return;
    
    router.delete(`/subscriptions/${selectedSubscription.value.id}`, {
        onSuccess: () => {
            showCancelDialog.value = false;
            selectedSubscription.value = null;
        },
    });
};

const formatPrice = (price: number | string) => {
    const numPrice = typeof price === 'string' ? parseFloat(price) : price;
    return `S/ ${numPrice.toFixed(2)}`;
};

const getDurationLabel = (days: number) => {
    if (days === 30) return '1 mes';
    if (days === 60) return '2 meses';
    if (days === 90) return '3 meses';
    if (days === 180) return '6 meses';
    if (days === 365) return '1 año';
    return `${days} días`;
};

const getStatusBadge = (status: string) => {
    const badges: Record<string, { variant: 'default' | 'secondary' | 'outline' | 'destructive', label: string }> = {
        active: { variant: 'default', label: 'Activa' },
        frozen: { variant: 'secondary', label: 'Congelada' },
        expired: { variant: 'outline', label: 'Expirada' },
        cancelled: { variant: 'destructive', label: 'Cancelada' },
    };
    return badges[status] || badges.active;
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
            <FormPageHeader
                title="Editar Miembro"
                :description="`${member.first_name} ${member.last_name}`"
                back-href="/members"
            >
                <template #actions>
                    <Button @click="submit" :disabled="form.processing">
                        <Save class="mr-2 h-4 w-4" />
                        {{ form.processing ? 'Guardando...' : 'Guardar Cambios' }}
                    </Button>
                </template>
            </FormPageHeader>

            <!-- Tabbed Layout -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Main Content with Tabs (Left) -->
                <div class="lg:col-span-2">
                    <Tabs default-value="member" class="w-full">
                        <TabsList class="grid w-full grid-cols-3">
                            <TabsTrigger value="member">
                                <UserIcon class="mr-2 h-4 w-4" />
                                Información
                            </TabsTrigger>
                            <TabsTrigger value="portal">
                                <Lock class="mr-2 h-4 w-4" />
                                Acceso Portal
                            </TabsTrigger>
                            <TabsTrigger value="subscriptions">
                                <CreditCard class="mr-2 h-4 w-4" />
                                Suscripciones
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

                        <!-- TAB 3: Subscriptions -->
                        <TabsContent value="subscriptions" class="space-y-4">
                            <Card>
                                <CardHeader>
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <CardTitle>Suscripciones</CardTitle>
                                            <CardDescription>
                                                Gestiona las membresías activas y pasadas
                                            </CardDescription>
                                        </div>
                                        <Button @click="openSubscriptionModal">
                                            <Plus class="mr-2 h-4 w-4" />
                                            Nueva Suscripción
                                        </Button>
                                    </div>
                                </CardHeader>
                                <CardContent class="space-y-4">
                                    <!-- Active Subscriptions -->
                                    <div v-if="member.subscriptions && member.subscriptions.length > 0">
                                        <div v-for="subscription in member.subscriptions" :key="subscription.id" 
                                             class="border rounded-lg p-4 space-y-3">
                                            <div class="flex items-start justify-between">
                                                <div class="space-y-1 flex-1">
                                                    <h4 class="font-semibold text-lg">{{ subscription.plan.name }}</h4>
                                                    <p v-if="subscription.plan.description" class="text-sm text-muted-foreground">
                                                        {{ subscription.plan.description }}
                                                    </p>
                                                </div>
                                                <div class="flex items-center gap-2">
                                                    <Badge :variant="getStatusBadge(subscription.status).variant">
                                                        {{ getStatusBadge(subscription.status).label }}
                                                    </Badge>
                                                    
                                                    <!-- Actions -->
                                                    <DropdownMenu v-if="subscription.status !== 'cancelled' && subscription.status !== 'expired'">
                                                        <DropdownMenuTrigger as-child>
                                                            <Button variant="ghost" size="icon">
                                                                <MoreVertical class="h-4 w-4" />
                                                            </Button>
                                                        </DropdownMenuTrigger>
                                                        <DropdownMenuContent align="end">
                                                            <DropdownMenuItem 
                                                                v-if="subscription.status === 'active' && subscription.plan.allows_freezing && subscription.remaining_freeze_days > 0"
                                                                @click="openFreezeModal(subscription)"
                                                            >
                                                                <Snowflake class="mr-2 h-4 w-4" />
                                                                Congelar
                                                            </DropdownMenuItem>
                                                            <DropdownMenuItem 
                                                                v-if="subscription.status === 'frozen'"
                                                                @click="unfreezeSubscription(subscription)"
                                                            >
                                                                <Check class="mr-2 h-4 w-4" />
                                                                Descongelar
                                                            </DropdownMenuItem>
                                                            <DropdownMenuItem 
                                                                @click="openCancelDialog(subscription)"
                                                                class="text-red-600"
                                                            >
                                                                <X class="mr-2 h-4 w-4" />
                                                                Cancelar
                                                            </DropdownMenuItem>
                                                        </DropdownMenuContent>
                                                    </DropdownMenu>
                                                </div>
                                            </div>

                                            <div class="grid grid-cols-2 gap-4 text-sm">
                                                <div>
                                                    <p class="text-muted-foreground">Duración</p>
                                                    <p class="font-medium">{{ getDurationLabel(subscription.plan.duration_days) }}</p>
                                                </div>
                                                <div>
                                                    <p class="text-muted-foreground">Precio pagado</p>
                                                    <p class="font-medium">{{ formatPrice(subscription.amount_paid) }}</p>
                                                </div>
                                                <div>
                                                    <p class="text-muted-foreground">Fecha inicio</p>
                                                    <p class="font-medium">{{ formatDate(subscription.start_date) }}</p>
                                                </div>
                                                <div>
                                                    <p class="text-muted-foreground">Fecha fin</p>
                                                    <p class="font-medium">{{ formatDate(subscription.end_date) }}</p>
                                                </div>
                                                <div>
                                                    <p class="text-muted-foreground">Método de pago</p>
                                                    <p class="font-medium capitalize">{{ subscription.payment_method }}</p>
                                                </div>
                                                <div>
                                                    <p class="text-muted-foreground">Entradas este mes</p>
                                                    <p class="font-medium">{{ subscription.entries_this_month }}</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Empty State -->
                                    <div v-else class="text-center py-12">
                                        <CreditCard class="mx-auto h-12 w-12 text-muted-foreground" />
                                        <h3 class="mt-4 text-lg font-semibold">Sin suscripciones</h3>
                                        <p class="mt-2 text-sm text-muted-foreground">
                                            Este miembro aún no tiene ninguna suscripción activa.
                                        </p>
                                        <Button @click="openSubscriptionModal" class="mt-4">
                                            <Plus class="mr-2 h-4 w-4" />
                                            Crear primera suscripción
                                        </Button>
                                    </div>
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

        <!-- Subscription Creation Modal -->
        <Dialog :open="showSubscriptionModal" @update:open="showSubscriptionModal = $event">
            <DialogContent class="max-w-4xl max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Nueva Suscripción</DialogTitle>
                    <DialogDescription>
                        Selecciona un plan para {{ member.first_name }} {{ member.last_name }}
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="createSubscription" class="space-y-6">
                    <!-- Plan Selection Cards -->
                    <div>
                        <Label class="text-base font-semibold mb-3 block">Seleccionar Plan</Label>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            <div
                                v-for="plan in membershipPlans"
                                :key="plan.id"
                                @click="selectPlan(plan)"
                                :class="[
                                    'relative cursor-pointer rounded-lg border-2 p-4 transition-all hover:shadow-md',
                                    selectedPlan?.id === plan.id
                                        ? 'border-primary bg-primary/5 ring-2 ring-primary'
                                        : 'border-border hover:border-primary/50'
                                ]"
                            >
                                <div class="space-y-3">
                                    <div>
                                        <h4 class="font-semibold text-lg">{{ plan.name }}</h4>
                                        <p v-if="plan.description" class="text-xs text-muted-foreground line-clamp-2">
                                            {{ plan.description }}
                                        </p>
                                    </div>

                                    <div class="space-y-2">
                                        <div class="text-2xl font-bold text-primary">
                                            {{ formatPrice(plan.price) }}
                                        </div>
                                        
                                        <div class="space-y-1 text-sm">
                                            <div class="flex items-center gap-2">
                                                <Calendar class="h-3 w-3 text-muted-foreground" />
                                                <span>{{ getDurationLabel(plan.duration_days) }}</span>
                                            </div>
                                            <div v-if="plan.max_entries_per_month" class="flex items-center gap-2">
                                                <CreditCard class="h-3 w-3 text-muted-foreground" />
                                                <span>{{ plan.max_entries_per_month }} entradas/mes</span>
                                            </div>
                                            <div v-else class="flex items-center gap-2">
                                                <CreditCard class="h-3 w-3 text-muted-foreground" />
                                                <span class="font-medium">Entradas ilimitadas</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div v-if="selectedPlan?.id === plan.id" class="absolute top-2 right-2">
                                        <div class="rounded-full bg-primary p-1">
                                            <Check class="h-4 w-4 text-primary-foreground" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <p v-if="subscriptionForm.errors.membership_plan_id" class="text-sm text-red-500 mt-2">
                            {{ subscriptionForm.errors.membership_plan_id }}
                        </p>
                    </div>

                    <!-- Payment Details -->
                    <div v-if="selectedPlan" class="space-y-4 border-t pt-4">
                        <h3 class="text-base font-semibold">Detalles de Pago</h3>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <Label for="start_date">Fecha de Inicio</Label>
                                <Input
                                    id="start_date"
                                    v-model="subscriptionForm.start_date"
                                    type="date"
                                    :min="new Date().toISOString().split('T')[0]"
                                />
                                <p class="text-xs text-muted-foreground">
                                    Deja vacío para iniciar hoy
                                </p>
                            </div>

                            <div class="space-y-2">
                                <Label for="payment_method">Método de Pago *</Label>
                                <Select v-model="subscriptionForm.payment_method">
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="efectivo">Efectivo</SelectItem>
                                        <SelectItem value="tarjeta">Tarjeta</SelectItem>
                                        <SelectItem value="transferencia">Transferencia</SelectItem>
                                        <SelectItem value="yape">Yape</SelectItem>
                                        <SelectItem value="plin">Plin</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <div class="space-y-2">
                                <Label for="amount_paid">Monto Pagado (S/) *</Label>
                                <Input
                                    id="amount_paid"
                                    v-model="subscriptionForm.amount_paid"
                                    type="number"
                                    step="0.01"
                                />
                            </div>
                        </div>

                        <div class="space-y-2">
                            <Label for="payment_reference">Referencia de Pago</Label>
                            <Input
                                id="payment_reference"
                                v-model="subscriptionForm.payment_reference"
                                placeholder="Número de operación, voucher, etc."
                            />
                        </div>

                        <div class="space-y-2">
                            <Label for="notes">Notas (opcional)</Label>
                            <Textarea
                                id="notes"
                                v-model="subscriptionForm.notes"
                                rows="3"
                                placeholder="Observaciones adicionales..."
                            />
                        </div>
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="showSubscriptionModal = false"
                            :disabled="subscriptionForm.processing"
                        >
                            Cancelar
                        </Button>
                        <Button
                            type="submit"
                            :disabled="!selectedPlan || subscriptionForm.processing"
                        >
                            {{ subscriptionForm.processing ? 'Creando...' : 'Crear Suscripción' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Freeze Subscription Modal -->
        <Dialog :open="showFreezeModal" @update:open="showFreezeModal = $event">
            <DialogContent class="max-w-md">
                <DialogHeader>
                    <DialogTitle>Congelar Suscripción</DialogTitle>
                    <DialogDescription>
                        El miembro no podrá ingresar durante el período de congelamiento.
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="freezeSubscription" class="space-y-4">
                    <div v-if="selectedSubscription" class="bg-muted p-3 rounded-lg text-sm">
                        <p><strong>Plan:</strong> {{ selectedSubscription.plan.name }}</p>
                        <p><strong>Días disponibles:</strong> {{ selectedSubscription.remaining_freeze_days }}</p>
                    </div>

                    <div class="space-y-2">
                        <Label for="freeze_days">Días a congelar *</Label>
                        <Input
                            id="freeze_days"
                            v-model.number="freezeForm.days"
                            type="number"
                            min="1"
                            :max="selectedSubscription?.remaining_freeze_days || 1"
                            required
                        />
                        <p v-if="freezeForm.errors.days" class="text-sm text-red-500">
                            {{ freezeForm.errors.days }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label for="freeze_reason">Motivo (opcional)</Label>
                        <Textarea
                            id="freeze_reason"
                            v-model="freezeForm.reason"
                            rows="3"
                            placeholder="Ej: Viaje, enfermedad, etc."
                        />
                    </div>

                    <div v-if="selectedSubscription && freezeForm.days > 0" class="bg-blue-50 border border-blue-200 p-3 rounded text-sm">
                        <p class="font-semibold mb-1">📅 Nueva fecha de fin:</p>
                        <p>{{ new Date(selectedSubscription.end_date).toLocaleDateString('es-PE') }} 
                           + {{ freezeForm.days }} días = 
                           <strong>{{ new Date(new Date(selectedSubscription.end_date).getTime() + freezeForm.days * 24 * 60 * 60 * 1000).toLocaleDateString('es-PE') }}</strong>
                        </p>
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="showFreezeModal = false"
                            :disabled="freezeForm.processing"
                        >
                            Cancelar
                        </Button>
                        <Button
                            type="submit"
                            :disabled="freezeForm.processing || !freezeForm.days"
                        >
                            {{ freezeForm.processing ? 'Congelando...' : 'Congelar Suscripción' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Cancel Subscription Confirmation -->
        <AlertDialog :open="showCancelDialog" @update:open="showCancelDialog = $event">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>¿Cancelar suscripción?</AlertDialogTitle>
                    <AlertDialogDescription>
                        Esta acción es permanente. La suscripción no podrá reactivarse.
                        
                        <div v-if="selectedSubscription" class="mt-4 p-3 bg-muted rounded-lg text-sm">
                            <p><strong>Plan:</strong> {{ selectedSubscription.plan.name }}</p>
                            <p><strong>Fecha de fin:</strong> {{ formatDate(selectedSubscription.end_date) }}</p>
                            <p><strong>Precio pagado:</strong> {{ formatPrice(selectedSubscription.amount_paid) }}</p>
                        </div>
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel>No, mantener activa</AlertDialogCancel>
                    <AlertDialogAction @click="cancelSubscription" class="bg-red-600 hover:bg-red-700">
                        Sí, cancelar suscripción
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    </AppLayout>
</template>
