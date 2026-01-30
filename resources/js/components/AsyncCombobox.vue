<script setup lang="ts">
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
import { Check, ChevronsUpDown, Plus } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

type Id = number | string;

interface Props {
    modelValue: Id | null;
    placeholder?: string;
    disabled?: boolean;
    searchUrl: string;
    getUrlTemplate?: string;
    queryParam?: string;
    limitParam?: string;
    limit?: number;
    extraParams?: Record<string, any>;
    optionId?: (option: any) => Id;
    optionLabel?: (option: any) => string;
    showCreate?: boolean;
    createLabel?: (query: string) => string;
    class?: string;
    width?: string;
}

interface Emits {
    (e: 'update:modelValue', value: Id | null): void;
    (e: 'select', option: any): void;
    (e: 'create', query: string): void;
}

const props = withDefaults(defineProps<Props>(), {
    modelValue: null,
    placeholder: 'Buscar...',
    disabled: false,
    queryParam: 'q',
    limitParam: 'limit',
    limit: 5,
    extraParams: () => ({}),
    optionId: (o: any) => o?.id as Id,
    optionLabel: (o: any) => (o?.name ?? '').toString(),
    showCreate: false,
    createLabel: (q: string) => (q ? `Crear "${q}"` : 'Crear'),
    width: 'w-[420px]',
});

const emit = defineEmits<Emits>();

const open = ref(false);
const searchQuery = ref('');
const options = ref<any[]>([]);
const loading = ref(false);
const selectedOption = ref<any | null>(null);

let searchTimeout: ReturnType<typeof setTimeout>;

const selectedLabel = computed(() => {
    return selectedOption.value
        ? props.optionLabel(selectedOption.value)
        : props.placeholder;
});

const parseOptions = (payload: any): any[] => {
    if (Array.isArray(payload)) return payload;
    if (Array.isArray(payload?.data)) return payload.data;
    return [];
};

const search = async (query: string) => {
    loading.value = true;
    try {
        const params: any = {
            ...props.extraParams,
            [props.limitParam]: props.limit,
        };
        if (query && query.length >= 1) {
            params[props.queryParam] = query;
        }

        const response = await axios.get(props.searchUrl, {
            params,
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        options.value = parseOptions(response.data);
    } catch (e) {
        console.error('Error searching:', e);
        options.value = [];
    } finally {
        loading.value = false;
    }
};

const handleSearch = (query: string) => {
    searchQuery.value = query;
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        search(query);
    }, 250);
};

const selectOption = (option: any) => {
    selectedOption.value = option;
    emit('update:modelValue', props.optionId(option));
    emit('select', option);
    open.value = false;
};

const createOptionLabel = computed(() =>
    props.createLabel(searchQuery.value.trim()),
);

watch(
    () => props.modelValue,
    async (newValue) => {
        if (!newValue) {
            selectedOption.value = null;
            return;
        }

        if (
            selectedOption.value &&
            props.optionId(selectedOption.value) === newValue
        ) {
            return;
        }

        if (!props.getUrlTemplate) {
            const found = options.value.find(
                (o) => props.optionId(o) === newValue,
            );
            selectedOption.value = found ?? null;
            return;
        }

        const url = props.getUrlTemplate.replace('{id}', String(newValue));
        try {
            const response = await axios.get(url, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            selectedOption.value = response.data?.data ?? response.data;
        } catch (e) {
            console.error('Error fetching selected option:', e);
            selectedOption.value = null;
        }
    },
    { immediate: true },
);

watch(open, (isOpen) => {
    if (isOpen && options.value.length === 0) {
        search('');
    }
});
</script>

<template>
    <Popover v-model:open="open" :modal="true">
        <PopoverTrigger as-child>
            <Button
                variant="outline"
                role="combobox"
                :aria-expanded="open"
                :class="cn('w-full justify-between', props.class)"
                :disabled="disabled"
            >
                <span class="truncate">{{ selectedLabel }}</span>
                <ChevronsUpDown class="ml-2 h-4 w-4 shrink-0 opacity-50" />
            </Button>
        </PopoverTrigger>
        <PopoverContent
            :class="cn('z-[100] p-0', width)"
            :style="{ pointerEvents: 'auto' }"
        >
            <Command :filter-function="() => 1">
                <CommandInput
                    :placeholder="placeholder"
                    @input="(e: any) => handleSearch(e.target.value)"
                />
                <CommandList>
                    <CommandEmpty>
                        {{
                            loading
                                ? 'Buscando...'
                                : 'No se encontraron resultados.'
                        }}
                    </CommandEmpty>

                    <CommandGroup v-if="options.length > 0">
                        <CommandItem
                            v-for="option in options"
                            :key="String(optionId(option))"
                            :value="String(optionId(option))"
                            @select="selectOption(option)"
                        >
                            <Check
                                :class="
                                    cn(
                                        'mr-2 h-4 w-4',
                                        modelValue === optionId(option)
                                            ? 'opacity-100'
                                            : 'opacity-0',
                                    )
                                "
                            />
                            <div class="flex-1">
                                <div class="font-medium">
                                    <slot name="option" :option="option">
                                        {{ optionLabel(option) }}
                                    </slot>
                                </div>
                            </div>
                        </CommandItem>
                    </CommandGroup>

                    <CommandGroup v-if="showCreate">
                        <CommandItem
                            value="__create__"
                            @select="
                                () => {
                                    emit('create', searchQuery.trim());
                                    open = false;
                                }
                            "
                        >
                            <Plus class="mr-2 h-4 w-4" />
                            <span>{{ createOptionLabel }}</span>
                        </CommandItem>
                    </CommandGroup>
                </CommandList>
            </Command>
        </PopoverContent>
    </Popover>
</template>
