<script setup lang="ts">
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Dialog, DialogContent } from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
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
import PosLayout from '@/layouts/PosLayout.vue';
import { router } from '@inertiajs/vue3';
import {
    CreditCard,
    Delete,
    Dumbbell,
    FileText,
    LogOut,
    Menu,
    Minus,
    Package,
    Plus,
    Search,
    Trash2,
    User,
    UserPlus,
    Wifi,
    WifiOff,
    X,
} from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

// Types
interface Category {
    id: number;
    name: string;
    icon?: any;
}

interface Product {
    id: number;
    name: string;
    price: number;
    category_id: number;
    image?: string;
    duration?: string;
}

interface CartItem {
    product_id: number;
    name: string;
    qty: number;
    price: number;
    subtotal: number;
}

interface PaymentMethod {
    id: number;
    name: string;
    is_active: boolean;
}

interface User {
    id: number;
    name: string;
    email: string;
}

interface Client {
    id: number;
    name: string;
    dni?: string;
    email?: string;
    phone?: string;
}

interface PosSession {
    id: number;
    user_id: number;
    pos_config_id: number;
    opening_balance: string;
    closing_balance: string | null;
    opened_at: string;
    closed_at: string | null;
    status: string;
    user?: User;
}

interface Props {
    session: PosSession;
    paymentMethods?: PaymentMethod[];
    customers: Client[];
    categories: Category[]; // Categories from backend
}

// Props
const props = defineProps<Props>();

// Helper to get icon based on category name
const getCategoryIcon = (categoryName: string) => {
    const lower = categoryName.toLowerCase();
    if (lower.includes('suscrip') || lower.includes('plan')) return CreditCard;
    if (lower.includes('producto') || lower.includes('merch')) return Package;
    if (lower.includes('servicio') || lower.includes('clase')) return Dumbbell;
    return Package; // Default
};

// Transform props categories to include icon
const categories = computed(() => {
    return props.categories.map((category) => ({
        ...category,
        icon: getCategoryIcon(category.name),
    }));
});

const products = ref<Product[]>([]);
const isLoadingProducts = ref<boolean>(false);
let productsDebounceTimer: number | undefined;

// Clients from API (hybrid: initialized with server data, refreshable)
const clients = ref<Client[]>(props.customers);

// State - Default to first category if available
const activeCategory = ref<number>(
    props.categories.length > 0 ? props.categories[0].id : 0,
);
const searchQuery = ref<string>('');

// SessionStorage keys for persistence
const cartStorageKey = `pos_cart_${props.session.id}`;
const clientStorageKey = `pos_client_${props.session.id}`;

// Load persisted cart and client from sessionStorage
const loadPersistedData = () => {
    const savedCart = sessionStorage.getItem(cartStorageKey);
    const savedClient = sessionStorage.getItem(clientStorageKey);

    if (savedCart) {
        try {
            cart.value = JSON.parse(savedCart);
        } catch (e) {
            console.error('Error loading cart from storage:', e);
        }
    }

    if (savedClient) {
        try {
            selectedClient.value = JSON.parse(savedClient);
        } catch (e) {
            console.error('Error loading client from storage:', e);
        }
    }
};

const clearPersistedData = () => {
    cart.value = [];
    selectedClient.value = null;
    sessionStorage.removeItem(cartStorageKey);
    sessionStorage.removeItem(clientStorageKey);
};

const cart = ref<CartItem[]>([]);
const showCloseModal = ref<boolean>(false);
const showClientModal = ref<boolean>(false);
const selectedClient = ref<Client | null>(null);
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

const isOnline = ref<boolean>(navigator.onLine);

const warehouseId = computed<number | undefined>(() => {
    const sessionAny = props.session as any;
    return sessionAny?.pos_config?.warehouse?.id;
});

const hasSearchQuery = computed(() => searchQuery.value.trim().length > 0);

// Computed
const filteredProducts = computed(() => {
    return products.value;
});

const cartTotal = computed(() => {
    return cart.value.reduce((sum, item) => sum + item.subtotal, 0);
});

const cartItemCount = computed(() => {
    return cart.value.reduce((sum, item) => sum + item.qty, 0);
});

const filteredClients = computed(() => {
    if (!clientSearchQuery.value) return clients.value;
    const query = clientSearchQuery.value.toLowerCase();
    return clients.value.filter(
        (client) =>
            client.name.toLowerCase().includes(query) ||
            client.dni?.includes(query) ||
            client.email?.toLowerCase().includes(query),
    );
});

const clientButtonText = computed(() => {
    if (!selectedClient.value) return '';
    const names = selectedClient.value.name.split(' ');
    if (names.length >= 2) {
        // Return initials for long names (e.g., "JP" for "Juan Pérez")
        return (names[0][0] + names[names.length - 1][0]).toUpperCase();
    }
    // For single names, return first 8 characters
    return selectedClient.value.name.substring(0, 8);
});

// Methods
const setCategory = (categoryId: number) => {
    activeCategory.value = categoryId;
    searchQuery.value = '';
};

const loadProducts = async () => {
    isLoadingProducts.value = true;

    try {
        const params = new URLSearchParams();
        if (hasSearchQuery.value) {
            params.set('q', searchQuery.value.trim());
            params.set('limit', '60');
        } else {
            if (!activeCategory.value) {
                products.value = [];
                return;
            }

            params.set('category_id', activeCategory.value.toString());
            params.set('browse', 'true');
            params.set('limit', '60');
        }

        params.set('only_active', 'true');

        if (warehouseId.value) {
            params.set('warehouse_id', warehouseId.value.toString());
        }

        const response = await fetch(
            `/api/products/search?${params.toString()}`,
        );
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        const data = await response.json();

        products.value = (data || []).map((p: any) => ({
            id: p.id,
            name: p.display_name || p.name,
            price: Number(p.price || 0),
            category_id: Number(p.category_id || 0),
        }));
    } catch (e) {
        console.error('Error loading products:', e);
        products.value = [];
    } finally {
        isLoadingProducts.value = false;
    }
};

const addToCart = (product: Product) => {
    const existing = cart.value.find((item) => item.product_id === product.id);

    if (existing) {
        existing.qty++;
        existing.subtotal = existing.qty * existing.price;
    } else {
        cart.value.push({
            product_id: product.id,
            name: product.name,
            qty: 1,
            price: product.price,
            subtotal: product.price,
        });
    }
};

const incrementQty = (item: CartItem) => {
    item.qty++;
    item.subtotal = item.qty * item.price;
};

const decrementQty = (item: CartItem) => {
    if (item.qty > 1) {
        item.qty--;
        item.subtotal = item.qty * item.price;
    }
};

const removeFromCart = (productId: number) => {
    const index = cart.value.findIndex((item) => item.product_id === productId);
    if (index !== -1) {
        cart.value.splice(index, 1);
    }
};

const processPayment = () => {
    if (cart.value.length === 0) return;

    // Navigate to payment view
    router.post(`/pos/${props.session.id}/payment`, {
        cart: cart.value,
        client_id: selectedClient.value?.id || null,
        total: cartTotal.value,
    });
};

const formatCurrency = (value: number): string => {
    return `S/ ${value.toFixed(2)}`;
};

// Numpad
const numpadKeys = ['1', '2', '3', '4', '5', '6', '7', '8', '9', '+', '0', 'C'];

const handleNumpad = (key: string) => {
    // Mock functionality for now
    console.log('Numpad:', key);
};

const openCloseModal = () => {
    showCloseModal.value = true;
};

const viewOrders = () => {
    // Mock for now
    console.log('Ver órdenes');
};

const openClientModal = () => {
    showClientModal.value = true;
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

const selectClient = (client: Client) => {
    selectedClient.value = client;
    showClientModal.value = false;
};

const clearClient = () => {
    selectedClient.value = null;
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
            `/api/pos/partners/lookup?${params.toString()}`,
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

        const response = await fetch('/api/pos/customers', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-XSRF-TOKEN': xsrfToken(),
                Accept: 'application/json',
            },
            body: JSON.stringify(payload),
        });

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

        const index = clients.value.findIndex((c) => c.id === saved.id);
        if (index >= 0) {
            clients.value.splice(index, 1, saved);
        } else {
            clients.value.unshift(saved);
        }

        selectClient(saved);
        showCreateClient.value = false;
    } catch (e) {
        console.error('Error saving customer:', e);
    } finally {
        isSavingClient.value = false;
    }
};

const getUserInitials = () => {
    if (!props.session?.user?.name) return '?';
    const names = props.session.user.name.split(' ');
    if (names.length >= 2) {
        return (names[0][0] + names[1][0]).toUpperCase();
    }
    return props.session.user.name.substring(0, 2).toUpperCase();
};

// Online/Offline detection
const updateOnlineStatus = () => {
    isOnline.value = navigator.onLine;
};

const handleIframeMessage = (event: MessageEvent) => {
    if (event.origin !== window.location.origin) return;
    if (event.data?.type === 'pos:close-modal') {
        showCloseModal.value = false;
        if (event.data?.redirectTo) {
            router.visit(event.data.redirectTo);
        }
    }
};

// Watchers to persist cart and client to sessionStorage
watch(
    cart,
    (newCart) => {
        sessionStorage.setItem(cartStorageKey, JSON.stringify(newCart));
    },
    { deep: true },
);

watch(selectedClient, (newClient) => {
    if (newClient) {
        sessionStorage.setItem(clientStorageKey, JSON.stringify(newClient));
    } else {
        sessionStorage.removeItem(clientStorageKey);
    }
});

onMounted(() => {
    window.addEventListener('online', updateOnlineStatus);
    window.addEventListener('offline', updateOnlineStatus);
    window.addEventListener('message', handleIframeMessage);
    // Initial data comes from server props, refreshClients() available if needed

    const clearCart =
        new URLSearchParams(window.location.search).get('clear_cart') === '1';
    if (clearCart) {
        clearPersistedData();
        window.history.replaceState({}, '', `/pos/${props.session.id}`);
    }

    // Load persisted cart and client
    loadPersistedData();

    void loadProducts();
});

onUnmounted(() => {
    window.removeEventListener('online', updateOnlineStatus);
    window.removeEventListener('offline', updateOnlineStatus);
    window.removeEventListener('message', handleIframeMessage);
});

watch(activeCategory, () => {
    void loadProducts();
});

watch(searchQuery, () => {
    if (productsDebounceTimer) window.clearTimeout(productsDebounceTimer);
    productsDebounceTimer = window.setTimeout(() => {
        void loadProducts();
    }, 250);
});

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
    <PosLayout title="POS - Ventas">
        <template #header-actions>
            <div class="flex items-center gap-3">
                <!-- User Info -->
                <div class="flex items-center gap-2">
                    <Avatar class="h-8 w-8">
                        <AvatarFallback class="text-xs">
                            {{ getUserInitials() }}
                        </AvatarFallback>
                    </Avatar>
                    <span class="text-sm font-medium">{{
                        session.user?.name || 'Usuario'
                    }}</span>
                </div>

                <!-- WiFi Status -->
                <div class="flex items-center">
                    <Wifi v-if="isOnline" class="h-5 w-5 text-green-600" />
                    <WifiOff v-else class="h-5 w-5 text-red-600" />
                </div>

                <!-- Menu -->
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <Button variant="ghost" size="icon">
                            <Menu class="h-5 w-5" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-48">
                        <DropdownMenuItem @click="viewOrders">
                            <FileText class="mr-2 h-4 w-4" />
                            Ver Órdenes
                        </DropdownMenuItem>
                        <DropdownMenuItem @click="openCloseModal">
                            <LogOut class="mr-2 h-4 w-4" />
                            Cerrar Caja
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </template>
        <div class="flex h-[calc(100vh-80px)] min-w-0 gap-4">
            <!-- Center - Products (now full width without left sidebar) -->
            <div class="min-w-0 flex-1 space-y-4">
                <!-- Search + Category Pills -->
                <div class="flex min-w-0 items-center gap-3">
                    <!-- Search (20%) -->
                    <div class="relative w-[20%]">
                        <Search
                            class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                        />
                        <Input
                            v-model="searchQuery"
                            placeholder="Buscar productos..."
                            class="ml-[1%] h-16 w-[99%] pl-10"
                        />
                    </div>

                    <!-- Category Pills (Rest of space) -->
                    <div
                        class="scrollbar-thin scrollbar-thumb-gray-300 scrollbar-track-transparent flex min-w-0 flex-1 gap-2 overflow-x-auto pb-2"
                    >
                        <Button
                            v-for="category in categories"
                            :key="category.id"
                            @click="setCategory(category.id)"
                            :variant="
                                activeCategory === category.id
                                    ? 'default'
                                    : 'outline'
                            "
                            class="flex flex-shrink-0 items-center gap-2 whitespace-nowrap"
                        >
                            <component :is="category.icon" class="h-4 w-4" />
                            {{ category.name }}
                        </Button>
                    </div>
                </div>

                <!-- Products Grid -->
                <div
                    class="grid h-[calc(100%-60px)] grid-cols-2 content-start gap-3 overflow-y-auto pb-4 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5"
                >
                    <div
                        v-if="isLoadingProducts"
                        class="col-span-full flex items-center justify-center py-16 text-center text-muted-foreground"
                    >
                        Cargando productos...
                    </div>
                    <div
                        v-else-if="filteredProducts.length === 0"
                        class="col-span-full flex items-center justify-center py-16 text-center text-muted-foreground"
                    >
                        <template v-if="hasSearchQuery">
                            No se encontraron productos
                        </template>
                        <template v-else>
                            No hay productos para esta categoría
                        </template>
                    </div>
                    <Card
                        v-for="product in filteredProducts"
                        :key="product.id"
                        class="flex h-auto cursor-pointer flex-col p-3 transition-shadow hover:shadow-lg"
                        @click="addToCart(product)"
                    >
                        <div
                            class="mb-2 flex aspect-square w-full flex-shrink-0 items-center justify-center rounded-lg bg-muted"
                        >
                            <component
                                :is="
                                    categories.find(
                                        (c) => c.id === product.category_id,
                                    )?.icon
                                "
                                class="h-12 w-12 text-muted-foreground"
                            />
                        </div>
                        <h4
                            class="mb-1 line-clamp-2 text-sm leading-tight font-semibold"
                        >
                            {{ product.name }}
                        </h4>
                        <p class="mb-1 text-base font-bold text-primary">
                            {{ formatCurrency(product.price) }}
                        </p>
                        <Badge
                            v-if="product.duration"
                            variant="secondary"
                            class="w-fit text-xs"
                        >
                            {{ product.duration }}
                        </Badge>
                    </Card>
                </div>
            </div>

            <!-- Right Sidebar - Cart -->
            <div class="flex w-[26%] flex-col gap-4">
                <!-- Cart -->
                <Card class="flex flex-1 flex-col">
                    <div class="space-y-2 border-b p-4">
                        <div class="flex items-center justify-between">
                            <h3 class="font-semibold">Carrito</h3>
                            <Badge>{{ cartItemCount }} items</Badge>
                        </div>

                        <!-- Selected Client Display -->
                        <div
                            v-if="selectedClient"
                            class="flex items-center gap-2 rounded-md border border-blue-200 bg-blue-50 p-2"
                        >
                            <User class="h-4 w-4 text-blue-600" />
                            <div class="min-w-0 flex-1">
                                <p
                                    class="truncate text-xs font-medium text-blue-900"
                                >
                                    {{ selectedClient.name }}
                                </p>
                                <p class="text-xs text-blue-600">
                                    DNI: {{ selectedClient.dni }}
                                </p>
                            </div>
                            <Button
                                size="icon"
                                variant="ghost"
                                class="h-5 w-5"
                                @click="clearClient"
                            >
                                <X class="h-3 w-3" />
                            </Button>
                        </div>
                    </div>

                    <div class="flex-1 space-y-2 overflow-y-auto p-4">
                        <div
                            v-if="cart.length === 0"
                            class="py-8 text-center text-muted-foreground"
                        >
                            Carrito vacío
                        </div>

                        <div
                            v-for="item in cart"
                            :key="item.product_id"
                            class="flex items-center gap-2 rounded-lg bg-muted/50 p-2"
                        >
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium">
                                    {{ item.name }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{ formatCurrency(item.price) }}
                                </p>
                            </div>
                            <div class="flex items-center gap-1">
                                <Button
                                    size="icon"
                                    variant="ghost"
                                    @click="decrementQty(item)"
                                    class="h-6 w-6"
                                >
                                    <Minus class="h-3 w-3" />
                                </Button>
                                <span
                                    class="w-8 text-center text-sm font-medium"
                                    >{{ item.qty }}</span
                                >
                                <Button
                                    size="icon"
                                    variant="ghost"
                                    @click="incrementQty(item)"
                                    class="h-6 w-6"
                                >
                                    <Plus class="h-3 w-3" />
                                </Button>
                            </div>
                            <p class="w-20 text-right text-sm font-semibold">
                                {{ formatCurrency(item.subtotal) }}
                            </p>
                            <Button
                                size="icon"
                                variant="ghost"
                                @click="removeFromCart(item.product_id)"
                                class="h-6 w-6"
                            >
                                <Trash2 class="h-3 w-3" />
                            </Button>
                        </div>
                    </div>

                    <div class="space-y-2 border-t p-4">
                        <div class="flex items-center justify-between">
                            <span class="font-semibold">Total:</span>
                            <span class="text-2xl font-bold text-primary">{{
                                formatCurrency(cartTotal)
                            }}</span>
                        </div>
                    </div>
                </Card>

                <!-- Action Buttons -->
                <div class="grid grid-cols-3 gap-2">
                    <!-- Numpad -->
                    <Card class="col-span-2 p-2">
                        <div class="grid grid-cols-3 gap-2">
                            <Button
                                v-for="key in numpadKeys"
                                :key="key"
                                @click="handleNumpad(key)"
                                variant="outline"
                                size="lg"
                                class="h-12 text-lg font-semibold"
                                :class="key === 'C' ? 'text-destructive' : ''"
                            >
                                <Delete v-if="key === 'C'" class="h-5 w-5" />
                                <span v-else-if="key === '+'"></span>
                                <template v-else>{{ key }}</template>
                            </Button>
                        </div>
                    </Card>

                    <!-- Quick Actions -->
                    <div class="flex flex-col gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            :class="
                                selectedClient
                                    ? 'flex-1 bg-blue-100 font-semibold text-blue-700 hover:bg-blue-200'
                                    : 'flex-1 bg-blue-50 font-semibold text-blue-600 hover:bg-blue-100'
                            "
                            @click="openClientModal"
                        >
                            <User
                                class="h-4 w-4"
                                :class="selectedClient ? 'mr-1.5' : ''"
                            />
                            <span
                                v-if="selectedClient"
                                class="text-sm font-bold"
                                >{{ clientButtonText }}</span
                            >
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            class="flex-1 bg-green-50 font-semibold hover:bg-green-100"
                        >
                            Cant.
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            class="flex-1 font-semibold"
                        >
                            % desc.
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            class="flex-1 font-semibold"
                        >
                            Precio
                        </Button>
                    </div>
                </div>

                <!-- Payment Button - Bottom -->
                <Button
                    @click="processPayment"
                    :disabled="cart.length === 0"
                    size="lg"
                    class="h-16 w-full bg-gradient-to-r from-purple-600 to-purple-700 text-xl font-bold text-white shadow-lg hover:from-purple-700 hover:to-purple-800"
                >
                    PROCESAR PAGO →
                </Button>
            </div>
        </div>

        <!-- Client Selector Modal -->
        <Dialog v-model:open="showClientModal">
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
                                            <TableHead
                                                class="w-[1%]"
                                            ></TableHead>
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
                                                <SelectValue
                                                    placeholder="Tipo"
                                                />
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
                                        <Input
                                            v-model="newClientDocumentNumber"
                                        />
                                    </div>

                                    <div
                                        v-if="newClientDocumentType === 'RUC'"
                                        class="col-span-2 space-y-1"
                                    >
                                        <Label>Razón social</Label>
                                        <Input
                                            v-model="newClientBusinessName"
                                        />
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

                                    <div
                                        class="col-span-2 grid grid-cols-2 gap-3"
                                    >
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
                                        v-else-if="
                                            partnerLookupStatus === 'found'
                                        "
                                    >
                                        Encontrado: se actualizará y se marcará
                                        como cliente.
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

        <!-- Close Session Modal (integrated from Close.vue) -->
        <Dialog v-model:open="showCloseModal">
            <DialogContent
                class="h-[80vh] w-[calc(100vw-2rem)] max-w-[76.8rem] overflow-hidden p-0 sm:max-w-[76.8rem]"
            >
                <iframe
                    :src="`/pos/${session.id}/close?embed=1`"
                    class="h-full w-full border-0"
                ></iframe>
            </DialogContent>
        </Dialog>
    </PosLayout>
</template>
