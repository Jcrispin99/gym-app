<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Separator } from '@/components/ui/separator';
import {
    Dialog,
    DialogContent,
} from '@/components/ui/dialog';
import { Search, X, UserPlus } from 'lucide-vue-next';
import { ref, computed } from 'vue';

// Types
interface Client {
    id: number;
    name: string;
    dni?: string;
    document_type?: 'DNI' | 'RUC' | 'CE' | 'Passport';
    document_number?: string;
    email?: string;
    phone?: string;
}

interface Props {
    open: boolean;
    selectedClient: Client | null;
    clients: Client[];
}

interface Emits {
    (e: 'update:open', value: boolean): void;
    (e: 'select', client: Client): void;
    (e: 'clear'): void;
}

const props = defineProps<Props>();
const emit = defineEmits<Emits>();

// State
const clientSearchQuery = ref<string>('');

// Computed
const filteredClients = computed(() => {
    if (!clientSearchQuery.value) return props.clients;
    const query = clientSearchQuery.value.toLowerCase();
    return props.clients.filter(client => 
        client.name.toLowerCase().includes(query) ||
        client.dni?.includes(query) ||
        client.email?.toLowerCase().includes(query)
    );
});

// Methods
const closeModal = () => {
    emit('update:open', false);
    clientSearchQuery.value = '';
};

const selectClient = (client: Client) => {
    emit('select', client);
    closeModal();
};

const clearClient = () => {
    emit('clear');
};
</script>

<template>
    <Dialog :open="open" @update:open="(val) => emit('update:open', val)">
        <DialogContent class="max-w-2xl max-h-[90vh] overflow-y-auto">
            <div class="space-y-6">
                <div class="flex items-center justify-between">
                    <h2 class="text-2xl font-bold">Seleccionar Cliente</h2>
                    <Button size="icon" variant="ghost" @click="closeModal">
                        <X class="h-4 w-4" />
                    </Button>
                </div>

                <Separator />

                <!-- Current Selected Client -->
                <div v-if="selectedClient" class="p-4 bg-blue-50 rounded-lg border border-blue-200">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="font-semibold text-blue-900">{{ selectedClient.name }}</p>
                            <p class="text-sm text-blue-700">DNI: {{ selectedClient.dni }}</p>
                        </div>
                        <Button size="sm" variant="outline" @click="clearClient">
                            Quitar
                        </Button>
                    </div>
                </div>

                <!-- Search -->
                <div class="relative">
                    <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                    <Input
                        v-model="clientSearchQuery"
                        placeholder="Buscar por nombre, DNI o email..."
                        class="pl-10"
                    />
                </div>

                <!-- Client List -->
                <div class="space-y-2 max-h-[300px] overflow-y-auto">
                    <h3 class="font-semibold text-sm text-muted-foreground">Clientes Registrados</h3>
                    <div
                        v-for="client in filteredClients"
                        :key="client.id"
                        class="p-3 rounded-lg border hover:bg-muted/50 cursor-pointer transition-colors"
                        :class="selectedClient?.id === client.id ? 'bg-blue-50 border-blue-300' : ''"
                        @click="selectClient(client)"
                    >
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="font-semibold">{{ client.name }}</p>
                                <div class="flex gap-3 text-sm text-muted-foreground">
                                    <span v-if="client.dni">DNI: {{ client.dni }}</span>
                                    <span v-if="client.email">{{ client.email }}</span>
                                </div>
                            </div>
                            <Button
                                v-if="selectedClient?.id === client.id"
                                size="sm"
                                variant="default"
                            >
                                Seleccionado
                            </Button>
                        </div>
                    </div>

                    <div v-if="filteredClients.length === 0" class="text-center py-8 text-muted-foreground">
                        No se encontraron clientes
                    </div>
                </div>

                <Separator />

                <!-- Quick Create Form -->
                <div class="space-y-4">
                    <div class="flex items-center gap-2">
                        <UserPlus class="h-5 w-5 text-primary" />
                        <h3 class="font-semibold">Crear Cliente Rápido</h3>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="col-span-2">
                            <Input placeholder="Nombre completo *" />
                        </div>
                        <Input placeholder="DNI *" />
                        <Input placeholder="Teléfono" />
                        <div class="col-span-2">
                            <Input placeholder="Email" type="email" />
                        </div>
                    </div>

                    <Button class="w-full" variant="default">
                        <UserPlus class="h-4 w-4 mr-2" />
                        Crear y Seleccionar
                    </Button>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
