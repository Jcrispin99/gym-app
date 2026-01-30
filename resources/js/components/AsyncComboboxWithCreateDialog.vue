<script setup lang="ts">
import AsyncCombobox from '@/components/AsyncCombobox.vue';
import FormDialog from '@/components/FormDialog.vue';
import type { Component } from 'vue';
import { computed, ref, watch } from 'vue';

type Id = number | string;

const props = withDefaults(
    defineProps<{
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
        class?: string;
        width?: string;

        showCreate?: boolean;
        createLabel?: (query: string) => string;

        createTitle: string;
        createDescription?: string;
        formComponent: Component;
        formComponentProps?: Record<string, any>;
        initialNameProp?: string;
        createModeProp?: string;
        createdId?: (entity: any) => Id | null;
    }>(),
    {
        placeholder: 'Buscar...',
        disabled: false,
        queryParam: 'q',
        limitParam: 'limit',
        limit: 5,
        extraParams: () => ({}),
        optionId: (o: any) => o?.id as Id,
        optionLabel: (o: any) => (o?.name ?? '').toString(),
        class: undefined,
        width: undefined,

        showCreate: true,
        createLabel: (q: string) => (q ? `Crear \"${q}\"` : 'Crear'),

        createDescription: undefined,
        formComponentProps: () => ({}),
        initialNameProp: 'initialName',
        createModeProp: 'mode',
        createdId: (e: any) => (e?.id ?? null) as Id | null,
    },
);

const emit = defineEmits<{
    (e: 'update:modelValue', value: Id | null): void;
    (e: 'select', option: any): void;
    (e: 'created', entity: any): void;
}>();

const createOpen = ref(false);
const createQuery = ref('');
const createFormRef = ref<any>(null);

const openCreate = (query: string) => {
    createQuery.value = query;
    createOpen.value = true;
};

const closeCreate = (value: boolean) => {
    createOpen.value = value;
};

watch(createOpen, (isOpen) => {
    if (!isOpen) {
        createQuery.value = '';
    }
});

const computedFormProps = computed(() => {
    return {
        ...props.formComponentProps,
        [props.createModeProp]: 'create',
        [props.initialNameProp]:
            createQuery.value.trim() !== '' ? createQuery.value.trim() : undefined,
    };
});

const handleSaved = (entity: any) => {
    createOpen.value = false;
    emit('created', entity);
    emit('update:modelValue', props.createdId(entity));
};
</script>

<template>
    <AsyncCombobox
        :model-value="modelValue"
        :placeholder="placeholder"
        :disabled="disabled"
        :search-url="searchUrl"
        :get-url-template="getUrlTemplate"
        :query-param="queryParam"
        :limit-param="limitParam"
        :limit="limit"
        :extra-params="extraParams"
        :option-id="optionId"
        :option-label="optionLabel"
        :show-create="showCreate"
        :create-label="createLabel"
        :class="props.class"
        :width="width"
        @update:model-value="(v) => emit('update:modelValue', v)"
        @select="(o) => emit('select', o)"
        @create="openCreate"
    />

    <FormDialog
        :open="createOpen"
        @update:open="closeCreate"
        :title="createTitle"
        :description="createDescription"
        :form-ref="createFormRef"
    >
        <component
            :is="formComponent"
            ref="createFormRef"
            v-bind="computedFormProps"
            @saved="handleSaved"
        />
    </FormDialog>
</template>

