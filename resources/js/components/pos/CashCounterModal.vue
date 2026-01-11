<script setup lang="ts">
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Banknote, Coins } from 'lucide-vue-next';
import { ref, computed } from 'vue';

interface Props {
    open: boolean;
}

interface Emits {
    (e: 'update:open', value: boolean): void;
    (e: 'confirm', amount: number): void;
}

const props = defineProps<Props>();
const emit = defineEmits<Emits>();

// Billetes
const bills_200 = ref(0);
const bills_100 = ref(0);
const bills_50 = ref(0);
const bills_20 = ref(0);
const bills_10 = ref(0);

// Monedas soles
const coins_5 = ref(0);
const coins_2 = ref(0);
const coins_1 = ref(0);

// Monedas céntimos
const coins_050 = ref(0);
const coins_020 = ref(0);
const coins_010 = ref(0);

const billsTotal = computed(() => {
    return (bills_200.value * 200) +
           (bills_100.value * 100) +
           (bills_50.value * 50) +
           (bills_20.value * 20) +
           (bills_10.value * 10);
});

const coinsTotal = computed(() => {
    return (coins_5.value * 5) +
           (coins_2.value * 2) +
           (coins_1.value * 1) +
           (coins_050.value * 0.50) +
           (coins_020.value * 0.20) +
           (coins_010.value * 0.10);
});

const grandTotal = computed(() => {
    return billsTotal.value + coinsTotal.value;
});

const handleClose = () => {
    emit('update:open', false);
};

const handleConfirm = () => {
    emit('confirm', grandTotal.value);
    handleClose();
    resetAll();
};

const resetAll = () => {
    bills_200.value = 0;
    bills_100.value = 0;
    bills_50.value = 0;
    bills_20.value = 0;
    bills_10.value = 0;
    coins_5.value = 0;
    coins_2.value = 0;
    coins_1.value = 0;
    coins_050.value = 0;
    coins_020.value = 0;
    coins_010.value = 0;
};

const formatCurrency = (value: number): string => {
    return `S/ ${value.toFixed(2)}`;
};
</script>

<template>
    <Dialog :open="open" @update:open="handleClose">
        <DialogContent class="max-w-2xl max-h-[90vh] overflow-y-auto">
            <DialogHeader>
                <DialogTitle>Contador de Dinero</DialogTitle>
                <DialogDescription>
                    Ingresa la cantidad de billetes y monedas para calcular el total
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-6 py-4">
                <!-- Billetes -->
                <div class="space-y-4">
                    <div class="flex items-center gap-2">
                        <Banknote class="h-5 w-5 text-primary" />
                        <h3 class="font-semibold">Billetes</h3>
                        <span class="ml-auto text-sm text-muted-foreground">
                            {{ formatCurrency(billsTotal) }}
                        </span>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <Label>S/ 200</Label>
                            <Input v-model.number="bills_200" type="number" min="0" placeholder="0" />
                        </div>
                        <div class="space-y-2">
                            <Label>S/ 100</Label>
                            <Input v-model.number="bills_100" type="number" min="0" placeholder="0" />
                        </div>
                        <div class="space-y-2">
                            <Label>S/ 50</Label>
                            <Input v-model.number="bills_50" type="number" min="0" placeholder="0" />
                        </div>
                        <div class="space-y-2">
                            <Label>S/ 20</Label>
                            <Input v-model.number="bills_20" type="number" min="0" placeholder="0" />
                        </div>
                        <div class="space-y-2">
                            <Label>S/ 10</Label>
                            <Input v-model.number="bills_10" type="number" min="0" placeholder="0" />
                        </div>
                    </div>
                </div>

                <Separator />

                <!-- Monedas -->
                <div class="space-y-4">
                    <div class="flex items-center gap-2">
                        <Coins class="h-5 w-5 text-primary" />
                        <h3 class="font-semibold">Monedas</h3>
                        <span class="ml-auto text-sm text-muted-foreground">
                            {{ formatCurrency(coinsTotal) }}
                        </span>
                    </div>

                    <div class="grid grid-cols-3 gap-4">
                        <div class="space-y-2">
                            <Label>S/ 5</Label>
                            <Input v-model.number="coins_5" type="number" min="0" placeholder="0" />
                        </div>
                        <div class="space-y-2">
                            <Label>S/ 2</Label>
                            <Input v-model.number="coins_2" type="number" min="0" placeholder="0" />
                        </div>
                        <div class="space-y-2">
                            <Label>S/ 1</Label>
                            <Input v-model.number="coins_1" type="number" min="0" placeholder="0" />
                        </div>
                        <div class="space-y-2">
                            <Label>S/ 0.50</Label>
                            <Input v-model.number="coins_050" type="number" min="0" placeholder="0" />
                        </div>
                        <div class="space-y-2">
                            <Label>S/ 0.20</Label>
                            <Input v-model.number="coins_020" type="number" min="0" placeholder="0" />
                        </div>
                        <div class="space-y-2">
                            <Label>S/ 0.10</Label>
                            <Input v-model.number="coins_010" type="number" min="0" placeholder="0" />
                        </div>
                    </div>
                </div>

                <Separator />

                <!-- Total -->
                <div class="rounded-lg bg-primary/10 p-4">
                    <div class="flex items-center justify-between">
                        <span class="text-lg font-semibold">Total</span>
                        <span class="text-2xl font-bold text-primary">
                            {{ formatCurrency(grandTotal) }}
                        </span>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex gap-3">
                    <Button variant="outline" class="flex-1" @click="handleClose">
                        Cancelar
                    </Button>
                    <Button class="flex-1" @click="handleConfirm">
                        Usar este monto
                    </Button>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
