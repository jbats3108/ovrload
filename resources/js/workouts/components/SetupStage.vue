<script setup lang="ts">
import UpcomingCard from '@/workouts/components/UpcomingCard.vue';
import { useWorkoutPlayer } from '@/workouts/composables/useWorkoutPlayer';

const { setupHint, upcoming, setupSupersetPair, workout, acknowledgeSetup } = useWorkoutPlayer();
</script>

<template>
    <div class="flex min-h-0 flex-1 flex-col items-center justify-center gap-6 overflow-y-auto overscroll-contain px-6 text-center">
        <p class="text-sm tracking-widest text-muted-foreground uppercase">Setup</p>
        <p class="text-2xl font-semibold">Change equipment, then continue</p>
        <p class="text-lg font-medium text-foreground">{{ setupHint }}</p>
        <UpcomingCard v-if="upcoming" :upcoming="upcoming" :pair="setupSupersetPair" :weight-unit="workout.weight_unit" />
        <button type="button" class="rounded-full bg-primary px-8 py-3 text-sm font-semibold text-primary-foreground" @click="acknowledgeSetup">
            Setup done
        </button>
    </div>
</template>
