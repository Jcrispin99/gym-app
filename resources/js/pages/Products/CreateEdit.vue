<script setup lang="ts">
import FormPageHeader from '@/components/FormPageHeader.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { MultiSelect } from '@/components/ui/multi-select';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { Clock, Save, User, X } from 'lucide-vue-next';
import { computed, onMounted, reactive, ref } from 'vue';

interface Category {
    id: number;
    name: string;
    parent?: {
        id: number;
        name: string;
    };
}

interface AttributeValue {
    id: number;
    value: string;
}

interface Attribute {
    id: number;
    name: string;
    attribute_values: AttributeValue[];
}

interface ProductProduct {
    id?: number;
    sku: string | null;
    barcode: string | null;
    price: number;
    stock: number;
    is_principal: boolean;
    attribute_values?: AttributeValue[];
}

interface Product {
    id: number;
    name: string;
    description: string | null;
    price: number;
    category_id: number;
    is_active: boolean;
    sku: string | null;
    barcode: string | null;
    product_products: ProductProduct[];
    images: any[];
}

interface Activity {
    id: number;
    description: string;
    event: string;
    properties: any;
    created_at: string;
    causer?: {
        name: string;
        email: string;
    };
}

const page = usePage();
const productId = computed(() => {
    const m = page.url.match(/\/products\/(\d+)/);
    return m ? Number(m[1]) : null;
});

const isEditing = computed(() => productId.value !== null);

const product = ref<Product | null>(null);
const categories = ref<Category[]>([]);
const attributes = ref<Attribute[]>([]);
const activities = ref<Activity[]>([]);

const breadcrumbs = computed<BreadcrumbItem[]>(() => {
    const current = isEditing.value
        ? {
              title: product.value?.name || 'Editar',
              href: productId.value
                  ? `/products/${productId.value}/edit`
                  : '/products',
          }
        : { title: 'Nuevo Producto', href: '/products/create' };

    return [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Productos', href: '/products' },
        current,
    ];
});

const form = reactive({
    name: '',
    description: '',
    price: 0,
    category_id: null as number | null,
    is_active: true,
    sku: '',
    barcode: '',
    image: null as File | null,
    additionalImages: [] as File[],
    existingImageIds: [] as number[],
    attributeLines: [] as { attribute_id: number; values: string[] }[],
    generatedVariants: [] as any[],
    processing: false,
    errors: {} as Record<string, string>,
});

const activeTab = ref('general');

// Estado para selector de atributos
const selectedAttributeId = ref<number | null>(null);
const attributeSelectorOpen = ref(false);
const attributeSearchQuery = ref('');

// Mapa de atributos seleccionados: { attributeId: [valueId1, valueId2, ...] }
const attributeSelections = ref<Record<number, number[]>>({});

// Helper para manejar cambio de categoría
const handleCategoryChange = (value: any) => {
    form.category_id = value ? parseInt(value as string) : null;
};

// Agregar atributo a la tabla
const addAttributeToTable = () => {
    if (!selectedAttributeId.value) return;

    // Si ya existe, no hacer nada
    if (attributeSelections.value[selectedAttributeId.value] !== undefined) {
        attributeSelectorOpen.value = false;
        selectedAttributeId.value = null;
        return;
    }

    // Agregar con array vacío inicialmente
    attributeSelections.value[selectedAttributeId.value] = [];
    updateAttributeLines();

    // Reset
    attributeSelectorOpen.value = false;
    selectedAttributeId.value = null;
    attributeSearchQuery.value = '';
};

// Eliminar atributo de la tabla
const removeAttributeFromTable = (attributeId: number) => {
    delete attributeSelections.value[attributeId];
    updateAttributeLines();
};

// Función para actualizar valores seleccionados de un atributo
const updateAttributeValues = (attributeId: number, valueIds: any[]) => {
    // Convertir a números si vienen como strings
    const numericIds = valueIds.map((id) =>
        typeof id === 'number' ? id : parseInt(id as string),
    );

    attributeSelections.value[attributeId] = numericIds;
    updateAttributeLines();

    // Auto-generar variantes siempre que cambien los atributos
    if (form.attributeLines.length > 0) {
        setTimeout(() => generateVariants(), 0);
    }
};

// Actualiza form.attributeLines basado en attributeSelections
const updateAttributeLines = () => {
    form.attributeLines = Object.entries(attributeSelections.value)
        .filter(([, valueIds]) => valueIds.length > 0) // Solo incluir si tiene valores seleccionados
        .map(([attrId, valueIds]) => {
            const attribute = attributes.value.find(
                (a) => a.id === parseInt(attrId),
            );
            if (!attribute) return null;

            const valueNames = valueIds
                .map(
                    (valueId) =>
                        attribute.attribute_values.find(
                            (av) => av.id === valueId,
                        )?.value,
                )
                .filter(Boolean) as string[];

            return {
                attribute_id: parseInt(attrId),
                values: valueNames,
            };
        })
        .filter(Boolean) as { attribute_id: number; values: string[] }[];

    // Limpiar variantes solo si no hay atributos (y no estamos editando)
    if (form.attributeLines.length === 0 && !isEditing.value) {
        form.generatedVariants = [];
    }
};

const getAttributeName = (attributeId: number): string => {
    return attributes.value.find((a) => a.id === attributeId)?.name || '';
};

// ============================================
// INICIALIZACIÓN AL EDITAR PRODUCTO EXISTENTE
// ============================================
const hydrateFromProduct = (p: Product) => {
    form.name = p.name || '';
    form.description = p.description || '';
    form.price = p.price || 0;
    form.category_id = p.category_id || null;
    form.is_active = p.is_active ?? true;
    form.sku = p.sku || '';
    form.barcode = p.barcode || '';
    form.existingImageIds = p.images?.map((img: any) => img.id) || [];

    const attributeMap: Record<number, Set<number>> = {};
    p.product_products?.forEach((variant) => {
        if (variant.attribute_values && variant.attribute_values.length > 0) {
            variant.attribute_values.forEach((attrValue: any) => {
                if (!attributeMap[attrValue.attribute_id]) {
                    attributeMap[attrValue.attribute_id] = new Set();
                }
                attributeMap[attrValue.attribute_id].add(attrValue.id);
            });
        }
    });

    attributeSelections.value = {};
    Object.entries(attributeMap).forEach(([attrId, valueIds]) => {
        attributeSelections.value[parseInt(attrId)] = Array.from(valueIds);
    });

    form.attributeLines = Object.entries(attributeMap)
        .map(([attrId, valueIds]) => {
            const attribute = attributes.value.find(
                (a) => a.id === parseInt(attrId),
            );
            if (!attribute) return null;

            const valueNames = Array.from(valueIds)
                .map(
                    (valueId) =>
                        attribute.attribute_values.find(
                            (av) => av.id === valueId,
                        )?.value,
                )
                .filter(Boolean) as string[];

            return {
                attribute_id: parseInt(attrId),
                values: valueNames,
            };
        })
        .filter(Boolean) as { attribute_id: number; values: string[] }[];

    form.generatedVariants = (p.product_products || []).map((variant) => {
        const variantAttributes: Record<number, string> = {};
        if (variant.attribute_values) {
            variant.attribute_values.forEach((attrValue: any) => {
                variantAttributes[attrValue.attribute_id] = attrValue.value;
            });
        }

        return {
            sku: variant.sku || '',
            barcode: variant.barcode || '',
            price: variant.price,
            stock: variant.stock,
            attributes: variantAttributes,
        };
    });
};

// Cartesian product helper (for generating variants)
const generateVariants = () => {
    if (form.attributeLines.length === 0) {
        form.generatedVariants = [];
        return;
    }

    // Get all combinations (Cartesian product)
    const combinations = cartesianProduct(
        form.attributeLines.map((line) => ({
            attribute_id: line.attribute_id,
            values: line.values,
        })),
    );

    form.generatedVariants = combinations.map((combo) => {
        const attributes: Record<number, string> = {};
        combo.forEach((item) => {
            attributes[item.attribute_id] = item.value;
        });

        const attributeString = combo.map((c) => c.value).join('-');

        return {
            sku: `SKU-${attributeString.toUpperCase()}`,
            barcode: '',
            price: form.price,
            stock: 0,
            attributes,
        };
    });
};

// Cartesian product helper
function cartesianProduct(
    arrays: { attribute_id: number; values: string[] }[],
): { attribute_id: number; value: string }[][] {
    if (arrays.length === 0) return [[]];
    if (arrays.length === 1) {
        return arrays[0].values.map((v) => [
            { attribute_id: arrays[0].attribute_id, value: v },
        ]);
    }

    const [first, ...rest] = arrays;
    const restProduct = cartesianProduct(rest);

    const result: { attribute_id: number; value: string }[][] = [];
    for (const value of first.values) {
        for (const combo of restProduct) {
            result.push([
                { attribute_id: first.attribute_id, value },
                ...combo,
            ]);
        }
    }
    return result;
}

const submit = async () => {
    form.processing = true;
    form.errors = {};

    try {
        const payload: any = {
            name: form.name,
            description: form.description || null,
            price: form.price,
            category_id: form.category_id,
            is_active: form.is_active,
            sku: form.sku || null,
            barcode: form.barcode || null,
            existingImageIds: form.existingImageIds,
            attributeLines: form.attributeLines,
            generatedVariants: form.generatedVariants,
        };

        const headers = {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        };

        if (isEditing.value && productId.value) {
            const response = await axios.put(
                `/api/product-templates/${productId.value}`,
                payload,
                { headers },
            );
            product.value = response.data?.data as Product;
            if (product.value) {
                hydrateFromProduct(product.value);
            }
            return;
        }

        const response = await axios.post('/api/product-templates', payload, {
            headers,
        });
        product.value = response.data?.data as Product;
        if (product.value?.id) {
            router.visit(`/products/${product.value.id}/edit`);
        }
    } catch (e: any) {
        if (e?.response?.status === 422) {
            form.errors = e.response.data?.errors || {};
        } else {
            console.error('Error saving product:', e);
        }
    } finally {
        form.processing = false;
    }
};

const loadCategories = async () => {
    try {
        const response = await axios.get('/api/categories', {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        categories.value = (response.data?.data || []) as Category[];
    } catch (e) {
        console.error('Error loading categories:', e);
        categories.value = [];
    }
};

const loadAttributes = async () => {
    try {
        const response = await axios.get('/api/attributes', {
            params: { with_values: 1 },
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        attributes.value = (response.data?.data || []) as Attribute[];
    } catch (e) {
        console.error('Error loading attributes:', e);
        attributes.value = [];
    }
};

const loadProduct = async () => {
    if (!productId.value) return;

    try {
        const response = await axios.get(
            `/api/product-templates/${productId.value}`,
            {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            },
        );
        product.value = response.data?.data as Product;
        if (product.value) {
            hydrateFromProduct(product.value);
        }
    } catch (e) {
        console.error('Error loading product:', e);
        product.value = null;
    }
};

const loadActivities = () => {
    const fromProps = (page.props as any)?.activities;
    if (Array.isArray(fromProps)) {
        activities.value = fromProps as Activity[];
    }
};

onMounted(async () => {
    await Promise.all([loadCategories(), loadAttributes()]);
    loadActivities();
    if (isEditing.value) {
        await loadProduct();
    }
});

const formatDate = (date: string) => {
    return new Date(date).toLocaleString('es-PE', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};
</script>

<template>
    <Head
        :title="isEditing ? `Editar ${product?.name || ''}` : 'Nuevo Producto'"
    />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <FormPageHeader
                :title="isEditing ? 'Editar Producto' : 'Nuevo Producto'"
                :description="
                    isEditing ? product?.name || '' : 'Crea un nuevo producto'
                "
                back-href="/products"
            >
                <template #actions>
                    <Button @click="submit" :disabled="form.processing">
                        <Save class="mr-2 h-4 w-4" />
                        {{
                            form.processing
                                ? 'Guardando...'
                                : isEditing
                                  ? 'Actualizar Producto'
                                  : 'Crear Producto'
                        }}
                    </Button>
                </template>
            </FormPageHeader>

            <!-- Form con sidebar de historial -->
            <div
                class="grid grid-cols-1"
                :class="
                    isEditing && activities.length > 0 ? 'lg:grid-cols-3' : ''
                "
            >
                <!-- Main Content-->
                <div
                    :class="
                        isEditing && activities.length > 0
                            ? 'lg:col-span-2'
                            : ''
                    "
                >
                    <form @submit.prevent="submit">
                        <Tabs v-model="activeTab" class="w-full">
                            <TabsList class="grid w-full grid-cols-3">
                                <TabsTrigger value="general"
                                    >Información General</TabsTrigger
                                >
                                <TabsTrigger value="attributes"
                                    >Atributos</TabsTrigger
                                >
                                <TabsTrigger value="variants"
                                    >Variantes</TabsTrigger
                                >
                            </TabsList>

                            <!-- Tab 1: General Information -->
                            <TabsContent value="general">
                                <Card>
                                    <CardHeader>
                                        <CardTitle
                                            >Información del Producto</CardTitle
                                        >
                                    </CardHeader>
                                    <CardContent class="space-y-4">
                                        <!-- Name -->
                                        <div class="space-y-2">
                                            <Label for="name">Nombre *</Label>
                                            <Input
                                                id="name"
                                                v-model="form.name"
                                                placeholder="Nombre del producto"
                                                :class="{
                                                    'border-destructive':
                                                        form.errors.name,
                                                }"
                                                required
                                            />
                                            <p
                                                v-if="form.errors.name"
                                                class="text-sm text-destructive"
                                            >
                                                {{ form.errors.name }}
                                            </p>
                                        </div>

                                        <!-- Description -->
                                        <div class="space-y-2">
                                            <Label for="description"
                                                >Descripción</Label
                                            >
                                            <Textarea
                                                id="description"
                                                v-model="form.description"
                                                placeholder="Descripción del producto"
                                                rows="4"
                                                :class="{
                                                    'border-destructive':
                                                        form.errors.description,
                                                }"
                                            />
                                            <p
                                                v-if="form.errors.description"
                                                class="text-sm text-destructive"
                                            >
                                                {{ form.errors.description }}
                                            </p>
                                        </div>

                                        <div class="grid grid-cols-2 gap-4">
                                            <!-- Price -->
                                            <div class="space-y-2">
                                                <Label for="price"
                                                    >Precio Base *</Label
                                                >
                                                <Input
                                                    id="price"
                                                    v-model="form.price"
                                                    type="number"
                                                    step="0.01"
                                                    placeholder="0.00"
                                                    :class="{
                                                        'border-destructive':
                                                            form.errors.price,
                                                    }"
                                                    required
                                                />
                                                <p
                                                    v-if="form.errors.price"
                                                    class="text-sm text-destructive"
                                                >
                                                    {{ form.errors.price }}
                                                </p>
                                            </div>

                                            <!-- Category -->
                                            <div class="space-y-2">
                                                <Label for="category_id"
                                                    >Categoría *</Label
                                                >
                                                <Select
                                                    :model-value="
                                                        form.category_id?.toString()
                                                    "
                                                    @update:model-value="
                                                        handleCategoryChange
                                                    "
                                                    required
                                                >
                                                    <SelectTrigger>
                                                        <SelectValue
                                                            placeholder="Seleccionar categoría"
                                                        />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem
                                                            v-for="category in categories"
                                                            :key="category.id"
                                                            :value="
                                                                category.id.toString()
                                                            "
                                                        >
                                                            {{ category.name }}
                                                        </SelectItem>
                                                    </SelectContent>
                                                </Select>
                                                <p
                                                    v-if="
                                                        form.errors.category_id
                                                    "
                                                    class="text-sm text-destructive"
                                                >
                                                    {{
                                                        form.errors.category_id
                                                    }}
                                                </p>
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-2 gap-4">
                                            <!-- SKU -->
                                            <div class="space-y-2">
                                                <Label for="sku">SKU</Label>
                                                <Input
                                                    id="sku"
                                                    v-model="form.sku"
                                                    placeholder="SKU del producto"
                                                    :class="{
                                                        'border-destructive':
                                                            form.errors.sku,
                                                    }"
                                                />
                                                <p
                                                    class="text-xs text-muted-foreground"
                                                >
                                                    Solo para productos simples
                                                    sin variantes
                                                </p>
                                                <p
                                                    v-if="form.errors.sku"
                                                    class="text-sm text-destructive"
                                                >
                                                    {{ form.errors.sku }}
                                                </p>
                                            </div>

                                            <!-- Barcode -->
                                            <div class="space-y-2">
                                                <Label for="barcode"
                                                    >Código de Barras</Label
                                                >
                                                <Input
                                                    id="barcode"
                                                    v-model="form.barcode"
                                                    placeholder="Código de barras"
                                                    :class="{
                                                        'border-destructive':
                                                            form.errors.barcode,
                                                    }"
                                                />
                                                <p
                                                    v-if="form.errors.barcode"
                                                    class="text-sm text-destructive"
                                                >
                                                    {{ form.errors.barcode }}
                                                </p>
                                            </div>
                                        </div>

                                        <!-- Is Active -->
                                        <div
                                            class="flex items-center justify-between rounded-lg border p-4"
                                        >
                                            <div class="space-y-0.5">
                                                <Label for="is_active"
                                                    >Producto Activo</Label
                                                >
                                                <p
                                                    class="text-sm text-muted-foreground"
                                                >
                                                    El producto estará visible
                                                    en el catálogo
                                                </p>
                                            </div>
                                            <Switch
                                                id="is_active"
                                                v-model:checked="form.is_active"
                                            />
                                        </div>
                                    </CardContent>
                                </Card>
                            </TabsContent>

                            <!-- Tab 2: Attributes -->
                            <TabsContent value="attributes">
                                <Card>
                                    <CardHeader>
                                        <CardTitle
                                            >Configuración de
                                            Atributos</CardTitle
                                        >
                                        <p
                                            class="mt-2 text-sm text-muted-foreground"
                                        >
                                            Selecciona los atributos y sus
                                            valores. Las variantes se generarán
                                            automáticamente en la pestaña
                                            "Variantes".
                                        </p>
                                    </CardHeader>
                                    <CardContent class="space-y-6">
                                        <!-- Selector de Atributos con Popover/Command -->
                                        <div class="space-y-2">
                                            <div
                                                class="flex items-center justify-between"
                                            >
                                                <Label>Agregar Atributo</Label>
                                                <Popover
                                                    v-model:open="
                                                        attributeSelectorOpen
                                                    "
                                                >
                                                    <PopoverTrigger as-child>
                                                        <Button
                                                            variant="outline"
                                                            size="sm"
                                                        >
                                                            + Buscar Atributo
                                                        </Button>
                                                    </PopoverTrigger>
                                                    <PopoverContent
                                                        class="w-[300px] p-0"
                                                        align="end"
                                                    >
                                                        <Command>
                                                            <CommandInput
                                                                placeholder="Buscar atributo..."
                                                            />
                                                            <CommandEmpty
                                                                >No se
                                                                encontraron
                                                                atributos.</CommandEmpty
                                                            >
                                                            <CommandList>
                                                                <CommandGroup>
                                                                    <CommandItem
                                                                        v-for="attribute in attributes"
                                                                        :key="
                                                                            attribute.id
                                                                        "
                                                                        :value="
                                                                            attribute.name
                                                                        "
                                                                        @select="
                                                                            () => {
                                                                                selectedAttributeId =
                                                                                    attribute.id;
                                                                                addAttributeToTable();
                                                                            }
                                                                        "
                                                                    >
                                                                        {{
                                                                            attribute.name
                                                                        }}
                                                                    </CommandItem>
                                                                </CommandGroup>
                                                            </CommandList>
                                                        </Command>
                                                    </PopoverContent>
                                                </Popover>
                                            </div>
                                        </div>

                                        <!-- Tabla de Atributos Seleccionados -->
                                        <div
                                            v-if="
                                                Object.keys(attributeSelections)
                                                    .length > 0
                                            "
                                            class="rounded-md border"
                                        >
                                            <Table>
                                                <TableHeader>
                                                    <TableRow>
                                                        <TableHead class="w-1/3"
                                                            >Atributo</TableHead
                                                        >
                                                        <TableHead
                                                            >Valores</TableHead
                                                        >
                                                        <TableHead
                                                            class="w-[50px]"
                                                        ></TableHead>
                                                    </TableRow>
                                                </TableHeader>
                                                <TableBody>
                                                    <TableRow
                                                        v-for="(
                                                            valueIds, attrId
                                                        ) in attributeSelections"
                                                        :key="attrId"
                                                    >
                                                        <TableCell
                                                            class="font-medium"
                                                        >
                                                            {{
                                                                getAttributeName(
                                                                    parseInt(
                                                                        attrId as string,
                                                                    ),
                                                                )
                                                            }}
                                                        </TableCell>
                                                        <TableCell>
                                                            <MultiSelect
                                                                :options="
                                                                    attributes
                                                                        .find(
                                                                            (
                                                                                a,
                                                                            ) =>
                                                                                a.id ===
                                                                                parseInt(
                                                                                    attrId as string,
                                                                                ),
                                                                        )
                                                                        ?.attribute_values.map(
                                                                            (
                                                                                av,
                                                                            ) => ({
                                                                                value: av.id,
                                                                                label: av.value,
                                                                            }),
                                                                        ) || []
                                                                "
                                                                :model-value="
                                                                    valueIds
                                                                "
                                                                @update:model-value="
                                                                    (values) =>
                                                                        updateAttributeValues(
                                                                            parseInt(
                                                                                attrId as string,
                                                                            ),
                                                                            values,
                                                                        )
                                                                "
                                                                :placeholder="`Seleccionar valores...`"
                                                                class="w-full"
                                                            />
                                                        </TableCell>
                                                        <TableCell>
                                                            <Button
                                                                type="button"
                                                                variant="ghost"
                                                                size="icon"
                                                                @click="
                                                                    removeAttributeFromTable(
                                                                        parseInt(
                                                                            attrId as string,
                                                                        ),
                                                                    )
                                                                "
                                                            >
                                                                <X
                                                                    class="h-4 w-4"
                                                                />
                                                            </Button>
                                                        </TableCell>
                                                    </TableRow>
                                                </TableBody>
                                            </Table>
                                        </div>

                                        <!-- Mensaje si no hay atributos -->
                                        <div
                                            v-else
                                            class="rounded-md border py-8 text-center text-sm text-muted-foreground"
                                        >
                                            Haz click en "+ Buscar Atributo"
                                            para agregar un atributo y sus
                                            valores
                                        </div>

                                        <!-- Info message sobre variantes generadas -->
                                        <div
                                            v-if="
                                                form.generatedVariants.length >
                                                0
                                            "
                                            class="rounded-md bg-muted p-4"
                                        >
                                            <p
                                                class="text-sm text-muted-foreground"
                                            >
                                                ✅ Se han generado
                                                <strong
                                                    >{{
                                                        form.generatedVariants
                                                            .length
                                                    }}
                                                    variantes</strong
                                                >. Ve a la pestaña "Variantes"
                                                para editarlas.
                                            </p>
                                        </div>
                                    </CardContent>
                                </Card>
                            </TabsContent>

                            <!-- Tab 3: Variants Table -->
                            <TabsContent value="variants">
                                <Card>
                                    <CardHeader>
                                        <CardTitle
                                            >Gestión de Variantes</CardTitle
                                        >
                                        <p
                                            class="mt-2 text-sm text-muted-foreground"
                                        >
                                            Edita el precio de cada variante. El
                                            stock se gestionará por almacén.
                                        </p>
                                    </CardHeader>
                                    <CardContent>
                                        <div
                                            v-if="
                                                form.generatedVariants.length >
                                                0
                                            "
                                            class="space-y-4"
                                        >
                                            <div
                                                class="flex items-center justify-between"
                                            >
                                                <Badge variant="secondary">
                                                    {{
                                                        form.generatedVariants
                                                            .length
                                                    }}
                                                    variante(s)
                                                </Badge>
                                            </div>

                                            <div class="rounded-md border">
                                                <Table>
                                                    <TableHeader>
                                                        <TableRow>
                                                            <TableHead
                                                                >Variante</TableHead
                                                            >
                                                            <TableHead
                                                                >SKU</TableHead
                                                            >
                                                            <TableHead
                                                                >Precio</TableHead
                                                            >
                                                            <TableHead
                                                                >Stock</TableHead
                                                            >
                                                        </TableRow>
                                                    </TableHeader>
                                                    <TableBody>
                                                        <TableRow
                                                            v-for="(
                                                                variant, index
                                                            ) in form.generatedVariants"
                                                            :key="index"
                                                        >
                                                            <TableCell>
                                                                <div
                                                                    class="font-medium"
                                                                >
                                                                    {{
                                                                        form.name
                                                                    }}
                                                                    -
                                                                    <span
                                                                        class="text-muted-foreground"
                                                                    >
                                                                        {{
                                                                            Object.values(
                                                                                variant.attributes,
                                                                            ).join(
                                                                                ' / ',
                                                                            )
                                                                        }}
                                                                    </span>
                                                                </div>
                                                            </TableCell>
                                                            <TableCell>
                                                                <Input
                                                                    v-model="
                                                                        variant.sku
                                                                    "
                                                                    placeholder="SKU"
                                                                    class="max-w-[200px]"
                                                                />
                                                            </TableCell>
                                                            <TableCell>
                                                                <div
                                                                    class="flex items-center gap-1"
                                                                >
                                                                    <span
                                                                        class="text-sm text-muted-foreground"
                                                                        >S/</span
                                                                    >
                                                                    <Input
                                                                        v-model="
                                                                            variant.price
                                                                        "
                                                                        type="number"
                                                                        step="0.01"
                                                                        placeholder="0.00"
                                                                        class="max-w-[120px]"
                                                                    />
                                                                </div>
                                                            </TableCell>
                                                            <TableCell>
                                                                <Badge
                                                                    variant="outline"
                                                                    class="font-mono"
                                                                >
                                                                    {{
                                                                        variant.stock ||
                                                                        0
                                                                    }}
                                                                    unidades
                                                                </Badge>
                                                            </TableCell>
                                                        </TableRow>
                                                    </TableBody>
                                                </Table>
                                            </div>
                                        </div>

                                        <div
                                            v-else
                                            class="py-12 text-center text-muted-foreground"
                                        >
                                            <p class="text-sm">
                                                No hay variantes generadas.
                                            </p>
                                            <p class="mt-1 text-sm">
                                                Ve a la pestaña "Atributos" para
                                                configurar atributos y generar
                                                variantes.
                                            </p>
                                        </div>
                                    </CardContent>
                                </Card>
                            </TabsContent>
                        </Tabs>
                    </form>
                </div>

                <!-- Activity Log Sidebar (Right) - Solo en edición -->
                <div
                    v-if="isEditing && activities"
                    class="mt-6 lg:col-span-1 lg:mt-0 lg:pl-6"
                >
                    <Card class="sticky top-4">
                        <CardHeader>
                            <CardTitle>Historial de Cambios</CardTitle>
                            <CardDescription
                                >Últimas 20 actividades</CardDescription
                            >
                        </CardHeader>
                        <CardContent>
                            <div class="space-y-4">
                                <div
                                    v-for="(activity, index) in activities"
                                    :key="index"
                                    class="flex gap-3 text-sm"
                                >
                                    <div class="flex-shrink-0">
                                        <Clock
                                            class="h-4 w-4 text-muted-foreground"
                                        />
                                    </div>
                                    <div class="flex-1 space-y-1">
                                        <p class="font-medium">
                                            {{ activity.description }}
                                        </p>
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{
                                                formatDate(activity.created_at)
                                            }}
                                        </p>
                                        <p
                                            v-if="activity.causer"
                                            class="flex items-center gap-1 text-xs text-muted-foreground"
                                        >
                                            <User class="h-3 w-3" />
                                            {{ activity.causer.name }}
                                        </p>
                                    </div>
                                </div>

                                <div
                                    v-if="activities.length === 0"
                                    class="py-4 text-center text-sm text-muted-foreground"
                                >
                                    No hay actividades registradas
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
