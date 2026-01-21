<script setup lang="ts">
import FormPageHeader from '@/components/FormPageHeader.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import {
    Calendar,
    Clock,
    Search,
    TrendingUp,
    UserCheck,
    XCircle,
} from 'lucide-vue-next';
import { ref } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Asistencias', href: '/attendances' },
    { title: 'Check-In', href: '/attendances/check-in' },
];

interface MembershipPlan {
    id: number;
    name: string;
    max_entries_per_day: number | null;
    max_entries_per_month: number | null;
}

interface MembershipSubscription {
    id: number;
    plan: MembershipPlan;
    end_date: string;
    status: string;
    entries_this_month: number;
}

interface Partner {
    id: number;
    full_name?: string;
    first_name?: string;
    last_name?: string;
    business_name?: string;
    document_number: string;
    email: string | null;
    photo_url: string | null;
    activeSubscription: MembershipSubscription | null;
}

interface ValidationResult {
    allowed: boolean;
    message: string;
    reason?: string;
    subscription?: MembershipSubscription;
}

const dni = ref('');
const searching = ref(false);
const partner = ref<Partner | null>(null);
const validation = ref<ValidationResult | null>(null);
const showResult = ref(false);

const searchByDni = async () => {
    if (!dni.value) return;

    searching.value = true;
    showResult.value = false;
    partner.value = null;
    validation.value = null;

    try {
        const response = await axios.get('/attendances/lookup-dni', {
            params: { dni: dni.value },
        });

        if (response.data.found) {
            partner.value = response.data.partner;
            validation.value = response.data.validation;
        } else {
            validation.value = {
                allowed: false,
                message: response.data.message,
            };
        }

        showResult.value = true;
    } catch (error) {
        console.error('Error looking up DNI:', error);
        validation.value = {
            allowed: false,
            message: 'Error al buscar el DNI',
        };
        showResult.value = true;
    } finally {
        searching.value = false;
    }
};

const registerCheckIn = (force = false) => {
    if (!partner.value) return;

    router.post(
        '/attendances/check-in',
        {
            partner_id: partner.value.id,
            force: force,
        },
        {
            onSuccess: () => {
                // Reset form
                dni.value = '';
                partner.value = null;
                validation.value = null;
                showResult.value = false;
            },
        },
    );
};

const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString('es-PE', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
};

const getEntriesToday = (): number => {
    // This would come from backend in real implementation
    return 0;
};

const getPartnerDisplayName = (p: Partner): string => {
    const name =
        p.business_name ||
        [p.first_name, p.last_name].filter(Boolean).join(' ') ||
        p.full_name ||
        '';
    return name.trim();
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex max-w-4xl flex-col gap-4 p-4">
            <FormPageHeader
                title="Check-In de Miembros"
                description="Registra la entrada de miembros por DNI"
                back-href="/attendances"
            />

            <!-- Search Card -->
            <Card>
                <CardHeader>
                    <CardTitle>Buscar Miembro</CardTitle>
                    <CardDescription
                        >Ingresa el DNI del miembro para validar su
                        acceso</CardDescription
                    >
                </CardHeader>
                <CardContent>
                    <div class="mx-auto max-w-xl">
                        <form @submit.prevent="searchByDni" class="flex gap-4">
                            <Input
                                v-model="dni"
                                type="text"
                                placeholder="Ingrese DNI..."
                                class="flex-1 text-lg"
                                :disabled="searching"
                                autofocus
                            />
                            <Button
                                type="submit"
                                size="lg"
                                :disabled="searching || !dni"
                            >
                                <Search class="mr-2 h-5 w-5" />
                                {{ searching ? 'Buscando...' : 'Buscar' }}
                            </Button>
                        </form>
                    </div>
                </CardContent>
            </Card>

            <!-- Result Card -->
            <Card
                v-if="showResult"
                :class="
                    validation?.allowed ? 'border-green-500' : 'border-red-500'
                "
            >
                <CardContent class="pt-6">
                    <!-- Validation Message -->
                    <Alert
                        :variant="
                            validation?.allowed ? 'default' : 'destructive'
                        "
                        class="mb-6"
                    >
                        <UserCheck v-if="validation?.allowed" class="h-5 w-5" />
                        <XCircle v-else class="h-5 w-5" />
                        <AlertDescription class="text-lg font-semibold">
                            {{ validation?.message }}
                        </AlertDescription>
                    </Alert>

                    <!-- Partner Info -->
                    <div v-if="partner" class="flex flex-col gap-4">
                        <!-- Basic Info -->
                        <div class="flex items-start gap-4">
                            <div v-if="partner.photo_url" class="flex-shrink-0">
                                <img
                                    :src="partner.photo_url"
                                    :alt="getPartnerDisplayName(partner)"
                                    class="h-20 w-20 rounded-full border-2 object-cover"
                                />
                            </div>
                            <div
                                class="flex h-20 w-20 flex-shrink-0 items-center justify-center rounded-full bg-muted text-2xl font-bold"
                                v-else
                            >
                                {{
                                    getPartnerDisplayName(partner).charAt(0) ||
                                    '?'
                                }}
                            </div>
                            <div class="flex-1">
                                <h3 class="text-2xl font-bold">
                                    {{ getPartnerDisplayName(partner) }}
                                </h3>
                                <p class="text-muted-foreground">
                                    DNI: {{ partner.document_number }}
                                </p>
                                <p
                                    v-if="partner.email"
                                    class="text-sm text-muted-foreground"
                                >
                                    {{ partner.email }}
                                </p>
                            </div>
                        </div>

                        <!-- Subscription Info -->
                        <div
                            v-if="partner.activeSubscription"
                            class="grid grid-cols-2 gap-4 rounded-lg bg-muted p-4"
                        >
                            <div>
                                <p class="text-sm text-muted-foreground">
                                    Plan
                                </p>
                                <p class="font-semibold">
                                    {{ partner.activeSubscription.plan.name }}
                                </p>
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground">
                                    Válido hasta
                                </p>
                                <p
                                    class="flex items-center gap-2 font-semibold"
                                >
                                    <Calendar class="h-4 w-4" />
                                    {{
                                        formatDate(
                                            partner.activeSubscription.end_date,
                                        )
                                    }}
                                </p>
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground">
                                    Entradas hoy
                                </p>
                                <p
                                    class="flex items-center gap-2 font-semibold"
                                >
                                    <TrendingUp class="h-4 w-4" />
                                    {{ getEntriesToday()
                                    }}{{
                                        partner.activeSubscription.plan
                                            .max_entries_per_day
                                            ? '/' +
                                              partner.activeSubscription.plan
                                                  .max_entries_per_day
                                            : ''
                                    }}
                                </p>
                            </div>
                            <div>
                                <p class="text-sm text-muted-foreground">
                                    Entradas este mes
                                </p>
                                <p
                                    class="flex items-center gap-2 font-semibold"
                                >
                                    <Clock class="h-4 w-4" />
                                    {{
                                        partner.activeSubscription
                                            .entries_this_month
                                    }}{{
                                        partner.activeSubscription.plan
                                            .max_entries_per_month
                                            ? '/' +
                                              partner.activeSubscription.plan
                                                  .max_entries_per_month
                                            : ''
                                    }}
                                </p>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="flex gap-4">
                            <Button
                                v-if="validation?.allowed"
                                @click="registerCheckIn(false)"
                                size="lg"
                                class="flex-1 bg-green-600 hover:bg-green-700"
                            >
                                <UserCheck class="mr-2 h-5 w-5" />
                                Registrar Entrada
                            </Button>
                            <Button
                                v-else
                                @click="registerCheckIn(true)"
                                variant="outline"
                                size="lg"
                                class="flex-1"
                            >
                                Permitir Acceso Manual
                            </Button>
                            <Button
                                @click="
                                    showResult = false;
                                    dni = '';
                                    partner = null;
                                    validation = null;
                                "
                                variant="outline"
                                size="lg"
                            >
                                Nuevo Check-In
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
