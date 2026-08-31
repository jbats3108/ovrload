<script setup lang="ts">
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        class?: string;
    }>(),
    {
        class: 'font-semibold tracking-wide',
    },
);
</script>

<template>
    <span :class="cn(props.class)"><span class="text-primary">OVR</span><span class="text-foreground">LOAD</span></span>
</template>
