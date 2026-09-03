<script setup lang="ts">
import ExercisePicker from '@/routines/components/ExercisePicker.vue';
import { useRoutineEditor } from '@/routines/composables/useRoutineEditor';
import { ChevronDown } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    deloadExerciseId: number | null;
    deloadWorkingWeightKg: number | null;
    workingWeightKg: number;
    variant: 'desktop' | 'mobile';
}>();

const emit = defineEmits<{
    'update:deloadExerciseId': [id: number | null];
    'update:deloadWorkingWeightKg': [kg: number | null];
}>();

const { exerciseName } = useRoutineEditor();

const expanded = ref(props.deloadExerciseId !== null);

watch(
    () => props.deloadExerciseId,
    (id) => {
        if (id !== null) {
            expanded.value = true;
        }
    },
);

const summary = computed(() => {
    if (props.deloadExerciseId === null) {
        return 'None';
    }

    const name = exerciseName(props.deloadExerciseId);
    const kg = props.deloadWorkingWeightKg;

    return kg == null ? name : `${name} · ${kg} kg`;
});

const onDeloadExercise = (id: number | null) => {
    emit('update:deloadExerciseId', id);
    if (id === null) {
        emit('update:deloadWorkingWeightKg', null);
        expanded.value = false;
        return;
    }
    if (props.deloadWorkingWeightKg === null) {
        emit('update:deloadWorkingWeightKg', props.workingWeightKg);
    }
};

const onClear = () => {
    emit('update:deloadExerciseId', null);
    emit('update:deloadWorkingWeightKg', null);
    expanded.value = false;
};

const onWeightInput = (event: Event) => {
    const value = (event.target as HTMLInputElement).value;
    emit('update:deloadWorkingWeightKg', value ? Number(value) : null);
};
</script>

<template>
    <div data-deload-alternate :class="variant === 'mobile' ? 'mt-3 border-t border-border pt-3' : 'mt-1'">
        <button
            type="button"
            class="flex w-full items-center justify-between gap-2 text-left"
            :aria-expanded="expanded"
            @click="expanded = !expanded"
        >
            <span class="min-w-0">
                <span class="block text-muted-foreground uppercase" :class="variant === 'mobile' ? 'text-xs' : 'font-mono text-[10px]'">
                    Deload alternate
                </span>
                <span v-if="!expanded" class="block truncate font-mono text-foreground" :class="variant === 'mobile' ? 'text-sm' : 'text-xs'">
                    {{ summary }}
                </span>
            </span>
            <ChevronDown
                class="shrink-0 text-muted-foreground transition-transform"
                :class="[variant === 'mobile' ? 'size-4' : 'size-3.5', expanded ? 'rotate-180' : '']"
            />
        </button>

        <div v-if="expanded" :class="variant === 'mobile' ? 'mt-3 space-y-2' : 'mt-1 flex min-w-0 flex-wrap items-center gap-1'">
            <div v-if="variant === 'mobile'" class="flex items-center justify-end">
                <button v-if="deloadExerciseId !== null" type="button" class="text-xs text-muted-foreground" @click="onClear">Clear</button>
            </div>
            <ExercisePicker :model-value="deloadExerciseId" :variant="variant" @update:model-value="onDeloadExercise" />
            <label v-if="variant === 'mobile'" class="block">
                <span class="text-xs text-muted-foreground">Deload kg</span>
                <input
                    :value="deloadWorkingWeightKg ?? ''"
                    type="number"
                    step="0.01"
                    min="0"
                    inputmode="decimal"
                    :disabled="deloadExerciseId === null"
                    class="mt-1 w-full rounded-xl border border-border bg-background px-3 py-2 text-center text-xl font-semibold tabular-nums outline-none focus:border-primary disabled:opacity-50"
                    @input="onWeightInput"
                />
            </label>
            <template v-else>
                <input
                    :value="deloadWorkingWeightKg ?? ''"
                    type="number"
                    step="0.01"
                    min="0"
                    inputmode="decimal"
                    placeholder="kg"
                    title="Deload alternate working weight"
                    class="w-16 rounded border border-border bg-card px-1.5 py-1 font-mono text-xs tabular-nums"
                    :disabled="deloadExerciseId === null"
                    @input="onWeightInput"
                />
                <button
                    v-if="deloadExerciseId !== null"
                    type="button"
                    class="shrink-0 text-[10px] text-muted-foreground hover:text-foreground"
                    title="Clear deload alternate"
                    @click="onClear"
                >
                    Clear
                </button>
            </template>
        </div>
    </div>
</template>
