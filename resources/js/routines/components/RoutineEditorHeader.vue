<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import ExerciseProfilePicker from '@/routines/components/ExerciseProfilePicker.vue';
import RoutineEditorErrors from '@/routines/components/RoutineEditorErrors.vue';
import { useRoutineEditor } from '@/routines/composables/useRoutineEditor';
import { Link } from '@inertiajs/vue3';

const { form, profileOptions, setRoutineProfile, save, duplicateRoutine, deleteRoutine, mutating } = useRoutineEditor();
</script>

<template>
    <header class="border-b border-border px-4 py-4 md:px-6">
        <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
            <div class="min-w-0 flex-1">
                <p class="text-xs tracking-[0.2em] text-muted-foreground uppercase">Routine</p>
                <input
                    v-model="form.name"
                    class="mt-1 w-full border-0 border-b border-border bg-transparent text-2xl font-bold outline-none focus:border-primary"
                    required
                />
                <InputError :message="form.errors.name" />
            </div>
            <div class="w-full md:max-w-xs">
                <ExerciseProfilePicker
                    :model-value="form.default_exercise_profile_id ?? null"
                    :profiles="profileOptions"
                    label="Routine profile"
                    @update:model-value="setRoutineProfile($event)"
                />
                <p class="mt-1 text-xs text-muted-foreground">Used for new blocks; existing blocks stay unchanged.</p>
                <InputError :message="form.errors.default_exercise_profile_id" />
            </div>
            <div class="flex flex-wrap gap-3 font-mono text-sm">
                <Link
                    :href="route('dashboard')"
                    class="rounded-full border border-border px-4 py-2 text-sm font-medium text-muted-foreground transition-colors hover:text-foreground"
                >
                    Cancel
                </Link>
                <button
                    type="button"
                    class="rounded-full border border-border px-4 py-2 text-sm font-medium text-muted-foreground transition-colors hover:text-foreground disabled:opacity-50"
                    :disabled="mutating || form.processing"
                    @click="duplicateRoutine"
                >
                    Duplicate
                </button>
                <button
                    type="button"
                    class="rounded-full border border-destructive/50 px-4 py-2 text-sm font-medium text-destructive transition-colors hover:bg-destructive/10 disabled:opacity-50"
                    :disabled="mutating || form.processing"
                    @click="deleteRoutine"
                >
                    Delete
                </button>
                <button
                    type="button"
                    class="rounded-full bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground disabled:opacity-50"
                    :disabled="form.processing || mutating"
                    @click="save"
                >
                    Save
                </button>
            </div>
        </div>
        <RoutineEditorErrors class="mt-3" />
        <p v-if="form.recentlySuccessful" class="mt-2 text-sm text-primary">Saved.</p>
    </header>
</template>
