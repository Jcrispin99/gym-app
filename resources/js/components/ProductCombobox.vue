<script setup lang="ts">
import { ref, watch } from 'vue';
import { Check, ChevronsUpDown } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { cn } from '@/lib/utils';
import axios from 'axios';

interface Product {
    id: number;
    sku: string;
    barcode: string;
    name: string;
    display_name: string;
    price: number;
    cost_price: number;
    stock: number;
    attributes: string;
}

interface Props {
    modelValue: number | null;
    warehouseId?: number | null;
    placeholder?: string;
    disabled?: boolean;
}

interface Emits {
    (e: 'update:modelValue', value: number | null): void;
    (e: 'select', product: Product): void;
}

const props = withDefaults(defineProps<Props>(), {
    modelValue: null,
    warehouseId: null,
    placeholder: 'Buscar producto...',
    disabled: false,
});

const emit = defineEmits<Emits>();

const open = ref(false);
const searchQuery = ref('');
const products = ref<Product[]>([]);
const loading = ref(false);
const selectedProduct = ref<Product | null>(null);

// Debounce search
let searchTimeout: ReturnType<typeof setTimeout>;

const searchProducts = async (query: string) => {
    loading.value = true;
    console.log('🔍 Searching products with query:', query, 'warehouse:', props.warehouseId, '(stock not filtered for purchases)');
    
    try {
        const params: any = {
            limit: 20,
        };
        
        // Solo agregar query si hay texto (si está vacío, el backend retorna los más usados)
        if (query && query.length >= 1) {
            params.q = query;
        }
        
        if (props.warehouseId) {
            params.warehouse_id = props.warehouseId;
        }

        const response = await axios.get('/api/products/search', { params });
        products.value = response.data;
        console.log('✅ Products found:', products.value.length, products.value);
    } catch (error) {
        console.error('❌ Error searching products:', error);
        products.value = [];
    } finally {
        loading.value = false;
    }
};

const handleSearch = (query: string) => {
    searchQuery.value = query;
    
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        searchProducts(query);
    }, 300);
};

const selectProduct = (product: Product) => {
    selectedProduct.value = product;
    emit('update:modelValue', product.id);
    emit('select', product);
    open.value = false;
};

const clearSelection = () => {
    selectedProduct.value = null;
    emit('update:modelValue', null);
    searchQuery.value = '';
    products.value = [];
};

// Si cambia el modelValue externamente, buscar el producto
watch(() => props.modelValue, async (newValue) => {
    console.log('🔄 ModelValue changed:', newValue, 'Current selected:', selectedProduct.value?.id);
    if (newValue && !selectedProduct.value) {
        console.log('📥 Fetching product details for ID:', newValue);
        try {
            const response = await axios.get(`/api/products/${newValue}`);
            selectedProduct.value = response.data;
            console.log('✅ Product loaded:', selectedProduct.value);
        } catch (error) {
            console.error('❌ Error fetching product:', error);
        }
    } else if (!newValue) {
        console.log('🗑️ Clearing selected product');
        selectedProduct.value = null;
    } else if (newValue && selectedProduct.value && selectedProduct.value.id !== newValue) {
        // Si el modelValue cambió a un producto diferente
        console.log('🔄 Product changed, fetching new product:', newValue);
        try {
            const response = await axios.get(`/api/products/${newValue}`);
            selectedProduct.value = response.data;
            console.log('✅ New product loaded:', selectedProduct.value);
        } catch (error) {
            console.error('❌ Error fetching product:', error);
        }
    }
}, { immediate: true });

// Cargar productos más usados cuando se abre el popover
watch(open, (isOpen) => {
    console.log('📦 Popover state changed:', isOpen, 'Products in cache:', products.value.length, 'Disabled:', props.disabled, 'Selected product:', selectedProduct.value?.id);
    if (isOpen && products.value.length === 0) {
        searchProducts(''); // Cargar los 10 más usados
    }
});
</script>

<template>
    <Popover v-model:open="open">
        <PopoverTrigger as-child>
            <Button
                variant="outline"
                role="combobox"
                :aria-expanded="open"
                class="w-full justify-between"
                :disabled="disabled"
            >
                <span class="truncate">
                    {{ selectedProduct ? selectedProduct.display_name : placeholder }}
                </span>
                <ChevronsUpDown class="ml-2 h-4 w-4 shrink-0 opacity-50" />
            </Button>
        </PopoverTrigger>
        <PopoverContent class="w-[400px] p-0">
            <Command>
                <CommandInput 
                    :placeholder="placeholder"
                    @input="(e: any) => handleSearch(e.target.value)"
                />
                <CommandList>
                    <CommandEmpty>
                        {{ loading ? 'Buscando...' : 'No se encontraron productos.' }}
                    </CommandEmpty>
                    <CommandGroup v-if="products.length > 0">
                        <CommandItem
                            v-for="product in products"
                            :key="product.id"
                            :value="product.id.toString()"
                            @select="selectProduct(product)"
                        >
                            <Check
                                :class="cn(
                                    'mr-2 h-4 w-4',
                                    selectedProduct?.id === product.id ? 'opacity-100' : 'opacity-0'
                                )"
                            />
                            <div class="flex-1">
                                <div class="font-medium">{{ product.display_name }}</div>
                                <div class="text-xs text-muted-foreground">
                                    SKU: {{ product.sku }}
                                    <template v-if="product.barcode"> | Barcode: {{ product.barcode }}</template>
                                    | Stock: {{ product.stock }} | S/ {{ product.price }}
                                </div>
                            </div>
                        </CommandItem>
                    </CommandGroup>
                </CommandList>
            </Command>
        </PopoverContent>
    </Popover>
</template>
