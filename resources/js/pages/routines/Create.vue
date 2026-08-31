<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import AppLayout from '@/layouts/AppLayout.vue';
import ExerciseProfilePicker from '@/routines/components/ExerciseProfilePicker.vue';
import type { ExerciseProfileOption, ExerciseProfileWarmUpStep } from '@/settings/types';
import { Head, Link, useForm, useHttp } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps<{
    exercise_profiles: ExerciseProfileOption[];
    default_exercise_profile_id: number | null;
}>();

const form = useForm({
    name: '',
    default_exercise_profile_id: props.default_exercise_profile_id,
});

const profiles = ref([...props.exercise_profiles]);
const createProfileOpen = ref(false);
const makeDefault = ref(false);
const createProfileForm = useHttp({
    name: '',
    target_reps: 6,
    floor_override: null as number | null,
    working_rest_seconds: 120,
    warm_up_steps: [] as ExerciseProfileWarmUpStep[],
});
const defaultProfileForm = useHttp({});
const defaultProfile = computed(() => profiles.value.find((profile) => profile.id === props.default_exercise_profile_id) ?? null);
const showMakeDefault = computed(() => defaultProfile.value === null || defaultProfile.value.kind === 'preset');
const derivedFloor = computed(() => Math.max(1, Number(createProfileForm.target_reps) - 2));
const displayedFloor = computed(() => createProfileForm.floor_override ?? derivedFloor.value);

const setFloorOverride = (raw: string) => {
    createProfileForm.floor_override = raw === '' ? null : Number(raw);
};

const addWarmUpStep = () => {
    createProfileForm.warm_up_steps.push({ percent: 50, reps: 5 });
};

const removeWarmUpStep = (index: number) => {
    createProfileForm.warm_up_steps.splice(index, 1);
};

const openCreateProfile = () => {
    createProfileForm.reset();
    createProfileForm.clearErrors();
    createProfileForm.name = '';
    createProfileForm.target_reps = 6;
    createProfileForm.floor_override = null;
    createProfileForm.working_rest_seconds = 120;
    createProfileForm.warm_up_steps = [];
    makeDefault.value = showMakeDefault.value;
    createProfileOpen.value = true;
};

const closeCreateProfile = () => {
    if (createProfileForm.processing) {
        return;
    }

    createProfileOpen.value = false;
    createProfileForm.clearErrors();
};

const createProfile = () => {
    createProfileForm.post(route('exercise-profiles.store'), {
        onSuccess: (data) => {
            const created = data as ExerciseProfileOption;
            profiles.value.push(created);
            form.default_exercise_profile_id = created.id;
            createProfileOpen.value = false;
            createProfileForm.clearErrors();

            if (makeDefault.value && showMakeDefault.value) {
                defaultProfileForm.post(route('exercise-profiles.default', created.id));
            }
        },
    });
};

const submit = () => {
    form.post(route('routines.store'));
};
</script>

<template>
    <AppLayout
        :breadcrumbs="[
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'New routine', href: '#' },
        ]"
    >
        <Head title="New routine" />

        <div class="mx-auto flex w-full max-w-lg flex-1 flex-col px-4 py-10 text-foreground">
            <p class="text-xs tracking-[0.2em] text-muted-foreground uppercase">Routine</p>
            <h1 class="mt-2 text-2xl font-bold">Name your routine</h1>
            <p class="mt-2 text-sm text-muted-foreground">You can add exercises on the next screen.</p>

            <form class="mt-8 flex flex-col gap-6" @submit.prevent="submit">
                <label class="flex flex-col gap-2 text-sm text-muted-foreground">
                    Name
                    <input
                        v-model="form.name"
                        type="text"
                        class="rounded-xl border border-border bg-card px-4 py-3 text-lg text-foreground outline-none focus:border-primary"
                        placeholder="e.g. Push day"
                        required
                        autofocus
                    />
                    <InputError :message="form.errors.name" />
                </label>

                <ExerciseProfilePicker v-model="form.default_exercise_profile_id" :profiles="profiles" label="Training profile" />
                <p class="-mt-3 text-xs text-muted-foreground">
                    Choose the Profile Details for new blocks in this routine. You can change individual exercises later.
                </p>
                <InputError :message="form.errors.default_exercise_profile_id" />
                <button type="button" class="-mt-3 self-start text-sm text-primary underline-offset-2 hover:underline" @click="openCreateProfile">
                    Create a custom profile
                </button>

                <div class="flex gap-3">
                    <Link
                        :href="route('dashboard')"
                        class="rounded-full border border-border px-5 py-3 text-sm text-muted-foreground hover:text-foreground"
                    >
                        Cancel
                    </Link>
                    <button
                        type="submit"
                        class="rounded-full bg-primary px-6 py-3 text-sm font-semibold text-primary-foreground disabled:opacity-50"
                        :disabled="form.processing || !form.name.trim()"
                    >
                        Continue
                    </button>
                </div>
            </form>

            <Dialog v-model:open="createProfileOpen">
                <DialogContent class="max-h-[90dvh] overflow-y-auto sm:max-w-xl">
                    <DialogHeader>
                        <DialogTitle>Create custom profile</DialogTitle>
                        <DialogDescription>
                            This profile will be used for the new routine. Target is the upper end; Floor starts two reps lower.
                        </DialogDescription>
                    </DialogHeader>

                    <form class="space-y-5" @submit.prevent="createProfile">
                        <label class="flex flex-col gap-1 text-sm text-muted-foreground">
                            Name
                            <input
                                v-model="createProfileForm.name"
                                class="rounded border border-border bg-background px-3 py-2 text-foreground"
                                required
                                maxlength="255"
                            />
                            <InputError :message="createProfileForm.errors.name" />
                        </label>

                        <div class="grid grid-cols-2 gap-3">
                            <label class="flex flex-col gap-1 text-sm text-muted-foreground">
                                Target reps
                                <input
                                    v-model.number="createProfileForm.target_reps"
                                    type="number"
                                    min="1"
                                    max="100"
                                    class="rounded border border-border bg-background px-3 py-2 font-mono text-foreground"
                                    required
                                />
                                <InputError :message="createProfileForm.errors.target_reps" />
                            </label>
                            <label class="flex flex-col gap-1 text-sm text-muted-foreground">
                                Floor
                                <input
                                    :value="createProfileForm.floor_override ?? displayedFloor"
                                    type="number"
                                    min="1"
                                    max="100"
                                    class="rounded border border-border bg-background px-3 py-2 font-mono text-foreground"
                                    @input="setFloorOverride(($event.target as HTMLInputElement).value)"
                                />
                                <span class="text-xs text-muted-foreground/80">
                                    {{ createProfileForm.floor_override === null ? 'From target' : 'Set manually' }}
                                </span>
                                <InputError :message="createProfileForm.errors.floor_override" />
                            </label>
                        </div>

                        <label class="flex flex-col gap-1 text-sm text-muted-foreground">
                            Working rest (seconds)
                            <input
                                v-model.number="createProfileForm.working_rest_seconds"
                                type="number"
                                min="0"
                                max="3600"
                                step="15"
                                class="rounded border border-border bg-background px-3 py-2 font-mono text-foreground"
                                required
                            />
                            <InputError :message="createProfileForm.errors.working_rest_seconds" />
                        </label>

                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <p class="text-sm font-medium text-foreground">Warm-up ladder</p>
                                <button type="button" class="text-xs text-primary" @click="addWarmUpStep">+ Step</button>
                            </div>
                            <div v-for="(step, index) in createProfileForm.warm_up_steps" :key="index" class="flex items-end gap-2">
                                <label class="flex flex-1 flex-col gap-1 text-xs text-muted-foreground">
                                    Percent
                                    <input
                                        v-model.number="step.percent"
                                        type="number"
                                        min="1"
                                        max="100"
                                        class="rounded border border-border bg-background px-2 py-1.5 font-mono text-foreground"
                                        required
                                    />
                                </label>
                                <label class="flex flex-1 flex-col gap-1 text-xs text-muted-foreground">
                                    Reps
                                    <input
                                        v-model.number="step.reps"
                                        type="number"
                                        min="1"
                                        max="100"
                                        class="rounded border border-border bg-background px-2 py-1.5 font-mono text-foreground"
                                        required
                                    />
                                </label>
                                <button
                                    type="button"
                                    class="pb-2 text-xs text-muted-foreground hover:text-destructive"
                                    @click="removeWarmUpStep(index)"
                                >
                                    Remove
                                </button>
                            </div>
                            <p v-if="!createProfileForm.warm_up_steps.length" class="text-xs text-muted-foreground">No warm-up steps.</p>
                            <InputError :message="createProfileForm.errors.warm_up_steps" />
                        </div>

                        <label v-if="showMakeDefault" class="flex items-center gap-2 text-sm text-muted-foreground">
                            <input v-model="makeDefault" type="checkbox" />
                            Make this my default for future routines
                        </label>

                        <DialogFooter>
                            <Button type="button" variant="outline" @click="closeCreateProfile">Cancel</Button>
                            <Button type="submit" :disabled="createProfileForm.processing">
                                {{ createProfileForm.processing ? 'Saving…' : 'Create profile' }}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </div>
    </AppLayout>
</template>
