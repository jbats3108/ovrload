<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { historyBlockTitle, historyRowsForBlock, historyWarmUpGroups } from '@/workouts/lib/historyDisplay';
import { useHistoryDelete } from '@/workouts/lib/historyMutations';
import type { WorkoutPayload } from '@/workouts/types';
import { Head, useForm } from '@inertiajs/vue3';
import { Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    history: {
        workout: WorkoutPayload;
        can_re_evaluate: boolean;
    };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'History', href: '/history' },
    { title: props.history.workout.routine_name, href: '#' },
];

const workingSets = props.history.workout.blocks.flatMap((block) => block.sets.filter((set) => set.group_type === 'working'));

const form = useForm({
    sets: workingSets.map((set) => ({
        id: set.id,
        reps: set.logged_reps ?? set.target_reps ?? 0,
        weight_kg: set.logged_weight_kg ?? set.target_weight_kg ?? 0,
    })),
});

const setFieldIndex = Object.fromEntries(form.sets.map((set, index) => [set.id, index]));

const { deleteForm, destroy: deleteWorkout } = useHistoryDelete();

const blockRows = computed(() =>
    props.history.workout.blocks.map((block) => ({
        block,
        title: historyBlockTitle(block),
        rows: historyRowsForBlock(block.sets),
        showExerciseHeadings: block.is_superset || block.exercises.length > 1,
    })),
);

const saveWorkout = () => {
    if (form.processing || !form.isDirty) {
        return;
    }

    form.put(route('history.update', props.history.workout.id), {
        preserveScroll: true,
    });
};

const removeWorkout = () => deleteWorkout(props.history.workout.id, props.history.workout.routine_name);
</script>

<template>
    <Head :title="`History · ${history.workout.routine_name}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-1 flex-col gap-5 p-4 text-foreground">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="font-mono text-xs tracking-wide text-primary uppercase">History</p>
                    <h1 class="mt-1 text-2xl font-semibold tracking-tight">{{ history.workout.routine_name }}</h1>
                    <p class="mt-1 font-mono text-xs text-muted-foreground">{{ history.workout.mode }}</p>
                </div>
                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-md bg-destructive px-3 py-2 text-sm font-semibold text-destructive-foreground transition-colors hover:bg-destructive/90 disabled:opacity-50"
                    :disabled="deleteForm.processing"
                    @click="removeWorkout"
                >
                    <Trash2 class="size-4" />
                    Delete
                </button>
            </div>

            <form class="flex flex-col gap-5" @submit.prevent="saveWorkout">
                <section
                    v-for="{ block, title, rows, showExerciseHeadings } in blockRows"
                    :key="block.id"
                    class="overflow-hidden rounded-xl border border-border"
                >
                    <header class="flex flex-wrap items-baseline gap-x-2 gap-y-0.5 border-b border-border bg-card/40 px-3 py-2">
                        <p class="font-medium">{{ title }}</p>
                        <p v-if="block.is_superset" class="font-mono text-xs text-muted-foreground uppercase">Superset</p>
                    </header>

                    <div class="divide-y divide-border">
                        <div v-for="row in rows" :key="row.key" class="px-3 py-2.5">
                            <template v-if="row.type === 'warm_up'">
                                <p class="font-mono text-xs tracking-wide text-muted-foreground uppercase">Warm-up</p>
                                <ul class="mt-1.5 space-y-1.5 border-l-2 border-muted pl-3">
                                    <li v-for="(group, gi) in historyWarmUpGroups(row.sets)" :key="gi" class="text-sm">
                                        <p v-if="group.exerciseName" class="font-medium text-foreground">{{ group.exerciseName }}</p>
                                        <p class="font-mono text-muted-foreground">{{ group.loads.join(' · ') }}</p>
                                    </li>
                                </ul>
                            </template>

                            <template v-else>
                                <p class="font-mono text-xs tracking-wide text-primary uppercase">
                                    Working
                                    <span v-if="showExerciseHeadings" class="tracking-normal text-foreground normal-case">
                                        · {{ row.exerciseName }}
                                    </span>
                                </p>
                                <ul class="mt-1.5 space-y-2 border-l-2 border-primary/40 pl-3">
                                    <li v-for="set in row.sets" :key="set.id" class="flex flex-wrap items-center gap-2">
                                        <span class="w-10 shrink-0 font-mono text-xs text-muted-foreground">Set {{ set.set_index + 1 }}</span>
                                        <label class="flex items-center gap-1 text-xs text-muted-foreground">
                                            <span class="sr-only">Reps</span>
                                            <input
                                                v-model.number="form.sets[setFieldIndex[set.id]].reps"
                                                type="number"
                                                min="0"
                                                class="w-14 rounded border border-border bg-background px-1.5 py-1 text-sm"
                                                aria-label="Reps"
                                            />
                                        </label>
                                        <span class="text-xs text-muted-foreground">×</span>
                                        <template v-if="!set.is_dropset">
                                            <label class="flex items-center gap-1 text-xs text-muted-foreground">
                                                <span class="sr-only">Weight</span>
                                                <input
                                                    v-model.number="form.sets[setFieldIndex[set.id]].weight_kg"
                                                    type="number"
                                                    min="0"
                                                    step="0.01"
                                                    inputmode="decimal"
                                                    class="w-16 rounded border border-border bg-background px-1.5 py-1 text-sm"
                                                    aria-label="Weight (kg)"
                                                />
                                                <span>kg</span>
                                            </label>
                                        </template>
                                        <span v-else class="font-mono text-xs text-muted-foreground">dropset</span>
                                    </li>
                                </ul>
                            </template>
                        </div>
                    </div>
                </section>

                <div class="flex flex-wrap items-center gap-3">
                    <button
                        type="button"
                        class="rounded-md bg-primary px-4 py-2.5 text-sm font-semibold text-primary-foreground transition-opacity hover:opacity-90 disabled:opacity-50"
                        :disabled="form.processing || !form.isDirty"
                        @click="saveWorkout"
                    >
                        Save
                    </button>
                    <p v-if="history.can_re_evaluate" class="text-xs text-muted-foreground">
                        Edits may update routine weights when this is your latest non-deload finish for this routine.
                    </p>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
