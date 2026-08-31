<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { formatRest } from '@/routines/lib/formatRest';
import type { Block, ExerciseProfileOption } from '@/routines/types';
import { useHttp } from '@inertiajs/vue3';
import { computed } from 'vue';

const open = defineModel<boolean>('open', { required: true });

const props = defineProps<{
    block: Block;
    exerciseIndex: number;
}>();

const emit = defineEmits<{
    saved: [profile: ExerciseProfileOption];
}>();

const form = useHttp({
    name: '',
    target_reps: 6,
    floor_override: null as number | null,
    working_rest_seconds: 120,
    warm_up_steps: [] as { mode?: 'percent' | 'bar'; percent?: number; reps: number }[],
});

const exercise = computed(() => props.block.exercises[props.exerciseIndex] ?? props.block.exercises[0]);
const floorOverride = computed(() => (exercise.value?.floor_is_derived === true ? null : (exercise.value?.achievement_floor ?? null)));
const warmUpSteps = computed(() =>
    props.block.warm_up.steps.map((step) => ({
        mode: step.mode ?? 'percent',
        percent: step.mode === 'bar' ? undefined : step.percent,
        reps: step.reps,
    })),
);
const previewTarget = computed(() => exercise.value?.prescribed_reps ?? 0);
const previewFloor = computed(() => Math.max(1, previewTarget.value - 2));

const prepare = () => {
    form.name = '';
    form.target_reps = previewTarget.value;
    form.floor_override = floorOverride.value;
    form.working_rest_seconds = props.block.working.rest_seconds;
    form.warm_up_steps = warmUpSteps.value;
    form.clearErrors();
};

const close = () => {
    if (form.processing) {
        return;
    }

    open.value = false;
    form.clearErrors();
};

const submit = () => {
    form.post(route('exercise-profiles.store'), {
        onSuccess: (data) => {
            emit('saved', data as ExerciseProfileOption);
            open.value = false;
            form.clearErrors();
        },
    });
};
</script>

<template>
    <Dialog v-model:open="open" @update:open="(value) => value && prepare()">
        <DialogContent class="max-h-[90dvh] overflow-y-auto sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>Save as profile</DialogTitle>
                <DialogDescription
                    >Give this block’s current Profile Details a reusable name. The current block will select the new profile.</DialogDescription
                >
            </DialogHeader>

            <form class="space-y-5" @submit.prevent="submit">
                <label class="flex flex-col gap-1 text-sm text-muted-foreground">
                    Name
                    <input
                        v-model="form.name"
                        class="rounded border border-border bg-background px-3 py-2 text-foreground"
                        required
                        maxlength="255"
                        autofocus
                    />
                    <InputError :message="form.errors.name" />
                </label>

                <div class="rounded-xl border border-border bg-card/50 p-3 text-sm text-muted-foreground">
                    <p>Target {{ previewTarget }} · Floor {{ form.floor_override ?? previewFloor }}</p>
                    <p class="mt-1">Working rest: {{ formatRest(props.block.working.rest_seconds) }}</p>
                    <p class="mt-1">
                        Warm-up:
                        {{ form.warm_up_steps.length ? form.warm_up_steps.map((step) => `${step.percent}%×${step.reps}`).join(', ') : 'None' }}
                    </p>
                </div>

                <DialogFooter>
                    <Button type="button" variant="outline" @click="close">Cancel</Button>
                    <Button type="submit" :disabled="form.processing">{{ form.processing ? 'Saving…' : 'Save profile' }}</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
