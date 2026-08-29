<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import {
    addWorkingRound,
    blockTitle,
    buildDraftBlocks,
    datetimeLocalToPayload,
    isFinishedAtInFuture,
    removeWorkingRound,
    roundsForBlock,
    syncWarmUpWeights,
    warmUpRoundsForBlock,
    type DraftBlock,
} from '@/workouts/lib/historicalCreate';
import type { HistoricalCreateForm } from '@/workouts/types';
import { Head, useForm } from '@inertiajs/vue3';
import { Minus, Plus } from 'lucide-vue-next';
import { computed, nextTick, ref, watch } from 'vue';

const props = defineProps<{
    form: HistoricalCreateForm;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'History', href: '/history' },
    { title: 'Add historical', href: '/history/create' },
    { title: props.form.routine_name, href: '#' },
];

type Step = 'when' | 'sets';

const step = ref<Step>('when');
const isDeload = ref(false);
const finishedAtLocal = ref('');
const whenError = ref('');
const blocks = ref<DraftBlock[]>(buildDraftBlocks(props.form.blocks, false, props.form.deload_weight_factor, props.form.deload_reps_factor));

watch(isDeload, (deload) => {
    blocks.value = buildDraftBlocks(
        props.form.blocks.filter((block) => blocks.value.some((kept) => kept.position === block.position)),
        deload,
        props.form.deload_weight_factor,
        props.form.deload_reps_factor,
    ).map((fresh) => {
        const existing = blocks.value.find((block) => block.position === fresh.position);
        if (!existing || existing.working_set_count === fresh.working_set_count) {
            return fresh;
        }

        // Preserve +/- set count when toggling deload: rebuild from base then adjust count.
        const adjusted = { ...fresh, sets: [...fresh.sets], working_set_count: fresh.working_set_count };
        while (adjusted.working_set_count < existing.working_set_count) {
            addWorkingRound(adjusted);
        }
        while (adjusted.working_set_count > existing.working_set_count) {
            removeWorkingRound(adjusted);
        }

        syncWarmUpWeights(adjusted);

        return adjusted;
    });
});

const submitForm = useForm({
    finished_at: '',
    mode: 'standard' as 'standard' | 'deload',
    blocks: [] as {
        position: number;
        working_set_count: number;
        sets: {
            exercise_position: number;
            set_index: number;
            reps: number;
            weight_kg?: number;
            segments?: { weight_kg: number }[];
        }[];
        warm_up_sets?: {
            exercise_position: number;
            set_index: number;
            reps: number;
            weight_kg: number;
        }[];
    }[],
});

const canContinueFromWhen = computed(() => finishedAtLocal.value !== '');
const canSubmit = computed(() => blocks.value.length > 0 && finishedAtLocal.value !== '');

const finishedAtLabel = computed(() => {
    if (!finishedAtLocal.value) {
        return '';
    }

    const date = new Date(finishedAtLocal.value);

    return Number.isNaN(date.getTime()) ? finishedAtLocal.value : date.toLocaleString();
});

const skipBlock = (position: number) => {
    blocks.value = blocks.value.filter((block) => block.position !== position);
};

const onWorkingWeightChange = async (block: DraftBlock) => {
    await nextTick();
    syncWarmUpWeights(block);
};

const goToSets = () => {
    whenError.value = '';

    if (!finishedAtLocal.value) {
        whenError.value = 'Pick when you finished this workout.';

        return;
    }

    if (isFinishedAtInFuture(finishedAtLocal.value)) {
        whenError.value = 'Finished time can’t be in the future.';

        return;
    }

    step.value = 'sets';
};

const goToWhen = () => {
    step.value = 'when';
};

const submit = () => {
    if (!canSubmit.value || submitForm.processing) {
        return;
    }

    if (isFinishedAtInFuture(finishedAtLocal.value)) {
        whenError.value = 'Finished time can’t be in the future.';
        step.value = 'when';

        return;
    }

    for (const block of blocks.value) {
        syncWarmUpWeights(block);
    }

    submitForm.finished_at = datetimeLocalToPayload(finishedAtLocal.value);
    submitForm.mode = isDeload.value ? 'deload' : 'standard';
    submitForm.blocks = blocks.value.map((block) => {
        const payload: (typeof submitForm.blocks)[number] = {
            position: block.position,
            working_set_count: block.working_set_count,
            sets: block.sets.map((set) => {
                if (set.is_dropset) {
                    return {
                        exercise_position: set.exercise_position,
                        set_index: set.set_index,
                        reps: set.reps,
                        segments: set.segments.map((segment) => ({ weight_kg: segment.weight_kg })),
                    };
                }

                return {
                    exercise_position: set.exercise_position,
                    set_index: set.set_index,
                    reps: set.reps,
                    weight_kg: set.weight_kg ?? 0,
                };
            }),
        };

        if (block.warm_ups.length > 0) {
            payload.warm_up_sets = block.warm_ups.map((set) => ({
                exercise_position: set.exercise_position,
                set_index: set.set_index,
                reps: set.reps,
                weight_kg: set.weight_kg,
            }));
        }

        return payload;
    });

    submitForm.post(route('history.store', props.form.routine_slug));
};
</script>

<template>
    <Head :title="`Add historical · ${form.routine_name}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-5 p-4 text-foreground">
            <div>
                <p class="font-mono text-xs tracking-wide text-primary uppercase">History</p>
                <h1 class="mt-1 text-2xl font-semibold tracking-tight">{{ form.routine_name }}</h1>
                <p class="mt-1 text-sm text-muted-foreground">Log a past session without Play.</p>
            </div>

            <template v-if="step === 'when'">
                <div class="max-w-md space-y-4 rounded-xl border border-border p-4">
                    <div>
                        <h2 class="text-lg font-semibold">When did you finish?</h2>
                        <p class="mt-1 text-sm text-muted-foreground">Pick the date and time before logging sets.</p>
                    </div>
                    <label class="flex flex-col gap-1 text-xs text-muted-foreground">
                        Finished at
                        <input
                            v-model="finishedAtLocal"
                            type="datetime-local"
                            class="rounded border border-border bg-background px-3 py-2 text-sm text-foreground"
                        />
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <input v-model="isDeload" type="checkbox" class="size-4 rounded border-border" />
                        Deload
                    </label>
                    <p v-if="whenError" class="text-sm text-destructive">{{ whenError }}</p>
                    <button
                        type="button"
                        class="rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-primary-foreground transition-opacity hover:opacity-90 disabled:opacity-50"
                        :disabled="!canContinueFromWhen"
                        @click="goToSets"
                    >
                        Continue to sets
                    </button>
                </div>
            </template>

            <template v-else>
                <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-border bg-card/40 px-3 py-2 text-sm">
                    <p>
                        <span class="text-muted-foreground">Finished</span>
                        {{ finishedAtLabel }}
                        <span v-if="isDeload" class="ml-2 font-mono text-xs text-muted-foreground uppercase">Deload</span>
                    </p>
                    <button type="button" class="text-sm text-primary hover:underline" @click="goToWhen">Change</button>
                </div>

                <p v-if="submitForm.hasErrors" class="text-sm text-destructive">
                    {{ Object.values(submitForm.errors)[0] }}
                </p>

                <p v-if="!blocks.length" class="text-sm text-muted-foreground">Everything marked skipped. Keep at least one group.</p>

                <section v-for="block in blocks" :key="block.position" class="overflow-hidden rounded-xl border border-border">
                    <header class="flex flex-wrap items-center justify-between gap-2 border-b border-border bg-card/40 px-3 py-2">
                        <div class="flex flex-wrap items-baseline gap-x-2">
                            <p class="font-medium">{{ blockTitle(block) }}</p>
                            <p v-if="block.is_superset" class="font-mono text-xs text-muted-foreground uppercase">Superset</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                class="rounded-md border border-border px-2 py-1 text-xs hover:bg-card disabled:opacity-40"
                                :disabled="block.working_set_count <= 1"
                                @click="removeWorkingRound(block)"
                            >
                                <Minus class="inline size-3.5" /> Set
                            </button>
                            <button
                                type="button"
                                class="rounded-md border border-border px-2 py-1 text-xs hover:bg-card"
                                @click="addWorkingRound(block)"
                            >
                                <Plus class="inline size-3.5" /> Set
                            </button>
                            <button
                                type="button"
                                class="rounded-md px-2 py-1 text-xs text-destructive hover:bg-destructive/10"
                                @click="skipBlock(block.position)"
                            >
                                Mark group skipped
                            </button>
                        </div>
                    </header>

                    <div class="divide-y divide-border bg-background">
                        <div v-if="block.warm_ups.length" class="space-y-3 bg-card/20 px-3 py-3">
                            <div>
                                <p class="font-mono text-xs tracking-wide text-muted-foreground uppercase">Warm-up</p>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    Weights are % of Set 1 for each exercise. Change Set 1 and they update; edit a warm-up row to change it.
                                </p>
                            </div>
                            <div v-for="round in warmUpRoundsForBlock(block)" :key="`wu-${round.setIndex}`" class="space-y-2">
                                <p class="font-mono text-xs text-muted-foreground">
                                    Step {{ round.setIndex + 1 }}
                                    <span v-if="round.sets[0]"> · {{ round.sets[0].percent_of_working }}%</span>
                                </p>
                                <ul class="space-y-2 border-l-2 border-muted pl-3">
                                    <li
                                        v-for="(set, idx) in round.sets"
                                        :key="`wu-${set.exercise_position}-${set.set_index}-${idx}`"
                                        class="rounded-lg border border-border/80 bg-background px-3 py-2.5"
                                    >
                                        <p v-if="round.sets.length > 1 || block.is_superset" class="mb-1.5 text-sm font-medium">
                                            {{ set.exercise_name }}
                                        </p>
                                        <div class="flex flex-wrap items-end gap-3">
                                            <label class="flex flex-col gap-1 text-xs text-muted-foreground">
                                                Weight kg
                                                <input
                                                    v-model.number="set.weight_kg"
                                                    type="number"
                                                    min="0"
                                                    step="0.25"
                                                    class="w-28 rounded border border-border bg-background px-2 py-1.5 text-sm text-foreground"
                                                />
                                            </label>
                                            <label class="flex flex-col gap-1 text-xs text-muted-foreground">
                                                Reps
                                                <input
                                                    v-model.number="set.reps"
                                                    type="number"
                                                    min="0"
                                                    max="100"
                                                    class="w-20 rounded border border-border bg-background px-2 py-1.5 text-sm text-foreground"
                                                />
                                            </label>
                                        </div>
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <div v-for="round in roundsForBlock(block)" :key="round.setIndex" class="px-3 py-3">
                            <p class="font-mono text-xs tracking-wide text-primary uppercase">
                                Set {{ round.setIndex + 1 }}
                                <span class="text-muted-foreground"> / {{ block.working_set_count }}</span>
                            </p>
                            <ul class="mt-2 space-y-3">
                                <li
                                    v-for="(set, idx) in round.sets"
                                    :key="`${set.exercise_position}-${set.set_index}-${idx}`"
                                    class="rounded-lg border border-border/80 bg-card/30 px-3 py-2.5"
                                >
                                    <p v-if="round.sets.length > 1 || block.is_superset" class="mb-1.5 text-sm font-medium">
                                        {{ set.exercise_name }}
                                    </p>

                                    <div v-if="set.is_dropset" class="flex flex-wrap items-end gap-3">
                                        <label
                                            v-for="(segment, si) in set.segments"
                                            :key="si"
                                            class="flex flex-col gap-1 text-xs text-muted-foreground"
                                        >
                                            Seg {{ si + 1 }} kg
                                            <input
                                                v-model.number="segment.weight_kg"
                                                type="number"
                                                min="0"
                                                step="0.25"
                                                class="w-24 rounded border border-border bg-background px-2 py-1.5 text-sm text-foreground"
                                                @input="round.setIndex === 0 && onWorkingWeightChange(block)"
                                            />
                                        </label>
                                        <label class="flex flex-col gap-1 text-xs text-muted-foreground">
                                            Reps
                                            <input
                                                v-model.number="set.reps"
                                                type="number"
                                                min="0"
                                                max="100"
                                                class="w-20 rounded border border-border bg-background px-2 py-1.5 text-sm text-foreground"
                                            />
                                        </label>
                                    </div>
                                    <div v-else class="flex flex-wrap items-end gap-3">
                                        <label class="flex flex-col gap-1 text-xs text-muted-foreground">
                                            Weight kg
                                            <input
                                                v-model.number="set.weight_kg"
                                                type="number"
                                                min="0"
                                                step="0.25"
                                                class="w-28 rounded border border-border bg-background px-2 py-1.5 text-sm text-foreground"
                                                @input="round.setIndex === 0 && onWorkingWeightChange(block)"
                                            />
                                        </label>
                                        <label class="flex flex-col gap-1 text-xs text-muted-foreground">
                                            Reps
                                            <input
                                                v-model.number="set.reps"
                                                type="number"
                                                min="0"
                                                max="100"
                                                class="w-20 rounded border border-border bg-background px-2 py-1.5 text-sm text-foreground"
                                            />
                                        </label>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>
                </section>

                <button
                    type="button"
                    class="rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-primary-foreground transition-opacity hover:opacity-90 disabled:opacity-50"
                    :disabled="!canSubmit || submitForm.processing"
                    @click="submit"
                >
                    Save workout
                </button>
            </template>
        </div>
    </AppLayout>
</template>
