<script setup lang="ts">
defineProps<{
    name: string;
    caption: string;
    alt: string;
}>();
</script>

<template>
    <figure class="mt-4 overflow-hidden rounded-lg border border-border bg-card">
        <img class="w-full md:hidden" :src="`/images/guide/${name}-mobile.png`" :alt="alt" />
        <img class="hidden w-full md:block" :src="`/images/guide/${name}-desktop.png`" :alt="alt" />
        <figcaption class="border-t border-border px-3 py-2 text-sm text-muted-foreground">{{ caption }}</figcaption>
    </figure>
</template>
