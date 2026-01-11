<script setup lang="ts">
import PosLayout from '@/layouts/PosLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import CashCounterModal from '@/components/pos/CashCounterModal.vue';
import { router } from '@inertiajs/vue3';
import { ArrowLeft, DollarSign, Calculator } from 'lucide-vue-next';
import { ref } from 'vue';

interface Warehouse {
    id: number;
    name: string;
}

interface Tax {
    id: number;
    name: string;
    rate_percent: number;
}

interface PosConfig {
    id: number;
    name: string;
    warehouse: Warehouse;
    tax?: Tax;
}

interface Props {
    posConfig: PosConfig;
}

const props = defineProps<Props>();

const openingBalance = ref<string>('0.00');
const openingNote = ref<string>('');
const isSubmitting = ref(false);
const showCounterModal = ref(false);

const handleCounterConfirm = (amount: number) => {
    openingBalance.value = amount.toFixed(2);
};

const handleSubmit = () => {
    if (isSubmitting.value) return;
    
    isSubmitting.value = true;
    
    router.post('/pos/open', {
        pos_config_id: props.posConfig.id,
        opening_balance: parseFloat(openingBalance.value),
        opening_note: openingNote.value || null,
    }, {
        onFinish: () => {
            isSubmitting.value = false;
        },
    });
};

const handleCancel = () => {
    router.visit('/pos-configs');
};
</script>

<template>
    <PosLayout :title="`Apertura de Caja - ${posConfig.name}`">
        <div class="max-w-2xl mx-auto mt-8">
            <!-- Back Button -->
            <Button
                variant="ghost"
                class="mb-4"
                @click="handleCancel"
            >
                <ArrowLeft class="h-4 w-4 mr-2" />
                Volver a Configuraciones
            </Button>

            <!-- Opening Form -->
            <Card>
                <CardHeader>
                    <CardTitle>Apertura de Caja</CardTitle>
                    <CardDescription>
                        Ingresa el balance inicial para abrir la caja
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-6">
                    <!-- POS Info -->
                    <div class="rounded-lg border bg-muted/50 p-4 space-y-2">
                        <div class="flex justify-between">
                            <span class="text-sm text-muted-foreground">Punto de Venta</span>
                            <span class="font-medium">{{ posConfig.name }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-muted-foreground">Almacén</span>
                            <span class="font-medium">{{ posConfig.warehouse.name }}</span>
                        </div>
                        <div v-if="posConfig.tax" class="flex justify-between">
                            <span class="text-sm text-muted-foreground">Impuesto</span>
                            <span class="font-medium">
                                {{ posConfig.tax.name }} ({{ posConfig.tax.rate_percent }}%)
                            </span>
                        </div>
                    </div>

                    <!-- Opening Balance Input -->
                    <div class="space-y-2">
                        <Label for="opening-balance">Balance Inicial</Label>
                        <div class="flex gap-2">
                            <div class="relative flex-1">
                                <DollarSign class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                                <Input
                                    id="opening-balance"
                                    v-model="openingBalance"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    placeholder="0.00"
                                    class="pl-10 text-lg"
                                />
                            </div>
                            <Button
                                variant="outline"
                                size="icon"
                                @click="showCounterModal = true"
                                title="Contar dinero"
                            >
                                <Calculator class="h-4 w-4" />
                            </Button>
                        </div>
                        <p class="text-sm text-muted-foreground">
                            Ingresa el dinero en efectivo con el que cuentas al inicio del día
                        </p>
                    </div>

                    <!-- Opening Note -->
                    <div class="space-y-2">
                        <Label for="opening-note">Nota de Apertura (Opcional)</Label>
                        <Textarea
                            id="opening-note"
                            v-model="openingNote"
                            placeholder="Ej: Todo en orden, sin novedades..."
                            rows="3"
                            maxlength="1000"
                        />
                    </div>

                    <!-- Actions -->
                    <div class="flex gap-3">
                        <Button
                            variant="outline"
                            class="flex-1"
                            @click="handleCancel"
                            :disabled="isSubmitting"
                        >
                            Cancelar
                        </Button>
                        <Button
                            class="flex-1"
                            @click="handleSubmit"
                            :disabled="isSubmitting"
                        >
                            {{ isSubmitting ? 'Abriendo...' : 'Abrir Caja' }}
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Cash Counter Modal -->
        <CashCounterModal
            v-model:open="showCounterModal"
            @confirm="handleCounterConfirm"
        />
    </PosLayout>
</template>
