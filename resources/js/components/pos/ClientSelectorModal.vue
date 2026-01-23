<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Search, UserPlus } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

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
    sessionId: number;
}

interface Emits {
    (e: 'update:open', value: boolean): void;
    (e: 'select', client: Client): void;
    (e: 'clear'): void;
    (e: 'client-created', client: Client): void;
}

const props = defineProps<Props>();
const emit = defineEmits<Emits>();

// State
const clientSearchQuery = ref<string>('');
const showCreateClient = ref<boolean>(false);
const isSavingClient = ref<boolean>(false);
const isLookingUpPartner = ref<boolean>(false);
const saveCustomerError = ref<string>('');
let partnerLookupDebounceTimer: number | undefined;
const partnerLookupStatus = ref<'idle' | 'found' | 'not_found'>('idle');

const newClientDocumentType = ref<'DNI' | 'RUC' | 'CE' | 'Passport'>('DNI');
const newClientDocumentNumber = ref<string>('');
const newClientBusinessName = ref<string>('');
const newClientFirstName = ref<string>('');
const newClientLastName = ref<string>('');
const newClientEmail = ref<string>('');
const newClientPhone = ref<string>('');
const newClientMobile = ref<string>('');

// Computed
const filteredClients = computed(() => {
    if (!clientSearchQuery.value) return props.clients;
    const query = clientSearchQuery.value.toLowerCase();
    return props.clients.filter(
        (client) =>
            client.name.toLowerCase().includes(query) ||
            client.dni?.includes(query) ||
            client.email?.toLowerCase().includes(query),
    );
});

// Methods
const closeModal = () => {
    emit('update:open', false);
    resetForm();
};

const selectClient = (client: Client) => {
    emit('select', client);
    closeModal();
};

const resetForm = () => {
    clientSearchQuery.value = '';
    showCreateClient.value = false;
    partnerLookupStatus.value = 'idle';
    saveCustomerError.value = '';
    newClientDocumentType.value = 'DNI';
    newClientDocumentNumber.value = '';
    newClientBusinessName.value = '';
    newClientFirstName.value = '';
    newClientLastName.value = '';
    newClientEmail.value = '';
    newClientPhone.value = '';
    newClientMobile.value = '';
};

const csrfToken = () => {
    const el = document.querySelector('meta[name="csrf-token"]');
    return el?.getAttribute('content') || '';
};

const getCookie = (name: string) => {
    const match = document.cookie.match(
        new RegExp(
            `(?:^|; )${name.replace(/[.*+?^${}()|[\\]\\\\]/g, '\\\\$&')}=([^;]*)`,
        ),
    );
    return match ? decodeURIComponent(match[1]) : '';
};

const xsrfToken = () => {
    return getCookie('XSRF-TOKEN') || csrfToken();
};

const lookupPartnerByDocument = async () => {
    const documentNumber = newClientDocumentNumber.value.trim();
    if (!showCreateClient.value || documentNumber.length < 8) {
        partnerLookupStatus.value = 'idle';
        return;
    }

    isLookingUpPartner.value = true;

    try {
        const params = new URLSearchParams();
        params.set('document_type', newClientDocumentType.value);
        params.set('document_number', documentNumber);

        const response = await fetch(
            `/pos/${props.sessionId}/customers/lookup?${params.toString()}`,
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-XSRF-TOKEN': xsrfToken(),
                    Accept: 'application/json',
                },
                body: JSON.stringify({
                    document_type: newClientDocumentType.value,
                    document_number: documentNumber,
                }),
            },
        );
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        const data = await response.json();

        if (data?.found) {
            partnerLookupStatus.value = 'found';
            const partner = data.partner;
            newClientDocumentType.value =
                partner.document_type || newClientDocumentType.value;
            newClientDocumentNumber.value =
                partner.document_number || newClientDocumentNumber.value;
            newClientBusinessName.value = partner.business_name || '';
            newClientFirstName.value = partner.first_name || '';
            newClientLastName.value = partner.last_name || '';
            newClientEmail.value = partner.email || '';
            newClientPhone.value = partner.phone || '';
            newClientMobile.value = partner.mobile || '';
        } else {
            partnerLookupStatus.value = 'not_found';
        }
    } catch (e) {
        partnerLookupStatus.value = 'idle';
        console.error('Error looking up partner:', e);
    } finally {
        isLookingUpPartner.value = false;
    }
};

const saveCustomer = async () => {
    isSavingClient.value = true;
    saveCustomerError.value = '';

    try {
        const payload = {
            document_type: newClientDocumentType.value,
            document_number: newClientDocumentNumber.value.trim(),
            business_name: newClientBusinessName.value.trim() || null,
            first_name: newClientFirstName.value.trim() || null,
            last_name: newClientLastName.value.trim() || null,
            email: newClientEmail.value.trim() || null,
            phone: newClientPhone.value.trim() || null,
            mobile: newClientMobile.value.trim() || null,
        };

        const response = await fetch(
            `/pos/${props.sessionId}/customers/upsert`,
            {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-XSRF-TOKEN': xsrfToken(),
                    Accept: 'application/json',
                },
                body: JSON.stringify(payload),
            },
        );

        if (!response.ok) {
            let data: any = null;
            try {
                data = await response.json();
            } catch {}

            if (response.status === 422) {
                const message =
                    data?.message ||
                    (data?.errors
                        ? Object.values(data.errors)
                              .flat()
                              .filter(Boolean)
                              .join(' ')
                        : '') ||
                    'Datos inválidos.';
                saveCustomerError.value = message;
            } else if (response.status === 419) {
                saveCustomerError.value =
                    'Sesión expirada (CSRF). Recarga la página e inténtalo otra vez.';
            } else {
                saveCustomerError.value =
                    data?.message || `Error HTTP ${response.status}`;
            }

            throw new Error(`HTTP ${response.status}`);
        }
        const saved = await response.json();

        // Emit event to parent to update list and select
        emit('client-created', saved);
        emit('select', saved);
        closeModal();
    } catch (e) {
        console.error('Error saving customer:', e);
    } finally {
        isSavingClient.value = false;
    }
};

// Watchers
watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            resetForm();
        }
    },
);

watch(
    [showCreateClient, newClientDocumentType, newClientDocumentNumber],
    () => {
        if (partnerLookupDebounceTimer) {
            window.clearTimeout(partnerLookupDebounceTimer);
        }
        partnerLookupDebounceTimer = window.setTimeout(() => {
            void lookupPartnerByDocument();
        }, 300);
    },
);
</script>

<template>
    <Dialog :open="open" @update:open="(val) => emit('update:open', val)">
        <DialogContent
            class="inset-4 h-[calc(100vh-2rem)] w-[calc(100vw-2rem)] max-w-none translate-x-0 translate-y-0 overflow-hidden p-0 sm:max-w-none"
        >
            <div class="flex h-full flex-col">
                <div
                    class="flex items-start justify-between gap-4 border-b p-6"
                >
                    <div class="space-y-1">
                        <h2 class="text-2xl font-bold">Clientes</h2>
                        <p class="text-sm text-muted-foreground">
                            Busca y selecciona un cliente, o crea uno nuevo.
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <Button
                            variant="outline"
                            @click="showCreateClient = !showCreateClient"
                        >
                            {{
                                showCreateClient
                                    ? 'Volver a lista'
                                    : 'Crear cliente'
                            }}
                        </Button>
                    </div>
                </div>

                <div class="grid min-h-0 flex-1 grid-cols-1">
                    <div
                        v-if="!showCreateClient"
                        class="flex min-h-0 flex-col gap-4 p-6"
                    >
                        <div class="relative">
                            <Search
                                class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                            />
                            <Input
                                v-model="clientSearchQuery"
                                placeholder="Buscar por nombre, DNI o email..."
                                class="pl-10"
                            />
                        </div>

                        <div
                            class="min-h-0 flex-1 overflow-y-auto rounded-lg border"
                        >
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Cliente</TableHead>
                                        <TableHead>DNI</TableHead>
                                        <TableHead>Email</TableHead>
                                        <TableHead class="w-[1%]"></TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow
                                        v-for="client in filteredClients"
                                        :key="client.id"
                                        class="cursor-pointer"
                                        :class="
                                            selectedClient?.id === client.id
                                                ? 'bg-muted/50'
                                                : ''
                                        "
                                        @click="selectClient(client)"
                                    >
                                        <TableCell class="font-medium">
                                            {{ client.name }}
                                        </TableCell>
                                        <TableCell>{{
                                            client.dni || '—'
                                        }}</TableCell>
                                        <TableCell
                                            class="max-w-[260px] truncate"
                                            >{{
                                                client.email || '—'
                                            }}</TableCell
                                        >
                                        <TableCell>
                                            <Button
                                                v-if="
                                                    selectedClient?.id ===
                                                    client.id
                                                "
                                                size="sm"
                                                variant="default"
                                            >
                                                Seleccionado
                                            </Button>
                                        </TableCell>
                                    </TableRow>

                                    <TableRow
                                        v-if="filteredClients.length === 0"
                                    >
                                        <TableCell
                                            class="py-10 text-center text-muted-foreground"
                                            colspan="4"
                                        >
                                            No se encontraron clientes
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>
                    </div>

                    <div v-else class="flex min-h-0 flex-col gap-6 p-6">
                        <div class="space-y-4">
                            <div class="flex items-center gap-2">
                                <UserPlus class="h-5 w-5 text-primary" />
                                <h3 class="font-semibold">Crear cliente</h3>
                            </div>

                            <Separator />

                            <div class="grid grid-cols-2 gap-3">
                                <div class="space-y-1">
                                    <Label>Tipo</Label>
                                    <Select v-model="newClientDocumentType">
                                        <SelectTrigger>
                                            <SelectValue placeholder="Tipo" />
                                        </SelectTrigger>
                                        <SelectContent class="z-[100]">
                                            <SelectItem value="DNI"
                                                >DNI</SelectItem
                                            >
                                            <SelectItem value="RUC"
                                                >RUC</SelectItem
                                            >
                                            <SelectItem value="CE"
                                                >CE</SelectItem
                                            >
                                            <SelectItem value="Passport"
                                                >Pasaporte</SelectItem
                                            >
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div class="space-y-1">
                                    <Label>Nro. documento</Label>
                                    <Input v-model="newClientDocumentNumber" />
                                </div>

                                <div
                                    v-if="newClientDocumentType === 'RUC'"
                                    class="col-span-2 space-y-1"
                                >
                                    <Label>Razón social</Label>
                                    <Input v-model="newClientBusinessName" />
                                </div>

                                <div v-else class="col-span-1 space-y-1">
                                    <Label>Nombres</Label>
                                    <Input v-model="newClientFirstName" />
                                </div>
                                <div
                                    v-if="newClientDocumentType !== 'RUC'"
                                    class="col-span-1 space-y-1"
                                >
                                    <Label>Apellidos</Label>
                                    <Input v-model="newClientLastName" />
                                </div>

                                <div class="col-span-2 grid grid-cols-2 gap-3">
                                    <div class="space-y-1">
                                        <Label>Email</Label>
                                        <Input
                                            v-model="newClientEmail"
                                            type="email"
                                        />
                                    </div>
                                    <div class="space-y-1">
                                        <Label>Teléfono</Label>
                                        <Input v-model="newClientPhone" />
                                    </div>
                                </div>

                                <div class="col-span-2 space-y-1">
                                    <Label>Celular</Label>
                                    <Input v-model="newClientMobile" />
                                </div>
                            </div>

                            <div class="text-xs text-muted-foreground">
                                <template v-if="isLookingUpPartner">
                                    Buscando en base de datos...
                                </template>
                                <template
                                    v-else-if="partnerLookupStatus === 'found'"
                                >
                                    <span
                                        v-if="
                                            newClientDocumentType === 'DNI' ||
                                            newClientDocumentType === 'RUC'
                                        "
                                        class="font-medium text-green-600"
                                    >
                                        ¡Datos encontrados!
                                    </span>
                                    <span v-else>
                                        Cliente encontrado en el sistema.
                                    </span>
                                </template>
                                <template
                                    v-else-if="
                                        partnerLookupStatus === 'not_found'
                                    "
                                >
                                    No existe: se creará un nuevo registro.
                                </template>
                            </div>
                            <div
                                v-if="saveCustomerError"
                                class="text-sm text-destructive"
                            >
                                {{ saveCustomerError }}
                            </div>

                            <Button
                                class="w-full"
                                variant="default"
                                :disabled="
                                    isSavingClient ||
                                    !newClientDocumentNumber.trim() ||
                                    (newClientDocumentType === 'RUC'
                                        ? !newClientBusinessName.trim()
                                        : !newClientFirstName.trim())
                                "
                                @click="saveCustomer"
                            >
                                <UserPlus class="mr-2 h-4 w-4" />
                                {{
                                    isSavingClient
                                        ? 'Guardando...'
                                        : 'Crear y seleccionar'
                                }}
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
