<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';

interface Company {
    id: number;
    business_name: string;
    trade_name: string;
}

interface MembershipPlan {
    id: number;
    company_id: number;
    name: string;
    description: string | null;
    duration_days: number;
    price: number | string;
    max_entries_per_month: number | null;
    max_entries_per_day: number;
    time_restricted: boolean;
    allowed_time_start: string | null;
    allowed_time_end: string | null;
    allowed_days: string[] | null;
    allows_freezing: boolean;
    max_freeze_days: number;
    is_active: boolean;
}

interface Props {
    plan: MembershipPlan;
    companies: Company[];
}

const props = defineProps<Props>();

const days = [
    { value: 'monday', label: 'Lunes' },
    { value: 'tuesday', label: 'Martes' },
    { value: 'wednesday', label: 'Miércoles' },
    { value: 'thursday', label: 'Jueves' },
    { value: 'friday', label: 'Viernes' },
    { value: 'saturday', label: 'Sábado' },
    { value: 'sunday', label: 'Domingo' },
];

const form = useForm({
    name: props.plan.name,
    description: props.plan.description || '',
    duration_days: props.plan.duration_days,
    price: props.plan.price,
    max_entries_per_month: props.plan.max_entries_per_month ?? undefined,
    max_entries_per_day: props.plan.max_entries_per_day,
    time_restricted: props.plan.time_restricted,
    allowed_time_start: props.plan.allowed_time_start || '',
    allowed_time_end: props.plan.allowed_time_end || '',
    allowed_days: props.plan.allowed_days || [],
    allows_freezing: props.plan.allows_freezing,
    max_freeze_days: props.plan.max_freeze_days,
    is_active: props.plan.is_active,
});

const submit = () => {
    form.transform((data) => {
        const transformed: any = { ...data };

        // Convert empty strings to null for nullable fields
        if (
            transformed.max_entries_per_month === '' ||
            transformed.max_entries_per_month === undefined
        ) {
            transformed.max_entries_per_month = null;
        }

        // If not time restricted, clear time fields to null
        if (!transformed.time_restricted) {
            transformed.allowed_time_start = null;
            transformed.allowed_time_end = null;
        }

        // For allowed_days, NULL means todos los días
        if (
            !Array.isArray(transformed.allowed_days) ||
            transformed.allowed_days.length === 0 ||
            transformed.allowed_days.length === 7
        ) {
            transformed.allowed_days = null;
        }

        return transformed;
    });

    form.put(`/membership-plans/${props.plan.id}`);
};

const toggleDay = (day: string) => {
    const index = form.allowed_days.indexOf(day);
    if (index > -1) {
        form.allowed_days.splice(index, 1);
    } else {
        form.allowed_days.push(day);
    }
};
</script>

<template>
    <AppLayout>
        <Head :title="`Editar ${plan.name}`" />

        <div class="space-y-6">
            <!-- Header -->
            <div class="flex items-center gap-4">
                <Button
                    variant="ghost"
                    size="icon"
                    @click="router.visit('/membership-plans')"
                >
                    <ArrowLeft class="h-4 w-4" />
                </Button>
                <div>
                    <h1 class="text-3xl font-bold tracking-tight">
                        Editar Plan de Membresía
                    </h1>
                    <p class="text-muted-foreground">
                        {{ plan.name }}
                    </p>
                </div>
            </div>

            <!-- Form -->
            <form @submit.prevent="submit">
                <div class="grid gap-6 md:grid-cols-2">
                    <!-- Información Básica -->
                    <Card class="md:col-span-2">
                        <CardHeader>
                            <CardTitle>Información Básica</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="space-y-2">
                                    <Label for="company_id">Compañía</Label>
                                    <Input
                                        :value="
                                            companies.find(
                                                (c) => c.id === plan.company_id,
                                            )?.trade_name
                                        "
                                        disabled
                                        class="bg-muted"
                                    />
                                    <p class="text-xs text-muted-foreground">
                                        No se puede cambiar la compañía de un
                                        plan existente
                                    </p>
                                </div>

                                <div class="space-y-2">
                                    <Label for="name">Nombre del Plan *</Label>
                                    <Input
                                        id="name"
                                        v-model="form.name"
                                        placeholder="Premium Full Access"
                                    />
                                    <p
                                        v-if="form.errors.name"
                                        class="text-sm text-red-500"
                                    >
                                        {{ form.errors.name }}
                                    </p>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <Label for="description">Descripción</Label>
                                <Textarea
                                    id="description"
                                    v-model="form.description"
                                    placeholder="Acceso ilimitado todos los días..."
                                    rows="3"
                                />
                            </div>

                            <div class="grid gap-4 md:grid-cols-3">
                                <div class="space-y-2">
                                    <Label for="duration_days"
                                        >Duración (días) *</Label
                                    >
                                    <Select v-model="form.duration_days">
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem :value="30"
                                                >30 días (1 mes)</SelectItem
                                            >
                                            <SelectItem :value="60"
                                                >60 días (2 meses)</SelectItem
                                            >
                                            <SelectItem :value="90"
                                                >90 días (3 meses)</SelectItem
                                            >
                                            <SelectItem :value="180"
                                                >180 días (6 meses)</SelectItem
                                            >
                                            <SelectItem :value="365"
                                                >365 días (1 año)</SelectItem
                                            >
                                        </SelectContent>
                                    </Select>
                                    <p
                                        v-if="form.errors.duration_days"
                                        class="text-sm text-red-500"
                                    >
                                        {{ form.errors.duration_days }}
                                    </p>
                                </div>

                                <div class="space-y-2">
                                    <Label for="price">Precio (S/) *</Label>
                                    <Input
                                        id="price"
                                        v-model="form.price"
                                        type="number"
                                        step="0.01"
                                        placeholder="150.00"
                                    />
                                    <p
                                        v-if="form.errors.price"
                                        class="text-sm text-red-500"
                                    >
                                        {{ form.errors.price }}
                                    </p>
                                </div>

                                <div class="flex items-center space-x-2 pt-8">
                                    <Switch
                                        id="is_active"
                                        v-model:checked="form.is_active"
                                    />
                                    <Label for="is_active">Plan activo</Label>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Restricciones de Acceso -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Restricciones de Acceso</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="space-y-2">
                                <Label for="max_entries_per_month"
                                    >Entradas por mes</Label
                                >
                                <Input
                                    id="max_entries_per_month"
                                    v-model="form.max_entries_per_month"
                                    type="number"
                                    placeholder="Dejar vacío para ilimitado"
                                />
                                <p class="text-xs text-muted-foreground">
                                    Vacío = ilimitado, ej: 12 = 3 veces por
                                    semana
                                </p>
                            </div>

                            <div class="space-y-2">
                                <Label for="max_entries_per_day"
                                    >Entradas por día *</Label
                                >
                                <Input
                                    id="max_entries_per_day"
                                    v-model.number="form.max_entries_per_day"
                                    type="number"
                                    min="1"
                                />
                            </div>

                            <div class="space-y-4 border-t pt-4">
                                <div class="flex items-center space-x-2">
                                    <Switch
                                        id="time_restricted"
                                        v-model:checked="form.time_restricted"
                                    />
                                    <Label for="time_restricted"
                                        >Restringir horario</Label
                                    >
                                </div>

                                <div
                                    v-if="form.time_restricted"
                                    class="grid gap-4 md:grid-cols-2"
                                >
                                    <div class="space-y-2">
                                        <Label for="allowed_time_start"
                                            >Hora inicio</Label
                                        >
                                        <Input
                                            id="allowed_time_start"
                                            v-model="form.allowed_time_start"
                                            type="time"
                                        />
                                    </div>

                                    <div class="space-y-2">
                                        <Label for="allowed_time_end"
                                            >Hora fin</Label
                                        >
                                        <Input
                                            id="allowed_time_end"
                                            v-model="form.allowed_time_end"
                                            type="time"
                                        />
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-2 border-t pt-4">
                                <Label>Días permitidos</Label>
                                <div class="grid grid-cols-2 gap-2">
                                    <div
                                        v-for="day in days"
                                        :key="day.value"
                                        class="flex items-center space-x-2"
                                    >
                                        <Checkbox
                                            :id="day.value"
                                            :checked="
                                                form.allowed_days.includes(
                                                    day.value,
                                                )
                                            "
                                            @update:checked="
                                                toggleDay(day.value)
                                            "
                                        />
                                        <Label
                                            :for="day.value"
                                            class="cursor-pointer"
                                            >{{ day.label }}</Label
                                        >
                                    </div>
                                </div>
                                <p class="text-xs text-muted-foreground">
                                    Vacío = todos los días permitidos
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Congelamiento -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Congelamiento</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="flex items-center space-x-2">
                                <Switch
                                    id="allows_freezing"
                                    v-model:checked="form.allows_freezing"
                                />
                                <Label for="allows_freezing"
                                    >Permitir congelamiento</Label
                                >
                            </div>

                            <div v-if="form.allows_freezing" class="space-y-2">
                                <Label for="max_freeze_days"
                                    >Días máximos de congelamiento</Label
                                >
                                <Input
                                    id="max_freeze_days"
                                    v-model.number="form.max_freeze_days"
                                    type="number"
                                    min="0"
                                    placeholder="30"
                                />
                                <p class="text-xs text-muted-foreground">
                                    Total de días que se puede congelar durante
                                    toda la vigencia del plan
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Actions -->
                <div class="mt-6 flex justify-end gap-4">
                    <Button
                        type="button"
                        variant="outline"
                        @click="router.visit('/membership-plans')"
                        :disabled="form.processing"
                    >
                        Cancelar
                    </Button>
                    <Button type="submit" :disabled="form.processing">
                        {{
                            form.processing ? 'Guardando...' : 'Guardar Cambios'
                        }}
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
