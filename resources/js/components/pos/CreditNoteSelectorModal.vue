<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Loader2 } from 'lucide-vue-next';
import { ref, watch } from 'vue';

const props = defineProps<{
    open: boolean;
    sessionId: number;
    partnerId?: number;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'select', note: any): void;
}>();

const isLoading = ref(false);
const creditNotes = ref<any[]>([]);

const fetchCreditNotes = async () => {
    if (!props.partnerId) return;

    isLoading.value = true;
    try {
        const response = await fetch(
            `/pos/${props.sessionId}/credit-notes/${props.partnerId}`,
        );
        if (response.ok) {
            creditNotes.value = await response.json();
        }
    } catch (e) {
        console.error(e);
    } finally {
        isLoading.value = false;
    }
};

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen && props.partnerId) {
            fetchCreditNotes();
        }
    },
);

const selectNote = (note: any) => {
    emit('select', note);
};

const formatCurrency = (val: number) => {
    return `S/ ${val.toFixed(2)}`;
};
</script>

<template>
    <Dialog :open="open" @update:open="(val) => emit('update:open', val)">
        <DialogContent class="sm:max-w-[600px]">
            <DialogHeader>
                <DialogTitle>Seleccionar Nota de Crédito</DialogTitle>
                <DialogDescription>
                    Selecciona una nota de crédito disponible para usar su
                    saldo.
                </DialogDescription>
            </DialogHeader>

            <div class="py-4">
                <div v-if="isLoading" class="flex justify-center py-8">
                    <Loader2 class="h-8 w-8 animate-spin text-primary" />
                </div>

                <div
                    v-else-if="creditNotes.length === 0"
                    class="py-8 text-center text-muted-foreground"
                >
                    No se encontraron notas de crédito disponibles para este
                    cliente.
                </div>

                <div
                    v-else
                    class="max-h-[400px] overflow-y-auto rounded-md border"
                >
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Documento</TableHead>
                                <TableHead>Fecha</TableHead>
                                <TableHead class="text-right">Total</TableHead>
                                <TableHead class="text-right"
                                    >Disponible</TableHead
                                >
                                <TableHead></TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow
                                v-for="note in creditNotes"
                                :key="note.id"
                            >
                                <TableCell class="font-medium">{{
                                    note.document
                                }}</TableCell>
                                <TableCell>{{ note.date }}</TableCell>
                                <TableCell class="text-right">{{
                                    formatCurrency(note.total)
                                }}</TableCell>
                                <TableCell
                                    class="text-right font-bold text-green-600"
                                    >{{
                                        formatCurrency(note.balance)
                                    }}</TableCell
                                >
                                <TableCell>
                                    <Button size="sm" @click="selectNote(note)">
                                        Usar
                                    </Button>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
