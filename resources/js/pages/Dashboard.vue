<script setup lang="ts">
import DashboardChart from '@/components/DashboardChart.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import {
    AlertCircle,
    DollarSign,
    Package,
    TrendingUp,
    Users,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface Stat {
    total_sales: number;
    total_orders: number;
    total_customers: number;
    average_ticket?: number;
    unpaid_invoices?: number;
}

interface Order {
    id: number;
    serie_correlative: string;
    date_formatted: string;
    status: string;
    status_class: string;
    total: number;
    customer_name: string;
    customer_email: string;
    image_url: string | null;
}

interface Product {
    id: number;
    name: string;
    sku: string;
    price?: number;
    stock?: number;
    total_sold?: number;
    qty_sold?: number;
    image_url: string | null;
}

interface Customer {
    id: number;
    name: string;
    email: string;
    orders_count: number;
    total_spent: number;
}

interface ChartPoint {
    date: string;
    value: number;
}

interface Props {
    filters?: {
        from: string;
        to: string;
    };
    overall?: Stat;
    today?: Stat;
    recent_orders?: Order[];
    stock_threshold?: Product[];
    top_products?: Product[];
    top_customers?: Customer[];
    charts?: {
        sales: ChartPoint[];
        visitors: ChartPoint[];
    };
}

const props = withDefaults(defineProps<Props>(), {
    filters: () => ({ from: '', to: '' }),
    overall: () => ({ total_sales: 0, total_orders: 0, total_customers: 0 }),
    today: () => ({ total_sales: 0, total_orders: 0, total_customers: 0 }),
    recent_orders: () => [],
    stock_threshold: () => [],
    top_products: () => [],
    top_customers: () => [],
    charts: () => ({ sales: [], visitors: [] }),
});

const fromDate = ref(props.filters?.from ?? '');
const toDate = ref(props.filters?.to ?? '');

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

const formatCurrency = (value: number) => {
    return new Intl.NumberFormat('es-PE', {
        style: 'currency',
        currency: 'PEN',
    }).format(value);
};

const applyFilters = () => {
    router.get(
        '/dashboard',
        {
            from: fromDate.value || undefined,
            to: toDate.value || undefined,
        },
        { preserveState: true, preserveScroll: true },
    );
};

// Auto-apply filters when dates change (debounced could be better but let's stick to simple change or manual?
// The snippet has inputs. I'll add a watch or simpler: use @change)
watch([fromDate, toDate], () => {
    applyFilters();
});

// Chart Data
const salesChartData = computed(() => ({
    labels: props.charts?.sales?.map((p) => p.date) ?? [],
    datasets: [
        {
            label: 'Ventas',
            backgroundColor: '#10b981', // emerald-500
            borderColor: '#10b981',
            data: props.charts?.sales?.map((p) => p.value) ?? [],
            fill: false,
            tension: 0.4,
        },
    ],
}));

const visitorsChartData = computed(() => ({
    labels: props.charts?.visitors?.map((p) => p.date) ?? [],
    datasets: [
        {
            label: 'Visitas',
            backgroundColor: '#3b82f6', // blue-500
            borderColor: '#3b82f6',
            data: props.charts?.visitors?.map((p) => p.value) ?? [],
            fill: false,
            tension: 0.4,
        },
    ],
}));
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-4 pb-4 lg:pb-6">
            <div class="mb-5 flex flex-wrap items-center justify-between gap-4">
                <div class="grid gap-1.5">
                    <p
                        class="text-xl !leading-normal font-bold text-gray-800 dark:text-white"
                    >
                        Hola! Bienvenido
                    </p>
                    <p class="!leading-normal text-gray-600 dark:text-gray-300">
                        Revisa rápidamente lo que sucede en tu tienda
                    </p>
                </div>
                <div class="flex gap-1.5">
                    <div class="relative inline-block !w-[160px] w-full">
                        <input
                            v-model="fromDate"
                            type="date"
                            class="flex min-h-[39px] w-full rounded-md border px-3 py-2 text-sm text-gray-600 transition-all hover:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-400"
                            placeholder="Desde"
                        />
                    </div>
                    <div class="relative inline-block !w-[160px] w-full">
                        <input
                            v-model="toDate"
                            type="date"
                            class="flex min-h-[39px] w-full rounded-md border px-3 py-2 text-sm text-gray-600 transition-all hover:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-400"
                            placeholder="Hasta"
                        />
                    </div>
                </div>
            </div>

            <div class="mt-3.5 flex gap-2.5 max-xl:flex-wrap">
                <!-- Left Column -->
                <div class="flex flex-1 flex-col gap-8 max-xl:flex-auto">
                    <!-- Overall Details -->
                    <div class="flex flex-col gap-2">
                        <p
                            class="text-base font-semibold text-gray-600 dark:text-gray-300"
                        >
                            Detalles Generales
                        </p>
                        <div
                            class="rounded bg-white p-4 shadow dark:bg-gray-900"
                        >
                            <div class="flex flex-wrap gap-4">
                                <!-- Card: Total Sales -->
                                <div class="flex min-w-[200px] flex-1 gap-2.5">
                                    <div
                                        class="flex h-[60px] w-full max-w-[60px] items-center justify-center rounded-full bg-emerald-100 text-emerald-600 dark:bg-emerald-900 dark:text-emerald-400"
                                    >
                                        <DollarSign class="h-8 w-8" />
                                    </div>
                                    <div class="grid place-content-start gap-1">
                                        <p
                                            class="text-base leading-none font-semibold text-gray-800 dark:text-white"
                                        >
                                            {{
                                                formatCurrency(
                                                    overall?.total_sales ?? 0,
                                                )
                                            }}
                                        </p>
                                        <p
                                            class="text-xs font-semibold text-gray-600 dark:text-gray-300"
                                        >
                                            Ventas Totales
                                        </p>
                                    </div>
                                </div>
                                <!-- Card: Total Orders -->
                                <div class="flex min-w-[200px] flex-1 gap-2.5">
                                    <div
                                        class="flex h-[60px] w-full max-w-[60px] items-center justify-center rounded-full bg-blue-100 text-blue-600 dark:bg-blue-900 dark:text-blue-400"
                                    >
                                        <Package class="h-8 w-8" />
                                    </div>
                                    <div class="grid place-content-start gap-1">
                                        <p
                                            class="text-base leading-none font-semibold text-gray-800 dark:text-white"
                                        >
                                            {{ overall?.total_orders ?? 0 }}
                                        </p>
                                        <p
                                            class="text-xs font-semibold text-gray-600 dark:text-gray-300"
                                        >
                                            Órdenes Totales
                                        </p>
                                    </div>
                                </div>
                                <!-- Card: Total Customers -->
                                <div class="flex min-w-[200px] flex-1 gap-2.5">
                                    <div
                                        class="flex h-[60px] w-full max-w-[60px] items-center justify-center rounded-full bg-orange-100 text-orange-600 dark:bg-orange-900 dark:text-orange-400"
                                    >
                                        <Users class="h-8 w-8" />
                                    </div>
                                    <div class="grid place-content-start gap-1">
                                        <p
                                            class="text-base leading-none font-semibold text-gray-800 dark:text-white"
                                        >
                                            {{ overall?.total_customers ?? 0 }}
                                        </p>
                                        <p
                                            class="text-xs font-semibold text-gray-600 dark:text-gray-300"
                                        >
                                            Clientes
                                        </p>
                                    </div>
                                </div>
                                <!-- Card: Avg Order -->
                                <div class="flex min-w-[200px] flex-1 gap-2.5">
                                    <div
                                        class="flex h-[60px] w-full max-w-[60px] items-center justify-center rounded-full bg-purple-100 text-purple-600 dark:bg-purple-900 dark:text-purple-400"
                                    >
                                        <TrendingUp class="h-8 w-8" />
                                    </div>
                                    <div class="grid place-content-start gap-1">
                                        <p
                                            class="text-base leading-none font-semibold text-gray-800 dark:text-white"
                                        >
                                            {{
                                                formatCurrency(
                                                    overall?.average_ticket ??
                                                        0,
                                                )
                                            }}
                                        </p>
                                        <p
                                            class="text-xs font-semibold text-gray-600 dark:text-gray-300"
                                        >
                                            Ticket Promedio
                                        </p>
                                    </div>
                                </div>
                                <!-- Card: Unpaid -->
                                <div class="flex min-w-[200px] flex-1 gap-2.5">
                                    <div
                                        class="flex h-[60px] w-full max-w-[60px] items-center justify-center rounded-full bg-red-100 text-red-600 dark:bg-red-900 dark:text-red-400"
                                    >
                                        <AlertCircle class="h-8 w-8" />
                                    </div>
                                    <div class="grid place-content-start gap-1">
                                        <p
                                            class="text-base leading-none font-semibold text-gray-800 dark:text-white"
                                        >
                                            {{
                                                formatCurrency(
                                                    overall?.unpaid_invoices ??
                                                        0,
                                                )
                                            }}
                                        </p>
                                        <p
                                            class="text-xs font-semibold text-gray-600 dark:text-gray-300"
                                        >
                                            Por cobrar
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Today Details -->
                    <div class="flex flex-col gap-2">
                        <p
                            class="text-base font-semibold text-gray-600 dark:text-gray-300"
                        >
                            Detalles de Hoy
                        </p>
                        <div class="rounded shadow">
                            <div
                                class="flex flex-wrap gap-4 border-b bg-white p-4 dark:border-gray-800 dark:bg-gray-900"
                            >
                                <div class="flex min-w-[200px] flex-1 gap-2.5">
                                    <div
                                        class="flex h-[60px] w-full max-w-[60px] items-center justify-center rounded-full bg-emerald-100/50 text-emerald-600 dark:bg-emerald-900/50"
                                    >
                                        <DollarSign class="h-6 w-6" />
                                    </div>
                                    <div class="grid place-content-start gap-1">
                                        <p
                                            class="text-base leading-none font-semibold text-gray-800 dark:text-white"
                                        >
                                            {{
                                                formatCurrency(
                                                    today?.total_sales ?? 0,
                                                )
                                            }}
                                        </p>
                                        <p
                                            class="text-xs font-semibold text-gray-600 dark:text-gray-300"
                                        >
                                            Ventas Hoy
                                        </p>
                                    </div>
                                </div>
                                <div class="flex min-w-[200px] flex-1 gap-2.5">
                                    <div
                                        class="flex h-[60px] w-full max-w-[60px] items-center justify-center rounded-full bg-blue-100/50 text-blue-600 dark:bg-blue-900/50"
                                    >
                                        <Package class="h-6 w-6" />
                                    </div>
                                    <div class="grid place-content-start gap-1">
                                        <p
                                            class="text-base leading-none font-semibold text-gray-800 dark:text-white"
                                        >
                                            {{ today?.total_orders ?? 0 }}
                                        </p>
                                        <p
                                            class="text-xs font-semibold text-gray-600 dark:text-gray-300"
                                        >
                                            Órdenes Hoy
                                        </p>
                                    </div>
                                </div>
                                <div class="flex min-w-[200px] flex-1 gap-2.5">
                                    <div
                                        class="flex h-[60px] w-full max-w-[60px] items-center justify-center rounded-full bg-orange-100/50 text-orange-600 dark:bg-orange-900/50"
                                    >
                                        <Users class="h-6 w-6" />
                                    </div>
                                    <div class="grid place-content-start gap-1">
                                        <p
                                            class="text-base leading-none font-semibold text-gray-800 dark:text-white"
                                        >
                                            {{ today?.total_customers ?? 0 }}
                                        </p>
                                        <p
                                            class="text-xs font-semibold text-gray-600 dark:text-gray-300"
                                        >
                                            Clientes Hoy
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Recent Orders List -->
                            <div
                                v-for="order in recent_orders"
                                :key="order.id"
                                class="border-b bg-white p-4 transition-all hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-950"
                            >
                                <div class="flex flex-wrap gap-4">
                                    <div
                                        class="flex min-w-[180px] flex-1 gap-2.5"
                                    >
                                        <div class="flex flex-col gap-1.5">
                                            <p
                                                class="text-base leading-none font-semibold text-gray-800 dark:text-white"
                                            >
                                                #{{ order.id }}
                                            </p>
                                            <p
                                                class="text-gray-600 dark:text-gray-300"
                                            >
                                                {{ order.date_formatted }}
                                            </p>
                                            <p
                                                class="w-fit rounded-full px-2 py-0.5 text-xs font-medium"
                                                :class="{
                                                    'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400':
                                                        order.status === 'Paid',
                                                    'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400':
                                                        order.status ===
                                                        'Pending',
                                                    'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400':
                                                        ![
                                                            'Paid',
                                                            'Pending',
                                                        ].includes(
                                                            order.status,
                                                        ),
                                                }"
                                            >
                                                {{ order.status }}
                                            </p>
                                        </div>
                                    </div>
                                    <div
                                        class="flex min-w-[180px] flex-1 gap-2.5"
                                    >
                                        <div class="flex flex-col gap-1.5">
                                            <p
                                                class="text-base leading-none font-semibold text-gray-800 dark:text-white"
                                            >
                                                {{
                                                    formatCurrency(order.total)
                                                }}
                                            </p>
                                            <p
                                                class="text-gray-600 dark:text-gray-300"
                                            >
                                                Venta POS
                                            </p>
                                        </div>
                                    </div>
                                    <div
                                        class="flex min-w-[200px] flex-1 gap-2.5"
                                    >
                                        <div class="flex flex-col gap-1.5">
                                            <p
                                                class="text-base text-gray-800 dark:text-white"
                                            >
                                                {{ order.customer_name }}
                                            </p>
                                            <p
                                                class="max-w-[180px] break-words text-gray-600 dark:text-gray-300"
                                            >
                                                {{ order.customer_email }}
                                            </p>
                                        </div>
                                    </div>
                                    <div
                                        class="flex min-w-[180px] flex-1 items-center justify-end gap-2.5"
                                    >
                                        <!-- Actions -->
                                        <!-- Add a link if needed -->
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Stock Threshold -->
                    <div class="flex flex-col gap-2">
                        <p
                            class="text-base font-semibold text-gray-600 dark:text-gray-300"
                        >
                            Stock Crítico (Bajo/Agotado)
                        </p>
                        <div class="rounded shadow">
                            <div
                                v-for="prod in stock_threshold"
                                :key="prod.id"
                                class="relative grid grid-cols-2 gap-y-6 border-b bg-white p-4 transition-all hover:bg-gray-50 max-sm:grid-cols-[1fr_auto] dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-950"
                            >
                                <div class="flex gap-2.5">
                                    <div
                                        class="relative h-[65px] max-h-[65px] w-full max-w-[65px] overflow-hidden rounded border border-dashed border-gray-300 dark:border-gray-800 dark:mix-blend-exclusion dark:invert"
                                    >
                                        <!-- Placeholder Image -->
                                        <Package
                                            class="absolute top-1/2 left-1/2 h-8 w-8 -translate-x-1/2 -translate-y-1/2 text-gray-400"
                                        />
                                    </div>
                                    <div class="flex flex-col gap-1.5">
                                        <p
                                            class="text-base font-semibold text-gray-800 dark:text-white"
                                        >
                                            {{ prod.name }}
                                        </p>
                                        <p
                                            class="text-gray-600 dark:text-gray-300"
                                        >
                                            SKU - {{ prod.sku }}
                                        </p>
                                    </div>
                                </div>
                                <div
                                    class="flex items-center justify-between gap-1.5"
                                >
                                    <div class="flex flex-col gap-1.5">
                                        <p
                                            class="text-base font-semibold text-gray-800 dark:text-white"
                                        >
                                            {{
                                                formatCurrency(prod.price || 0)
                                            }}
                                        </p>
                                        <p
                                            class="font-medium"
                                            :class="
                                                (prod.stock || 0) === 0
                                                    ? 'text-red-500'
                                                    : 'text-amber-500'
                                            "
                                        >
                                            {{ prod.stock }} Stock
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div
                                v-if="stock_threshold.length === 0"
                                class="bg-white p-4 text-sm text-gray-500 dark:bg-gray-900"
                            >
                                No hay productos con stock crítico.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column (Stats) -->
                <div
                    class="flex w-[360px] max-w-full flex-col gap-2 max-sm:w-full"
                >
                    <p
                        class="text-base font-semibold text-gray-600 dark:text-gray-300"
                    >
                        Estadísticas de la Tienda
                    </p>
                    <div class="gap-4 rounded bg-white shadow dark:bg-gray-900">
                        <!-- Sales Chart -->
                        <div class="border-b px-4 py-2 dark:border-gray-800">
                            <div class="flex justify-between gap-2">
                                <div
                                    class="flex flex-col justify-between gap-1"
                                >
                                    <p
                                        class="text-xs font-semibold text-gray-600 dark:text-gray-300"
                                    >
                                        Ventas
                                    </p>
                                    <p
                                        class="text-lg leading-none font-bold text-gray-800 dark:text-white"
                                    >
                                        {{
                                            formatCurrency(
                                                overall?.total_sales ?? 0,
                                            )
                                        }}
                                    </p>
                                </div>
                                <div
                                    class="flex flex-col justify-between gap-1"
                                >
                                    <p
                                        class="text-right text-xs font-semibold text-gray-400 dark:text-white"
                                    >
                                        {{ filters?.from }} - {{ filters?.to }}
                                    </p>
                                </div>
                            </div>
                            <div class="mt-4 h-[200px] w-full">
                                <DashboardChart :data="salesChartData" />
                            </div>
                        </div>

                        <!-- Visitors Chart -->
                        <div class="border-b px-4 py-2 dark:border-gray-800">
                            <div class="flex justify-between gap-2">
                                <div
                                    class="flex flex-col justify-between gap-1"
                                >
                                    <p
                                        class="text-xs font-semibold text-gray-600 dark:text-gray-300"
                                    >
                                        Visitas (Asistencias)
                                    </p>
                                    <p
                                        class="text-lg leading-none font-bold text-gray-800 dark:text-white"
                                    >
                                        {{
                                            charts.visitors
                                                ? charts.visitors.reduce(
                                                      (acc, curr) =>
                                                          acc + curr.value,
                                                      0,
                                                  )
                                                : 0
                                        }}
                                    </p>
                                </div>
                                <div
                                    class="flex flex-col justify-between gap-1"
                                >
                                    <p
                                        class="text-right text-xs font-semibold text-gray-400 dark:text-white"
                                    >
                                        {{ filters?.from }} - {{ filters?.to }}
                                    </p>
                                </div>
                            </div>
                            <div class="mt-4 h-[200px] w-full">
                                <DashboardChart :data="visitorsChartData" />
                            </div>
                        </div>

                        <!-- Top Selling Products -->
                        <div class="border-b dark:border-gray-800">
                            <div class="flex items-center justify-between p-4">
                                <p
                                    class="text-base font-semibold text-gray-600 dark:text-gray-300"
                                >
                                    Productos Más Vendidos
                                </p>
                            </div>
                            <div class="flex flex-col gap-4 p-4 pt-0">
                                <div
                                    v-for="prod in top_products"
                                    :key="prod.id"
                                    class="flex items-center justify-between gap-2 border-b pb-2 last:border-0"
                                >
                                    <div class="flex items-center gap-2">
                                        <div
                                            class="flex h-10 w-10 items-center justify-center rounded bg-gray-100 dark:bg-gray-800"
                                        >
                                            <Package
                                                class="h-5 w-5 text-gray-500"
                                            />
                                        </div>
                                        <div>
                                            <p
                                                class="text-sm font-semibold text-gray-800 dark:text-white"
                                            >
                                                {{ prod.name }}
                                            </p>
                                            <p class="text-xs text-gray-500">
                                                {{ prod.qty_sold }} vendidos
                                            </p>
                                        </div>
                                    </div>
                                    <p class="text-sm font-bold">
                                        {{
                                            formatCurrency(prod.total_sold || 0)
                                        }}
                                    </p>
                                </div>
                                <div
                                    v-if="top_products.length === 0"
                                    class="text-center text-sm text-gray-500"
                                >
                                    Sin datos.
                                </div>
                            </div>
                        </div>

                        <!-- Top Customers -->
                        <div class="border-b dark:border-gray-800">
                            <div class="flex items-center justify-between p-4">
                                <p
                                    class="text-base font-semibold text-gray-600 dark:text-gray-300"
                                >
                                    Clientes Top
                                </p>
                            </div>
                            <div
                                class="flex flex-col gap-4 border-b p-4 pt-0 transition-all last:border-b-0 hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-gray-950"
                            >
                                <div
                                    v-for="customer in top_customers"
                                    :key="customer.id"
                                    class="flex justify-between gap-1.5 border-b pb-2 last:border-0"
                                >
                                    <div class="flex flex-col">
                                        <p
                                            class="font-semibold text-gray-600 dark:text-gray-300"
                                        >
                                            {{ customer.name }}
                                        </p>
                                        <p
                                            class="text-xs text-gray-600 dark:text-gray-300"
                                        >
                                            {{ customer.email }}
                                        </p>
                                    </div>
                                    <div class="flex flex-col items-end">
                                        <p
                                            class="font-semibold text-gray-800 dark:text-white"
                                        >
                                            {{
                                                formatCurrency(
                                                    customer.total_spent,
                                                )
                                            }}
                                        </p>
                                        <p
                                            class="text-xs text-gray-600 dark:text-gray-300"
                                        >
                                            {{ customer.orders_count }} Órdenes
                                        </p>
                                    </div>
                                </div>
                                <div
                                    v-if="top_customers.length === 0"
                                    class="text-center text-sm text-gray-500"
                                >
                                    Sin datos.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
