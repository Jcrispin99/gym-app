<script setup lang="ts">
import AsyncComboboxWithCreateDialog from '@/components/AsyncComboboxWithCreateDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { MultiSelect } from '@/components/ui/multi-select';
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
import AttributeForm from '@/pages/Attributes/Form.vue';
import CategoryForm from '@/pages/Categories/Form.vue';
import axios from 'axios';
import { X } from 'lucide-vue-next';
import { onMounted, reactive, ref } from 'vue';

const CategoryFormComponent = CategoryForm;
const AttributeFormComponent = AttributeForm;

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

type Mode = 'create' | 'edit';

const props = defineProps<{
    mode: Mode;
    productId?: number | null;
    initialName?: string;
    initialCategoryId?: number | null;
}>();

const emit = defineEmits<{
    (e: 'loaded', product: Product): void;
    (e: 'saved', product: Product): void;
}>();

const product = ref<Product | null>(null);
const attributes = ref<Attribute[]>([]);
const activeTab = ref('general');

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

const formatCreateCategoryLabel = (q: string) => {
    return q ? `Crear "${q}"` : 'Crear categoría';
};

const formatCreateAttributeLabel = (q: string) => {
    return q ? `Crear "${q}"` : 'Crear atributo';
};

// Estado para selector de atributos
const selectedAttributeId = ref<number | null>(null);

// Mapa de atributos seleccionados: { attributeId: [valueId1, valueId2, ...] }
const attributeSelections = ref<Record<number, number[]>>({});

// Helper para manejar cambio de categoría
const handleCategoryChange = (value: any) => {
    if (value === null || value === undefined) {
        form.category_id = null;
        return;
    }
    const parsed = Number(value);
    form.category_id = Number.isFinite(parsed) ? parsed : null;
};

const ensureAttributeInList = (attribute: any) => {
    if (!attribute) return;
    if (!attributes.value.some((a) => a.id === attribute.id)) {
        attributes.value.push(attribute);
    }
};

const addAttributeFromId = (id: any) => {
    if (id === null || id === undefined) return;
    const parsed = Number(id);
    if (!Number.isFinite(parsed)) return;
    selectedAttributeId.value = parsed;
    addAttributeToTable();
};

// Agregar atributo a la tabla
const addAttributeToTable = () => {
    if (!selectedAttributeId.value) return;

    // Si ya existe, no hacer nada
    if (attributeSelections.value[selectedAttributeId.value] !== undefined) {
        selectedAttributeId.value = null;
        return;
    }

    // Agregar con array vacío inicialmente
    attributeSelections.value[selectedAttributeId.value] = [];
    updateAttributeLines();

    // Reset
    selectedAttributeId.value = null;
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
    if (form.attributeLines.length === 0 && props.mode === 'create') {
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

const processing = ref(false);

const submit = async () => {
    processing.value = true;
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

        if (props.mode === 'edit' && props.productId) {
            const response = await axios.put(
                `/api/product-templates/${props.productId}`,
                payload,
                { headers },
            );
            const savedProduct = response.data?.data as Product;
            product.value = savedProduct;
            if (product.value) {
                hydrateFromProduct(product.value);
            }
            emit('saved', savedProduct);
            return;
        }

        const response = await axios.post('/api/product-templates', payload, {
            headers,
        });
        const savedProduct = response.data?.data as Product;
        product.value = savedProduct;
        emit('saved', savedProduct);
    } catch (e: any) {
        if (e?.response?.status === 422) {
            form.errors = e.response.data?.errors || {};
        } else {
            console.error('Error saving product:', e);
        }
    } finally {
        form.processing = false;
        processing.value = false;
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
    if (!props.productId) return;

    try {
        const response = await axios.get(
            `/api/product-templates/${props.productId}`,
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
            emit('loaded', product.value);
        }
    } catch (e) {
        console.error('Error loading product:', e);
        product.value = null;
    }
};

onMounted(async () => {
    await Promise.all([loadAttributes()]);
    if (props.mode === 'edit' && props.productId) {
        await loadProduct();
    } else if (props.mode === 'create') {
        if (props.initialName) form.name = props.initialName;
        if (props.initialCategoryId) form.category_id = props.initialCategoryId;
    }
});

defineExpose({
    submit,
    processing,
});
</script>

<template>
    <div class="space-y-6">
        <form @submit.prevent="submit">
            <Tabs v-model="activeTab" class="w-full">
                <TabsList class="grid w-full grid-cols-3">
                    <TabsTrigger value="general"
                        >Información General</TabsTrigger
                    >
                    <TabsTrigger value="attributes">Atributos</TabsTrigger>
                    <TabsTrigger value="variants">Variantes</TabsTrigger>
                </TabsList>

                <!-- Tab 1: General Information -->
                <TabsContent value="general">
                    <Card>
                        <CardHeader>
                            <CardTitle>Información del Producto</CardTitle>
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
                                        'border-destructive': form.errors.name,
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
                                <Label for="description">Descripción</Label>
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
                                    <Label for="price">Precio Base *</Label>
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
                                    <Label for="category_id">Categoría *</Label>
                                    <AsyncComboboxWithCreateDialog
                                        :model-value="form.category_id"
                                        placeholder="Seleccionar categoría"
                                        search-url="/api/categories"
                                        get-url-template="/api/categories/{id}"
                                        :limit="5"
                                        :extra-params="{ only_active: 1 }"
                                        show-create
                                        :create-label="
                                            formatCreateCategoryLabel
                                        "
                                        create-title="Nueva Categoría"
                                        create-description="Crea una nueva categoría sin salir del formulario"
                                        :form-component="CategoryFormComponent"
                                        @update:model-value="
                                            handleCategoryChange
                                        "
                                    />
                                    <p
                                        v-if="form.errors.category_id"
                                        class="text-sm text-destructive"
                                    >
                                        {{ form.errors.category_id }}
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
                                    <p class="text-xs text-muted-foreground">
                                        Solo para productos simples sin
                                        variantes
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
                                    <p class="text-sm text-muted-foreground">
                                        El producto estará visible en el
                                        catálogo
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
                            <CardTitle>Configuración de Atributos</CardTitle>
                            <p class="mt-2 text-sm text-muted-foreground">
                                Selecciona los atributos y sus valores. Las
                                variantes se generarán automáticamente en la
                                pestaña "Variantes".
                            </p>
                        </CardHeader>
                        <CardContent class="space-y-6">
                            <!-- Selector de Atributos with Popover/Command -->
                            <div class="space-y-2">
                                <div class="flex items-center justify-between">
                                    <Label>Agregar Atributo</Label>
                                    <AsyncComboboxWithCreateDialog
                                        :model-value="null"
                                        placeholder="Buscar atributo..."
                                        search-url="/api/attributes"
                                        get-url-template="/api/attributes/{id}"
                                        :extra-params="{ with_values: 1 }"
                                        width="w-[300px]"
                                        show-create
                                        :create-label="
                                            formatCreateAttributeLabel
                                        "
                                        create-title="Nuevo Atributo"
                                        create-description="Crea un nuevo atributo y sus valores sin salir del formulario"
                                        :form-component="AttributeFormComponent"
                                        @update:model-value="addAttributeFromId"
                                        @select="ensureAttributeInList"
                                        @created="ensureAttributeInList"
                                    />
                                </div>
                            </div>

                            <!-- Selected Attributes Table -->
                            <div
                                v-if="
                                    Object.keys(attributeSelections).length > 0
                                "
                                class="rounded-md border"
                            >
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead class="w-1/3"
                                                >Atributo</TableHead
                                            >
                                            <TableHead>Valores</TableHead>
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
                                            <TableCell class="font-medium">
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
                                                                (a) =>
                                                                    a.id ===
                                                                    parseInt(
                                                                        attrId as string,
                                                                    ),
                                                            )
                                                            ?.attribute_values.map(
                                                                (av) => ({
                                                                    value: av.id,
                                                                    label: av.value,
                                                                }),
                                                            ) || []
                                                    "
                                                    :model-value="valueIds"
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
                                                    <X class="h-4 w-4" />
                                                </Button>
                                            </TableCell>
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </div>

                            <!-- No attributes message -->
                            <div
                                v-else
                                class="rounded-md border py-8 text-center text-sm text-muted-foreground"
                            >
                                Haz click en "+ Buscar Atributo" para agregar un
                                atributo y sus valores
                            </div>

                            <!-- Info message about generated variants -->
                            <div
                                v-if="form.generatedVariants.length > 0"
                                class="rounded-md bg-muted p-4"
                            >
                                <p class="text-sm text-muted-foreground">
                                    ✅ Se han generado
                                    <strong
                                        >{{
                                            form.generatedVariants.length
                                        }}
                                        variantes</strong
                                    >. Ve a la pestaña "Variantes" para
                                    editarlas.
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </TabsContent>

                <!-- Tab 3: Variants Table -->
                <TabsContent value="variants">
                    <Card>
                        <CardHeader>
                            <CardTitle>Gestión de Variantes</CardTitle>
                            <p class="mt-2 text-sm text-muted-foreground">
                                Edita el precio de cada variante. El stock se
                                gestionará por almacén.
                            </p>
                        </CardHeader>
                        <CardContent>
                            <div
                                v-if="form.generatedVariants.length > 0"
                                class="space-y-4"
                            >
                                <div class="flex items-center justify-between">
                                    <Badge variant="secondary">
                                        {{ form.generatedVariants.length }}
                                        variante(s)
                                    </Badge>
                                </div>

                                <div class="rounded-md border">
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead>Variante</TableHead>
                                                <TableHead>SKU</TableHead>
                                                <TableHead>Precio</TableHead>
                                                <TableHead>Stock</TableHead>
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
                                                    <div class="font-medium">
                                                        {{ form.name }} -
                                                        <span
                                                            class="text-muted-foreground"
                                                        >
                                                            {{
                                                                Object.values(
                                                                    variant.attributes,
                                                                ).join(' / ')
                                                            }}
                                                        </span>
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    <Input
                                                        v-model="variant.sku"
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
                                                        {{ variant.stock || 0 }}
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
                                    Ve a la pestaña "Atributos" para configurar
                                    atributos y generar variantes.
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </TabsContent>
            </Tabs>
        </form>
    </div>
</template>
