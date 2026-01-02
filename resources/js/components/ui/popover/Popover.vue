<script setup lang="ts">
import { PopoverRoot, type PopoverRootEmits, type PopoverRootProps } from 'radix-vue';
import { computed, type HTMLAttributes } from 'vue';

const props = defineProps<PopoverRootProps & { class?: HTMLAttributes['class'] }>();
const emits = defineEmits<PopoverRootEmits>();

const delegatedProps = computed(() => {
  const { class: _, ...delegated } = props;
  return delegated;
});
</script>

<template>
  <PopoverRoot
    v-bind="delegatedProps"
    @update:open="emits('update:open', $event)"
  >
    <slot />
  </PopoverRoot>
</template>
