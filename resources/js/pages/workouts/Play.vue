<script setup lang="ts">
/**
 * Workout player — chrome-minimal full-bleed stage.
 */
import ConfirmDialogHost from '@/shared/components/ConfirmDialogHost.vue';
import CompleteStage from '@/workouts/components/CompleteStage.vue';
import PlayerHeader from '@/workouts/components/PlayerHeader.vue';
import RestStage from '@/workouts/components/RestStage.vue';
import SetStage from '@/workouts/components/SetStage.vue';
import SetupStage from '@/workouts/components/SetupStage.vue';
import { createWorkoutPlayer, workoutPlayerKey, type PlayWorkoutProps } from '@/workouts/composables/useWorkoutPlayer';
import { preparePlayerInteraction } from '@/workouts/lib/playerInteraction';
import { Head } from '@inertiajs/vue3';
import { provide, ref } from 'vue';

const props = defineProps<PlayWorkoutProps>();

const player = createWorkoutPlayer(props);
provide(workoutPlayerKey, player);

const { workout, focus, current, currentBlock, restSecondsLeft } = player;

const primedInteraction = ref(false);

const primeOnFirstInteraction = () => {
    if (primedInteraction.value) {
        return;
    }
    primedInteraction.value = true;
    preparePlayerInteraction();
};
</script>

<template>
    <div
        class="safe-pt safe-pb safe-px mx-auto flex min-h-dvh w-full max-w-lg flex-col overflow-x-hidden overscroll-none bg-background text-foreground"
        @pointerdown="primeOnFirstInteraction"
    >
        <Head :title="`Play · ${workout.routine_name}`" />

        <PlayerHeader v-if="focus.kind !== 'done'" />

        <RestStage v-if="restSecondsLeft > 0" />
        <SetupStage v-else-if="focus.kind === 'setup' && currentBlock" />
        <CompleteStage v-else-if="focus.kind === 'done'" />
        <SetStage v-else-if="current" />
        <ConfirmDialogHost />
    </div>
</template>
