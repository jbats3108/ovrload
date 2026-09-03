<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import DeloadSettings from '@/routines/components/DeloadSettings.vue';
import EditorDisclosure from '@/routines/components/EditorDisclosure.vue';
import ExerciseProfilePicker from '@/routines/components/ExerciseProfilePicker.vue';
import { useRoutineEditor } from '@/routines/composables/useRoutineEditor';
import { formatDeloadSummary } from '@/routines/lib/deload';
import { computed, ref } from 'vue';

const { form, profileOptions, setRoutineProfile } = useRoutineEditor();

const expanded = ref(false);

const routineProfileModel = computed({
    get: () => form.default_exercise_profile_id ?? null,
    set: (profileId: number | null) => {
        void setRoutineProfile(profileId);
    },
});

const summary = computed(() => {
    const profile = profileOptions.value.find((option) => option.id === form.default_exercise_profile_id);
    const profileLabel = profile?.display_name ?? 'No routine profile';
    const deload = formatDeloadSummary(form.deload_weight_factor, form.deload_reps_factor, form.deload_every_n);

    return `${profileLabel} · ${deload}`;
});
</script>

<template>
    <section data-desktop-routine-settings class="border-b border-border bg-card/40 px-4 py-3">
        <EditorDisclosure flush :expanded="expanded" label="Routine settings" :summary="summary" @toggle="expanded = !expanded">
            <div class="max-w-md space-y-4">
                <div>
                    <ExerciseProfilePicker v-model="routineProfileModel" :profiles="profileOptions" label="Routine profile" />
                    <p class="mt-1 text-xs text-muted-foreground">Used for new blocks; existing blocks stay unchanged.</p>
                    <InputError :message="form.errors.default_exercise_profile_id" />
                </div>
                <div class="rounded-xl border border-border bg-background/50 p-3">
                    <DeloadSettings variant="mobile" flush />
                </div>
            </div>
        </EditorDisclosure>
    </section>
</template>
