<script setup lang="ts">
import DeloadMultiplierFields from '@/routines/components/DeloadMultiplierFields.vue';
import EditorDisclosure from '@/routines/components/EditorDisclosure.vue';
import { useRoutineEditor } from '@/routines/composables/useRoutineEditor';
import { formatDeloadSummary } from '@/routines/lib/deload';
import type { EditorDensity } from '@/routines/types';
import { computed } from 'vue';

const { variant = 'desktop' } = defineProps<{
    variant?: EditorDensity;
}>();

const { form, deloadExpanded, toggleDeloadExpanded } = useRoutineEditor();

const summary = computed(() => formatDeloadSummary(form.deload_weight_factor, form.deload_reps_factor, form.deload_every_n));
</script>

<template>
    <section v-if="variant === 'desktop'" class="border-t border-border bg-card/40 px-4 py-3">
        <h3 class="text-sm font-medium">Deload</h3>
        <p class="mt-1 max-w-3xl text-xs text-muted-foreground">Overrides for this routine. Defaults live in Preferences.</p>
        <DeloadMultiplierFields variant="desktop" />
    </section>

    <EditorDisclosure v-else :expanded="deloadExpanded" label="Deload" :summary="summary" @toggle="toggleDeloadExpanded">
        <template #label> Deload <span class="text-muted-foreground/80">(this routine)</span> </template>
        <p class="text-xs text-muted-foreground">Overrides for this routine. Defaults: Preferences.</p>
        <DeloadMultiplierFields variant="mobile" />
    </EditorDisclosure>
</template>
