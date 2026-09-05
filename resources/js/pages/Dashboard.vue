<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import RoutineCard from '@/routines/components/RoutineCard.vue';
import { deleteRoutine as deleteRoutineMutation, duplicateRoutine as duplicateRoutineMutation } from '@/routines/lib/routineMutations';
import type { Routine } from '@/routines/types';
import { confirmDialog } from '@/shared/lib/confirmDialog';
import { formatDate } from '@/shared/lib/formatDate';
import { type BreadcrumbItem } from '@/types';
import { clearParkedBlocks } from '@/workouts/lib/playerSessionMutations';
import { abandonWorkout, finishWorkout } from '@/workouts/lib/workoutMutations';
import type { HistoryWorkout, InProgressWorkout } from '@/workouts/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps<{
    data: {
        routines: Routine[];
        in_progress_workout: InProgressWorkout | null;
        recent_finished_workouts: HistoryWorkout[];
    };
}>();

const page = usePage();
const formErrors = computed(() => Object.values(page.props.errors ?? {}));
const routineMutating = ref(false);
const workoutMutating = ref(false);
const cardBusy = computed(() => routineMutating.value || workoutMutating.value);

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

const startWorkout = (routineSlug: string, mode: 'standard' | 'deload' = 'standard') => {
    if (workoutMutating.value || routineMutating.value) {
        return;
    }
    workoutMutating.value = true;
    router.post(
        route('workouts.store', { routine: routineSlug }),
        { mode },
        {
            onFinish: () => {
                workoutMutating.value = false;
            },
        },
    );
};

const canStart = (routine: Routine) => !props.data.in_progress_workout && routine.can_start === true;

const startBlockedReason = (routine: Routine) => {
    if (!routine.can_start) {
        return 'Add exercises first';
    }
    if (props.data.in_progress_workout) {
        return 'Finish or resume the current workout';
    }
    return null;
};

const finishInProgress = async () => {
    const workout = props.data.in_progress_workout;
    if (!workout) {
        return;
    }

    const parkedCount = workout.parked_incomplete_count ?? 0;
    if (parkedCount > 0) {
        const doParked = await confirmDialog({
            title: parkedCount === 1 ? 'You left 1 group for later — do them now?' : `You left ${parkedCount} groups for later — do them now?`,
            confirmLabel: 'Yes',
            cancelLabel: 'No thanks',
        });
        if (doParked) {
            try {
                await clearParkedBlocks(workout.id, { mutating: workoutMutating });
            } catch {
                return;
            }
            router.visit(route('workouts.play', workout.id));
            return;
        }

        try {
            await clearParkedBlocks(workout.id, { mutating: workoutMutating });
        } catch {
            return;
        }
    }

    await finishWorkout(workout.id, {
        mutating: workoutMutating,
        confirm: async () =>
            confirmDialog({
                title: 'Finish this workout now?',
                description: 'Incomplete sets stay incomplete.',
                confirmLabel: 'Finish',
            }),
    });
};

const abandonInProgress = async () => {
    const workout = props.data.in_progress_workout;
    if (!workout) {
        return;
    }
    await abandonWorkout(workout.id, {
        mutating: workoutMutating,
        confirm: async () =>
            confirmDialog({
                title: 'Abandon this workout?',
                description: 'Logged sets are kept but it will not count as finished.',
                confirmLabel: 'Abandon',
                variant: 'destructive',
            }),
    });
};

const duplicateRoutine = (routine: Routine) => {
    duplicateRoutineMutation(routine.slug, { mutating: routineMutating });
};

const deleteRoutine = async (routine: Routine) => {
    await deleteRoutineMutation(routine.slug, routine.name, routineMutating);
};

const formatFinishedAt = formatDate;
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl bg-background p-4 text-foreground">
            <div v-if="formErrors.length" class="rounded-xl border border-destructive/40 bg-destructive/10 px-4 py-3 text-sm text-destructive">
                <p v-for="(error, index) in formErrors" :key="index">{{ error }}</p>
            </div>

            <div
                v-if="data.in_progress_workout"
                class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-primary/40 bg-card px-4 py-3"
            >
                <div>
                    <p class="text-xs tracking-wide text-primary uppercase">In progress</p>
                    <p class="text-lg font-semibold">{{ data.in_progress_workout.routine_name }}</p>
                    <p class="font-mono text-xs text-muted-foreground">{{ data.in_progress_workout.mode }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button size="pill" as-child>
                        <Link :href="route('workouts.play', data.in_progress_workout.id)">Resume</Link>
                    </Button>
                    <Button type="button" variant="outline" size="pill" :disabled="workoutMutating" @click="finishInProgress">Finish</Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="pill"
                        class="border-destructive/40 text-destructive"
                        :disabled="workoutMutating"
                        @click="abandonInProgress"
                    >
                        Abandon
                    </Button>
                </div>
            </div>

            <div v-if="data.recent_finished_workouts.length" class="space-y-3">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-xl font-semibold">Recent</h2>
                    <Link :href="route('history.index')" class="text-sm text-primary hover:underline">All history</Link>
                </div>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <Link
                        v-for="workout in data.recent_finished_workouts"
                        :key="workout.id"
                        :href="route('history.show', workout.id)"
                        class="rounded-xl border border-border bg-card px-4 py-3 transition-colors hover:border-primary/40"
                    >
                        <p class="font-medium">{{ workout.routine_name }}</p>
                        <p class="mt-1 font-mono text-xs text-muted-foreground">{{ workout.mode }} · {{ formatFinishedAt(workout.finished_at) }}</p>
                    </Link>
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-xl font-semibold">My Routines</h2>
                <Button size="pill" as-child>
                    <Link :href="route('routines.create')">Create</Link>
                </Button>
            </div>

            <div class="grid auto-rows-min gap-3 md:grid-cols-3">
                <RoutineCard
                    v-for="routine in props.data.routines"
                    :key="routine.id"
                    :routine="routine"
                    :can-start="canStart(routine)"
                    :start-blocked-reason="startBlockedReason(routine)"
                    :mutating="cardBusy"
                    @start="(mode) => startWorkout(routine.slug, mode)"
                    @duplicate="duplicateRoutine(routine)"
                    @delete="deleteRoutine(routine)"
                />
            </div>
            <p v-if="!props.data.routines.length" class="text-sm text-muted-foreground">
                No routines yet.
                <Link :href="route('tutorial')" class="font-medium text-primary underline-offset-2 hover:underline">Read the tutorial</Link>, then tap
                Create to start one.
            </p>
        </div>
    </AppLayout>
</template>
