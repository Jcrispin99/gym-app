<script setup lang="ts">
import { ref, computed } from 'vue';
import { Check, X, ChevronsUpDown } from 'lucide-vue-next';
import { cn } from '@/lib/utils';
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
import { Badge } from '@/components/ui/badge';

interface Option {
  value: string | number;
  label: string;
}

interface Props {
  options: Option[];
  modelValue: (string | number)[];
  placeholder?: string;
  emptyText?: string;
}

const props = withDefaults(defineProps<Props>(), {
  placeholder: 'Select items...',
  emptyText: 'No items found.',
});

const emit = defineEmits<{
  'update:modelValue': [value: (string | number)[]];
}>();

const open = ref(false);

const selectedLabels = computed(() => {
  return props.modelValue
    .map(value => props.options.find(opt => opt.value === value)?.label)
    .filter(Boolean);
});

const toggleOption = (value: string | number) => {
  const index = props.modelValue.indexOf(value);
  const newValue = [...props.modelValue];
  
  if (index > -1) {
    newValue.splice(index, 1);
  } else {
    newValue.push(value);
  }
  
  emit('update:modelValue', newValue);
};

const removeOption = (value: string | number) => {
  const newValue = props.modelValue.filter(v => v !== value);
  emit('update:modelValue', newValue);
};

const isSelected = (value: string | number) => {
  return props.modelValue.includes(value);
};
</script>

<template>
  <Popover v-model:open="open">
    <PopoverTrigger as-child>
      <Button
        variant="outline"
        role="combobox"
        :aria-expanded="open"
        class="w-full justify-between"
      >
        <div class="flex flex-wrap gap-1" v-if="selectedLabels.length > 0">
          <Badge
            v-for="(label, index) in selectedLabels"
            :key="index"
            variant="secondary"
            class="mr-1"
          >
            {{ label }}
            <button
              class="ml-1 rounded-full outline-none ring-offset-background focus:ring-2 focus:ring-ring focus:ring-offset-2"
              @click.stop="removeOption(modelValue[index])"
            >
              <X class="h-3 w-3" />
            </button>
          </Badge>
        </div>
        <span v-else class="text-muted-foreground">{{ placeholder }}</span>
        <ChevronsUpDown class="ml-2 h-4 w-4 shrink-0 opacity-50" />
      </Button>
    </PopoverTrigger>
    <PopoverContent class="w-full p-0">
      <Command>
        <CommandInput placeholder="Buscar..." />
        <CommandEmpty>{{ emptyText }}</CommandEmpty>
        <CommandList>
          <CommandGroup>
            <CommandItem
              v-for="option in options"
              :key="option.value"
              :value="option.value"
              @select="toggleOption(option.value)"
            >
              <Check
                :class="cn(
                  'mr-2 h-4 w-4',
                  isSelected(option.value) ? 'opacity-100' : 'opacity-0'
                )"
              />
              {{ option.label }}
            </CommandItem>
          </CommandGroup>
        </CommandList>
      </Command>
    </PopoverContent>
  </Popover>
</template>
