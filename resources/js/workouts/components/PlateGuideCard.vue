<script setup lang="ts">
import { gramsToKg, type PlateLoadResult } from '@/lib/plateCalculator';
import type { PlateProfile } from '@/settings/types';
import { ref } from 'vue';

const props = defineProps<{
    plateLoad: PlateLoadResult;
    formatPlateStack: string;
    weightUnit: string;
    plateProfile: PlateProfile;
}>();

defineEmits<{
    applyNearest: [];
    changePlate: [denominationG: number, change: 1 | -1];
}>();

const editing = ref(false);

const countFor = (denominationG: number): number => {
    return props.plateLoad.per_side.find((step) => step.denomination_g === denominationG)?.count ?? 0;
};

const maxPerSideFor = (count: number): number => Math.floor(count / 2);
</script>

<template>
    <div class="rounded-xl border border-border bg-card/60 px-4 py-4 text-center">
        <p class="text-xs tracking-wide text-muted-foreground uppercase">Plates</p>
        <p class="mt-2 font-mono text-lg text-foreground">{{ formatPlateStack }}</p>
        <p v-if="!plateLoad.exact" class="mt-2 text-sm text-muted-foreground">
            Nearest loadable:
            {{ gramsToKg(plateLoad.total_g) }}{{ weightUnit }}
            <span v-if="plateLoad.delta_g > 0">(+{{ gramsToKg(plateLoad.delta_g) }})</span>
            <span v-else-if="plateLoad.delta_g < 0">({{ gramsToKg(plateLoad.delta_g) }})</span>
        </p>
        <div class="mt-3 flex flex-wrap items-center justify-center gap-2">
            <button
                v-if="!plateLoad.exact"
                type="button"
                class="rounded-full border border-primary/40 bg-primary/10 px-4 py-2 text-sm font-medium text-primary"
                @click="$emit('applyNearest')"
            >
                Use nearest
            </button>
            <button
                type="button"
                class="rounded-full border border-border px-4 py-2 text-sm font-medium text-foreground transition-colors hover:bg-secondary"
                :aria-expanded="editing"
                @click="editing = !editing"
            >
                {{ editing ? 'Done editing' : 'Edit plates' }}
            </button>
        </div>
        <div v-if="editing" data-testid="plate-editor" class="mt-4 space-y-2 text-left">
            <p class="text-xs tracking-wide text-muted-foreground uppercase">Adjust per side</p>
            <div
                v-for="plate in plateProfile.plates"
                :key="plate.denomination_g"
                class="flex items-center justify-between gap-3 rounded-lg border border-border/60 bg-background/40 px-3 py-2"
            >
                <span class="font-mono text-sm text-foreground">{{ gramsToKg(plate.denomination_g) }}{{ weightUnit }}</span>
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        class="flex size-8 items-center justify-center rounded-full border border-border text-lg text-foreground disabled:opacity-30"
                        :aria-label="`Remove ${gramsToKg(plate.denomination_g)}${weightUnit} plate per side`"
                        :disabled="countFor(plate.denomination_g) === 0"
                        @click="$emit('changePlate', plate.denomination_g, -1)"
                    >
                        −
                    </button>
                    <span class="w-5 text-center font-mono text-sm text-foreground">{{ countFor(plate.denomination_g) }}</span>
                    <button
                        type="button"
                        class="flex size-8 items-center justify-center rounded-full border border-primary/40 bg-primary/10 text-lg text-primary disabled:opacity-30"
                        :aria-label="`Add ${gramsToKg(plate.denomination_g)}${weightUnit} plate per side`"
                        :disabled="countFor(plate.denomination_g) >= maxPerSideFor(plate.count)"
                        @click="$emit('changePlate', plate.denomination_g, 1)"
                    >
                        +
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
