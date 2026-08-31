<script setup lang="ts">
import { ChevronDown } from 'lucide-vue-next';

const {
    expanded,
    label,
    summary,
    flush = false,
} = defineProps<{
    expanded: boolean;
    label: string;
    summary: string;
    flush?: boolean;
}>();

defineEmits<{
    toggle: [];
}>();
</script>

<template>
    <div :class="flush ? '' : 'mt-3 border-t border-border pt-3'">
        <button type="button" class="flex w-full items-center justify-between gap-2 text-left" :aria-expanded="expanded" @click="$emit('toggle')">
            <span class="min-w-0">
                <span class="block text-xs text-muted-foreground">
                    <slot name="label">{{ label }}</slot>
                </span>
                <span class="block truncate font-mono text-sm text-foreground">{{ summary }}</span>
            </span>
            <ChevronDown class="size-4 shrink-0 text-muted-foreground transition-transform" :class="expanded ? 'rotate-180' : ''" />
        </button>
        <div v-if="expanded" class="mt-3 space-y-3">
            <slot />
        </div>
    </div>
</template>
