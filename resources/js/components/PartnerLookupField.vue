<script setup lang="ts">
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Loader2, Search } from 'lucide-vue-next';
import { ref, watch } from 'vue';

interface Props {
    documentType: string;
    documentNumber: string;
    error?: string;
    readonly?: boolean;
    autoLookup?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    autoLookup: true,
});

const emit = defineEmits<{
    (e: 'update:documentType', value: string): void;
    (e: 'update:documentNumber', value: string): void;
    (e: 'found', data: any): void;
}>();

const isSearching = ref(false);
const searchStatus = ref<'idle' | 'found' | 'not_found' | 'error'>('idle');

let debounceTimer: number | undefined;

const performLookup = async () => {
    if (props.readonly) {
        return;
    }

    const docNum = props.documentNumber?.trim();

    // Validar longitud mínima antes de buscar
    if (!docNum || docNum.length < 8) {
        searchStatus.value = 'idle';
        return;
    }

    // Auto-detectar tipo si no está establecido correctamente
    if (docNum.length === 8 && props.documentType !== 'DNI') {
        emit('update:documentType', 'DNI');
    } else if (docNum.length === 11 && props.documentType !== 'RUC') {
        emit('update:documentType', 'RUC');
    }

    isSearching.value = true;
    searchStatus.value = 'idle';

    try {
        const params = new URLSearchParams({
            document_number: docNum,
            document_type: props.documentType,
        });

        const response = await fetch(`/api/sunat/lookup?${params.toString()}`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) throw new Error('Error en la búsqueda');

        const data = await response.json();

        // Fix: El backend puede devolver found:true o simplemente los datos.
        // Verificamos si hay nombre o razón social como indicador de éxito
        const hasData =
            data.name ||
            data.business_name ||
            data.first_name ||
            (data.partner && data.found);

        if (hasData) {
            searchStatus.value = 'found';
            emit('found', data);
        } else {
            searchStatus.value = 'not_found';
        }
    } catch (e) {
        console.error('Lookup error:', e);
        searchStatus.value = 'error';
    } finally {
        isSearching.value = false;
    }
};

// Observar cambios en el número de documento para búsqueda automática (debounce)
watch(
    () => props.documentNumber,
    (newVal) => {
        if (props.readonly || !props.autoLookup) {
            searchStatus.value = 'idle';
            return;
        }

        if (debounceTimer) clearTimeout(debounceTimer);

        if (newVal && newVal.length >= 8) {
            debounceTimer = window.setTimeout(() => {
                performLookup();
            }, 800); // 800ms delay
        } else {
            searchStatus.value = 'idle';
        }
    },
);

// Observar cambios en el tipo de documento
watch(
    () => props.documentType,
    () => {
        if (props.readonly || !props.autoLookup) {
            searchStatus.value = 'idle';
            return;
        }

        if (props.documentNumber && props.documentNumber.length >= 8) {
            performLookup();
        }
    },
);
</script>

<template>
    <div class="grid grid-cols-2 gap-4">
        <div>
            <Label for="document_type">Tipo Documento *</Label>
            <Select
                :model-value="documentType"
                @update:model-value="
                    (val) => emit('update:documentType', val as string)
                "
                :disabled="readonly"
            >
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
            <Label
                for="document_number"
                class="flex items-center justify-between"
            >
                <span>Número Documento *</span>
                <span
                    v-if="isSearching"
                    class="flex items-center gap-1 text-xs text-muted-foreground"
                >
                    <Loader2 class="h-3 w-3 animate-spin" /> Buscando...
                </span>
                <span
                    v-else-if="searchStatus === 'found'"
                    class="text-xs font-medium text-green-600"
                >
                    ¡Encontrado!
                </span>
                <span
                    v-else-if="searchStatus === 'not_found'"
                    class="text-xs text-amber-600"
                >
                    No encontrado
                </span>
            </Label>
            <div class="relative">
                <Input
                    id="document_number"
                    :model-value="documentNumber"
                    @input="
                        (e: any) =>
                            emit('update:documentNumber', e.target.value)
                    "
                    :class="{ 'border-red-500': error }"
                    :disabled="readonly"
                    placeholder="Ingrese número..."
                />
                <button
                    type="button"
                    class="absolute top-1/2 right-2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                    @click="performLookup"
                    :disabled="readonly || isSearching || !documentNumber"
                    title="Buscar manualmente"
                >
                    <Search class="h-4 w-4" />
                </button>
            </div>
            <p v-if="error" class="mt-1 text-sm text-red-500">
                {{ error }}
            </p>
        </div>
    </div>
</template>
