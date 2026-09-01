<script setup lang="ts">
import BrandName from '@/components/BrandName.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { formatRest } from '@/routines/lib/formatRest';
import type { ExerciseProfileOption, ExerciseProfilePage, ExerciseProfileWarmUpStep } from '@/settings/types';
import { confirmDialog } from '@/shared/lib/confirmDialog';
import { formatProfileWarmUpSteps, setEditorWarmUpMode, type WarmUpWeightMode } from '@/shared/warmUpStep';
import { useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

type ProfileFormData = {
    name: string;
    target_reps: number;
    floor_override: number | null;
    working_rest_seconds: number;
    warm_up_steps: ExerciseProfileWarmUpStep[];
};

const props = defineProps<{
    exerciseProfiles: ExerciseProfilePage;
}>();

const activeProfiles = computed(() => props.exerciseProfiles.profiles);
const archivedProfiles = computed(() => props.exerciseProfiles.archived_profiles);
const page = usePage();
const profileSyncId = computed(() => {
    const flash = page.props.flash as { profile_sync_id?: number | null } | undefined;

    return flash?.profile_sync_id ?? null;
});
const showArchived = ref(false);
const dialogOpen = ref(false);
const editingId = ref<number | null>(null);

const form = useForm<ProfileFormData>({
    name: '',
    target_reps: 6,
    floor_override: null,
    working_rest_seconds: 120,
    warm_up_steps: [],
});
const actionForm = useForm<{ profile?: string }>({ profile: undefined });

const editingProfile = computed(() => {
    if (editingId.value === null) {
        return null;
    }

    return activeProfiles.value.find((profile) => profile.id === editingId.value) ?? null;
});

const derivedFloor = computed(() => Math.max(1, Number(form.target_reps) - 2));
const displayedFloor = computed(() => form.floor_override ?? derivedFloor.value);

const profileSummary = (profile: ExerciseProfileOption): string => `Target ${profile.target_reps} · Floor ${profile.floor}`;

const copyIntoForm = (profile?: ExerciseProfileOption) => {
    editingId.value = null;
    form.clearErrors();
    form.name = profile ? `${profile.name} copy` : '';
    form.target_reps = profile?.target_reps ?? 6;
    form.floor_override = profile?.floor_override ?? null;
    form.working_rest_seconds = profile?.working_rest_seconds ?? 120;
    form.warm_up_steps = profile?.warm_up_steps.map((step) => ({ ...step })) ?? [];
    dialogOpen.value = true;
};

const editProfile = (profile: ExerciseProfileOption) => {
    editingId.value = profile.id;
    form.clearErrors();
    form.name = profile.name;
    form.target_reps = profile.target_reps;
    form.floor_override = profile.floor_override;
    form.working_rest_seconds = profile.working_rest_seconds;
    form.warm_up_steps = profile.warm_up_steps.map((step) => ({ ...step }));
    dialogOpen.value = true;
};

const closeDialog = (force = false) => {
    if (form.processing && !force) {
        return;
    }

    dialogOpen.value = false;
    editingId.value = null;
    form.clearErrors();
};

const setFloorOverride = (raw: string) => {
    form.floor_override = raw === '' ? null : Number(raw);
};

const addWarmUpStep = () => {
    form.warm_up_steps.push({ mode: 'percent', percent: 50, reps: 5 });
};

const setWarmUpMode = (step: ExerciseProfileWarmUpStep, mode: WarmUpWeightMode) => {
    setEditorWarmUpMode(step, mode);
};

const removeWarmUpStep = (index: number) => {
    form.warm_up_steps.splice(index, 1);
};

const submit = () => {
    if (editingId.value === null) {
        form.post(route('exercise-profiles.store'), {
            onSuccess: () => closeDialog(true),
        });
        return;
    }

    form.put(route('exercise-profiles.update', editingId.value), {
        onSuccess: () => closeDialog(true),
    });
};

const setDefault = (profile: ExerciseProfileOption) => {
    if (actionForm.processing || profile.is_default) {
        return;
    }

    actionForm.post(route('exercise-profiles.default', profile.id));
};

const archive = async (profile: ExerciseProfileOption) => {
    if (actionForm.processing) {
        return;
    }

    const confirmed = await confirmDialog({
        title: `Archive ${profile.name}?`,
        description: 'Existing routines may continue using this profile, but it will no longer be available for new selections.',
        confirmLabel: 'Archive',
    });
    if (!confirmed) {
        return;
    }

    actionForm.post(route('exercise-profiles.archive', profile.id));
};

const sync = async (profile: ExerciseProfileOption) => {
    if (actionForm.processing) {
        return;
    }

    const confirmed = await confirmDialog({
        title: `Update routines using ${profile.name}?`,
        description: 'This updates exercises and blocks still linked to this profile. Anything you have edited manually stays as is.',
        confirmLabel: 'Update routines',
    });
    if (!confirmed) {
        return;
    }

    actionForm.post(route('exercise-profiles.sync', profile.id));
};

const restore = (profile: ExerciseProfileOption) => {
    if (actionForm.processing) {
        return;
    }

    actionForm.post(route('exercise-profiles.restore', profile.id));
};

const remove = async (profile: ExerciseProfileOption) => {
    if (actionForm.processing) {
        return;
    }

    const confirmed = await confirmDialog({
        title: `Delete ${profile.name}?`,
        description: 'This permanently deletes the profile. Change any routines using it in the editor first.',
        confirmLabel: 'Delete',
        variant: 'destructive',
    });
    if (!confirmed) {
        return;
    }

    actionForm.delete(route('exercise-profiles.delete', profile.id));
};

watch(profileSyncId, (profileId) => {
    if (profileId === null) {
        return;
    }

    const profile = activeProfiles.value.find((item) => item.id === profileId);
    if (profile && profile.stale_assignment_count > 0) {
        void sync(profile);
    }
});
</script>

<template>
    <section class="space-y-6 border-t border-border pt-10">
        <HeadingSmall title="Exercise profiles" description="Choose a reusable profile for target reps, floor, working rest, and warm-up steps." />

        <p class="text-sm text-muted-foreground">
            Your default is preselected when you create a routine. Profiles are copied into routines, so changing one does not rewrite existing
            workouts.
        </p>

        <div class="space-y-3">
            <div
                v-for="profile in activeProfiles"
                :key="profile.id"
                class="rounded-xl border border-border bg-card p-4"
                :class="profile.is_default ? 'border-primary/60 bg-primary/5' : ''"
            >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="font-semibold text-foreground">
                                <template v-if="profile.kind === 'preset'"> <BrandName class="mr-1" />{{ profile.name }} </template>
                                <template v-else>{{ profile.display_name }}</template>
                            </h3>
                            <span v-if="profile.is_default" class="rounded-full bg-primary/15 px-2 py-0.5 text-xs text-primary">Default</span>
                        </div>
                        <p class="mt-1 text-sm text-muted-foreground">{{ profileSummary(profile) }}</p>
                        <p v-if="profile.assigned_routines.length" class="mt-1 text-xs text-muted-foreground">
                            Used in
                            <!-- prettier-ignore -->
                            <template v-for="(routine, index) in profile.assigned_routines" :key="routine.slug"><TextLink :href="route('routines.edit', routine.slug)">{{ routine.name }}</TextLink><template v-if="index < profile.assigned_routines.length - 1">, </template></template>
                            . Check Routine profile and each exercise's profile selector before deleting or archiving.
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Button v-if="!profile.is_default" type="button" variant="outline" size="sm" @click="setDefault(profile)">Set default</Button>
                        <Button v-if="profile.kind === 'custom'" type="button" variant="outline" size="sm" @click="editProfile(profile)">Edit</Button>
                        <Button type="button" variant="outline" size="sm" @click="copyIntoForm(profile)">Duplicate</Button>
                    </div>
                </div>

                <details class="mt-3 text-sm">
                    <summary class="cursor-pointer text-muted-foreground hover:text-foreground">Profile Details</summary>
                    <div class="mt-3 space-y-1 text-muted-foreground">
                        <p>Working rest: {{ formatRest(profile.working_rest_seconds) }}</p>
                        <p>
                            Warm-up:
                            {{ profile.warm_up_steps.length ? formatProfileWarmUpSteps(profile.warm_up_steps) : 'None' }}
                        </p>
                    </div>
                </details>

                <div v-if="profile.kind === 'custom'" class="mt-4 flex flex-wrap gap-2 border-t border-border pt-3">
                    <Button
                        v-if="!profile.is_default && !profile.assigned_routines.length"
                        type="button"
                        variant="ghost"
                        size="sm"
                        @click="archive(profile)"
                    >
                        Archive
                    </Button>
                    <Button v-if="profile.stale_assignment_count > 0" type="button" variant="ghost" size="sm" @click="sync(profile)">
                        Update routines
                    </Button>
                    <Button
                        v-if="!profile.assigned_routines.length"
                        type="button"
                        variant="ghost"
                        size="sm"
                        class="text-destructive hover:text-destructive"
                        @click="remove(profile)"
                    >
                        Delete
                    </Button>
                </div>
            </div>

            <p v-if="!activeProfiles.length" class="rounded-xl border border-dashed border-border px-4 py-5 text-sm text-muted-foreground">
                No active profiles yet. Create one below or choose an <BrandName class="text-sm" /> preset when you create your first routine.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <Button type="button" @click="copyIntoForm()">Create profile</Button>
            <Button v-if="archivedProfiles.length" type="button" variant="ghost" @click="showArchived = !showArchived">
                {{ showArchived ? 'Hide archived' : `Show archived (${archivedProfiles.length})` }}
            </Button>
        </div>

        <div v-if="showArchived && archivedProfiles.length" class="space-y-3 rounded-xl border border-border p-4">
            <h3 class="text-sm font-semibold text-foreground">Archived profiles</h3>
            <div v-for="profile in archivedProfiles" :key="profile.id" class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-sm font-medium text-foreground">{{ profile.name }}</p>
                    <p class="text-xs text-muted-foreground">{{ profileSummary(profile) }}</p>
                </div>
                <Button type="button" variant="outline" size="sm" @click="restore(profile)">Restore</Button>
            </div>
        </div>

        <InputError :message="actionForm.errors.profile" />
    </section>

    <Dialog v-model:open="dialogOpen">
        <DialogContent class="max-h-[90dvh] overflow-y-auto sm:max-w-xl">
            <DialogHeader>
                <DialogTitle>{{ editingProfile ? `Edit ${editingProfile.name}` : 'Create exercise profile' }}</DialogTitle>
                <DialogDescription>
                    Target is the top of your rep range. Floor is usually two reps lower, or you can set it yourself.
                </DialogDescription>
            </DialogHeader>

            <form class="space-y-5" @submit.prevent="submit">
                <label class="flex flex-col gap-1 text-sm text-muted-foreground">
                    Name
                    <input
                        v-model="form.name"
                        class="rounded border border-border bg-background px-3 py-2 text-foreground"
                        required
                        maxlength="255"
                    />
                    <InputError :message="form.errors.name" />
                </label>

                <div class="grid grid-cols-2 gap-3">
                    <label class="flex flex-col gap-1 text-sm text-muted-foreground">
                        Target reps
                        <input
                            v-model.number="form.target_reps"
                            type="number"
                            min="1"
                            max="100"
                            class="rounded border border-border bg-background px-3 py-2 font-mono text-foreground"
                            required
                        />
                        <InputError :message="form.errors.target_reps" />
                    </label>
                    <label class="flex flex-col gap-1 text-sm text-muted-foreground">
                        Floor
                        <input
                            :value="form.floor_override ?? displayedFloor"
                            type="number"
                            min="1"
                            max="100"
                            class="rounded border border-border bg-background px-3 py-2 font-mono text-foreground"
                            @input="setFloorOverride(($event.target as HTMLInputElement).value)"
                        />
                        <span class="text-xs text-muted-foreground/80">
                            {{ form.floor_override === null ? 'From target' : 'Set manually' }}
                        </span>
                        <InputError :message="form.errors.floor_override" />
                    </label>
                </div>

                <label class="flex flex-col gap-1 text-sm text-muted-foreground">
                    Working rest (seconds)
                    <input
                        v-model.number="form.working_rest_seconds"
                        type="number"
                        min="0"
                        max="3600"
                        step="15"
                        class="rounded border border-border bg-background px-3 py-2 font-mono text-foreground"
                        required
                    />
                    <InputError :message="form.errors.working_rest_seconds" />
                </label>

                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-medium text-foreground">Warm-up ladder</p>
                        <button type="button" class="text-xs text-primary" @click="addWarmUpStep">+ Step</button>
                    </div>
                    <div v-for="(step, index) in form.warm_up_steps" :key="index" class="flex items-end gap-2">
                        <label class="flex flex-col gap-1 text-xs text-muted-foreground">
                            Mode
                            <select
                                :value="step.mode ?? 'percent'"
                                class="rounded border border-border bg-background px-2 py-1.5 text-foreground"
                                @change="setWarmUpMode(step, ($event.target as HTMLSelectElement).value as WarmUpWeightMode)"
                            >
                                <option value="percent">Percent</option>
                                <option value="bar">Empty bar</option>
                                <option value="fixed">Fixed weight</option>
                            </select>
                        </label>
                        <label v-if="(step.mode ?? 'percent') === 'percent'" class="flex flex-1 flex-col gap-1 text-xs text-muted-foreground">
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
                        <label v-else-if="(step.mode ?? 'percent') === 'fixed'" class="flex flex-1 flex-col gap-1 text-xs text-muted-foreground">
                            kg
                            <input
                                v-model.number="step.weight_kg"
                                type="number"
                                min="0.25"
                                max="1000"
                                step="0.25"
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
                        <button type="button" class="pb-2 text-xs text-muted-foreground hover:text-destructive" @click="removeWarmUpStep(index)">
                            Remove
                        </button>
                    </div>
                    <p v-if="!form.warm_up_steps.length" class="text-xs text-muted-foreground">No warm-up steps.</p>
                    <InputError :message="form.errors.warm_up_steps" />
                </div>

                <DialogFooter>
                    <Button type="button" variant="outline" @click="closeDialog">Cancel</Button>
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Saving…' : 'Save profile' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
