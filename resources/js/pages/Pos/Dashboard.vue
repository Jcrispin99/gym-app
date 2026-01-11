<script setup lang="ts">
import PosLayout from '@/layouts/PosLayout.vue';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import { 
    Package, 
    CreditCard, 
    Dumbbell, 
    Search, 
    Plus, 
    Minus, 
    Trash2,
    X,
    Delete,
    User,
    UserPlus,
    Menu,
    FileText,
    LogOut,
    Wifi,
    WifiOff
} from 'lucide-vue-next';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Dialog,
    DialogContent,
} from '@/components/ui/dialog';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { router } from '@inertiajs/vue3';

// Types
interface Category {
    id: number;
    name: string;
    icon: any;
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
    customers: Client[]; // Initial customers from server
}

// Mock Data - Reordered with Suscripciones first
const categories = ref<Category[]>([
    { id: 2, name: 'Suscripciones', icon: CreditCard },
    { id: 1, name: 'Productos', icon: Package },
    { id: 3, name: 'Servicios', icon: Dumbbell },
]);

const mockProducts = ref<Product[]>([
    // Productos
    { id: 1, name: 'Proteína Whey Gold', price: 120.00, category_id: 1 },
    { id: 2, name: 'Creatina Monohidratada', price: 80.00, category_id: 1 },
    { id: 3, name: 'Toalla Premium', price: 25.00, category_id: 1 },
    { id: 4, name: 'Botella de Agua', price: 15.00, category_id: 1 },
    { id: 5, name: 'Guantes de Gym', price: 35.00, category_id: 1 },
    { id: 6, name: 'Shaker Proteína', price: 20.00, category_id: 1 },
    
    // Suscripciones
    { id: 7, name: 'Plan Mensual', price: 150.00, category_id: 2, duration: '30 días' },
    { id: 8, name: 'Plan Trimestral', price: 400.00, category_id: 2, duration: '90 días' },
    { id: 9, name: 'Plan Semestral', price: 700.00, category_id: 2, duration: '180 días' },
    { id: 10, name: 'Plan Anual', price: 1200.00, category_id: 2, duration: '365 días' },
    
    // Servicios
    { id: 11, name: 'Entrenamiento Personal', price: 80.00, category_id: 3, duration: '1 sesión' },
    { id: 12, name: 'Evaluación Nutricional', price: 60.00, category_id: 3 },
    { id: 13, name: 'Clase Grupal', price: 25.00, category_id: 3 },
]);

// Props
const props = defineProps<Props>();

// Clients from API (hybrid: initialized with server data, refreshable)
const clients = ref<Client[]>(props.customers);

// State - Default to Suscripciones (id: 2)
const activeCategory = ref<number>(2);
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

const cart = ref<CartItem[]>([]);
const showCloseModal = ref<boolean>(false);
const showClientModal = ref<boolean>(false);
const selectedClient = ref<Client | null>(null);
const clientSearchQuery = ref<string>('');
const isOnline = ref<boolean>(navigator.onLine);

// Computed
const filteredProducts = computed(() => {
    let filtered = mockProducts.value.filter(p => p.category_id === activeCategory.value);
    
    if (searchQuery.value) {
        filtered = filtered.filter(p => 
            p.name.toLowerCase().includes(searchQuery.value.toLowerCase())
        );
    }
    
    return filtered;
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
    return clients.value.filter(client => 
        client.name.toLowerCase().includes(query) ||
        client.dni?.includes(query) ||
        client.email?.toLowerCase().includes(query)
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

const addToCart = (product: Product) => {
    const existing = cart.value.find(item => item.product_id === product.id);
    
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
    const index = cart.value.findIndex(item => item.product_id === productId);
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
        total: cartTotal.value
    });
};

const formatCurrency = (value: number): string => {
    return `S/ ${value.toFixed(2)}`;
};

// Numpad
const numpadKeys = [
    '1', '2', '3',
    '4', '5', '6',
    '7', '8', '9',
    '+', '0', 'C'
];

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
};

const selectClient = (client: Client) => {
    selectedClient.value = client;
    showClientModal.value = false;
};

const clearClient = () => {
    selectedClient.value = null;
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

// Refresh clients from API
const refreshClients = async () => {
    try {
        const response = await fetch('/api/pos/customers');
        const data = await response.json();
        clients.value = data;
    } catch (error) {
        console.error('Error refreshing clients:', error);
    }
};

// Watchers to persist cart and client to sessionStorage
watch(cart, (newCart) => {
    sessionStorage.setItem(cartStorageKey, JSON.stringify(newCart));
}, { deep: true });

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
    // Initial data comes from server props, refreshClients() available if needed
    
    // Load persisted cart and client
    loadPersistedData();
});

onUnmounted(() => {
    window.removeEventListener('online', updateOnlineStatus);
    window.removeEventListener('offline', updateOnlineStatus);
});
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
                    <span class="text-sm font-medium">{{ session.user?.name || 'Usuario' }}</span>
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
                            <FileText class="h-4 w-4 mr-2" />
                            Ver Órdenes
                        </DropdownMenuItem>
                        <DropdownMenuItem @click="openCloseModal">
                            <LogOut class="h-4 w-4 mr-2" />
                            Cerrar Caja
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </template>
        <div class="flex h-[calc(100vh-80px)] gap-4">
            <!-- Center - Products (now full width without left sidebar) -->
            <div class="flex-1 space-y-4">
                <!-- Search + Category Pills -->
                <div class="flex gap-3">
                    <!-- Search -->
                    <div class="relative flex-1">
                        <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                        <Input
                            v-model="searchQuery"
                            placeholder="Buscar productos..."
                            class="pl-10"
                        />
                    </div>
                    
                    <!-- Category Pills -->
                    <div class="flex gap-2">
                        <Button
                            v-for="category in categories"
                            :key="category.id"
                            @click="setCategory(category.id)"
                            :variant="activeCategory === category.id ? 'default' : 'outline'"
                            class="flex items-center gap-2"
                        >
                            <component :is="category.icon" class="h-4 w-4" />
                            {{ category.name }}
                        </Button>
                    </div>
                </div>

                <!-- Products Grid -->
                <div class="grid grid-cols-6 gap-3 overflow-y-auto h-[calc(100%-60px)] content-start">
                    <Card
                        v-for="product in filteredProducts"
                        :key="product.id"
                        class="p-3 cursor-pointer hover:shadow-lg transition-shadow flex flex-col h-auto"
                        @click="addToCart(product)"
                    >
                        <div class="w-full aspect-square bg-muted rounded-lg mb-2 flex items-center justify-center flex-shrink-0">
                            <component :is="categories.find(c => c.id === product.category_id)?.icon" 
                                       class="h-12 w-12 text-muted-foreground" />
                        </div>
                        <h4 class="font-semibold text-sm mb-1 line-clamp-2 leading-tight">{{ product.name }}</h4>
                        <p class="text-base font-bold text-primary mb-1">{{ formatCurrency(product.price) }}</p>
                        <Badge v-if="product.duration" variant="secondary" class="text-xs w-fit">
                            {{ product.duration }}
                        </Badge>
                    </Card>
                </div>
            </div>

            <!-- Right Sidebar - Cart -->
            <div class="w-[30%] flex flex-col gap-4">
                <!-- Cart -->
                <Card class="flex-1 flex flex-col">
                    <div class="p-4 border-b space-y-2">
                        <div class="flex items-center justify-between">
                            <h3 class="font-semibold">Carrito</h3>
                            <Badge>{{ cartItemCount }} items</Badge>
                        </div>
                        
                        <!-- Selected Client Display -->
                        <div v-if="selectedClient" class="flex items-center gap-2 p-2 bg-blue-50 rounded-md border border-blue-200">
                            <User class="h-4 w-4 text-blue-600" />
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-medium text-blue-900 truncate">{{ selectedClient.name }}</p>
                                <p class="text-xs text-blue-600">DNI: {{ selectedClient.dni }}</p>
                            </div>
                            <Button size="icon" variant="ghost" class="h-5 w-5" @click="clearClient">
                                <X class="h-3 w-3" />
                            </Button>
                        </div>
                    </div>
                    
                    <div class="flex-1 overflow-y-auto p-4 space-y-2">
                        <div v-if="cart.length === 0" class="text-center text-muted-foreground py-8">
                            Carrito vacío
                        </div>
                        
                        <div
                            v-for="item in cart"
                            :key="item.product_id"
                            class="flex items-center gap-2 p-2 rounded-lg bg-muted/50"
                        >
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium truncate">{{ item.name }}</p>
                                <p class="text-xs text-muted-foreground">{{ formatCurrency(item.price) }}</p>
                            </div>
                            <div class="flex items-center gap-1">
                                <Button size="icon" variant="ghost" @click="decrementQty(item)" class="h-6 w-6">
                                    <Minus class="h-3 w-3" />
                                </Button>
                                <span class="w-8 text-center text-sm font-medium">{{ item.qty }}</span>
                                <Button size="icon" variant="ghost" @click="incrementQty(item)" class="h-6 w-6">
                                    <Plus class="h-3 w-3" />
                                </Button>
                            </div>
                            <p class="text-sm font-semibold w-20 text-right">{{ formatCurrency(item.subtotal) }}</p>
                            <Button size="icon" variant="ghost" @click="removeFromCart(item.product_id)" class="h-6 w-6">
                                <Trash2 class="h-3 w-3" />
                            </Button>
                        </div>
                    </div>
                    
                    <div class="p-4 border-t space-y-2">
                        <div class="flex justify-between items-center">
                            <span class="font-semibold">Total:</span>
                            <span class="text-2xl font-bold text-primary">{{ formatCurrency(cartTotal) }}</span>
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
                            :class="selectedClient ? 'flex-1 bg-blue-100 hover:bg-blue-200 text-blue-700 font-semibold' : 'flex-1 bg-blue-50 hover:bg-blue-100 text-blue-600 font-semibold'"
                            @click="openClientModal"
                        >
                            <User class="h-4 w-4" :class="selectedClient ? 'mr-1.5' : ''" />
                            <span v-if="selectedClient" class="text-sm font-bold">{{ clientButtonText }}</span>
                        </Button>
                        <Button variant="outline" size="sm" class="flex-1 bg-green-50 hover:bg-green-100 font-semibold">
                            Cant.
                        </Button>
                        <Button variant="outline" size="sm" class="flex-1 font-semibold">
                            % desc.
                        </Button>
                        <Button variant="outline" size="sm" class="flex-1 font-semibold">
                            Precio
                        </Button>
                        
                    </div>
                </div>

                <!-- Payment Button - Bottom -->
                <Button
                    @click="processPayment"
                    :disabled="cart.length === 0"
                    size="lg"
                    class="w-full h-16 text-white font-bold text-xl bg-gradient-to-r from-purple-600 to-purple-700 hover:from-purple-700 hover:to-purple-800 shadow-lg"
                >
                    PROCESAR PAGO →
                </Button>
            </div>
        </div>



        <!-- Client Selector Modal -->
        <Dialog v-model:open="showClientModal">
            <DialogContent class="max-w-2xl max-h-[90vh] overflow-y-auto">
                <div class="space-y-6">
                    <div class="flex items-center justify-between">
                        <h2 class="text-2xl font-bold">Seleccionar Cliente</h2>
                        <Button size="icon" variant="ghost" @click="showClientModal = false">
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

        <!-- Close Session Modal (integrated from Close.vue) -->
        <Dialog v-model:open="showCloseModal">
            <DialogContent class="max-w-2xl max-h-[90vh] overflow-y-auto">
                <iframe 
                    :src="`/pos/${session.id}/close`" 
                    class="w-full h-[80vh] border-0"
                ></iframe>
            </DialogContent>
        </Dialog>
    </PosLayout>
</template>
