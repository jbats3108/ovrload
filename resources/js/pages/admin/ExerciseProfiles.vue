<script setup lang="ts">
import type { AdminExerciseProfile } from '@/admin/types';
import BrandName from '@/components/BrandName.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import AppLayout from '@/layouts/AppLayout.vue';
import AdminLayout from '@/layouts/admin/Layout.vue';
import { formatRest } from '@/routines/lib/formatRest';
import type { ExerciseProfileWarmUpStep } from '@/settings/types';
import { confirmDialog } from '@/shared/lib/confirmDialog';
import { formatProfileWarmUpSteps, setEditorWarmUpMode, type WarmUpWeightMode } from '@/shared/warmUpStep';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

type PresetFormData = {
    name: string;
    target_reps: number;
    floor_override: number | null;
    working_rest_seconds: number;
    warm_up_steps: ExerciseProfileWarmUpStep[];
};

const props = defineProps<{
    drafts: AdminExerciseProfile[];
    published: AdminExerciseProfile[];
}>();

const dialogOpen = ref(false);
const editingId = ref<number | null>(null);
const form = useForm<PresetFormData>({
    name: '',
    target_reps: 6,
    floor_override: null,
    working_rest_seconds: 120,
    warm_up_steps: [],
});
const actionForm = useForm<{ profile?: string }>({ profile: undefined });

const editingProfile = computed(() => props.drafts.find((profile) => profile.id === editingId.value) ?? null);
const derivedFloor = computed(() => Math.max(1, Number(form.target_reps) - 2));
const displayedFloor = computed(() => form.floor_override ?? derivedFloor.value);

const openCreate = () => {
    editingId.value = null;
    form.clearErrors();
    form.name = '';
    form.target_reps = 6;
    form.floor_override = null;
    form.working_rest_seconds = 120;
    form.warm_up_steps = [];
    dialogOpen.value = true;
};

const openEdit = (profile: AdminExerciseProfile) => {
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
        form.post(route('admin.exercise-profiles.store'), {
            onSuccess: () => closeDialog(true),
        });
        return;
    }

    form.put(route('admin.exercise-profiles.update', editingId.value), {
        onSuccess: () => closeDialog(true),
    });
};

const publish = async (profile: AdminExerciseProfile) => {
    if (actionForm.processing) {
        return;
    }

    const confirmed = await confirmDialog({
        title: `Publish preset "${profile.name}"?`,
        description: `Target ${profile.target_reps} · Floor ${profile.floor} · Rest ${formatRest(profile.working_rest_seconds)} · Warm-up ${
            profile.warm_up_steps.length ? formatProfileWarmUpSteps(profile.warm_up_steps) : 'None'
        }. Publishing is permanent: the preset cannot be edited, archived, or deleted.`,
        confirmLabel: 'Publish preset',
    });
    if (!confirmed) {
        return;
    }

    actionForm.post(route('admin.exercise-profiles.publish', profile.id));
};

const remove = async (profile: AdminExerciseProfile) => {
    if (actionForm.processing) {
        return;
    }

    const confirmed = await confirmDialog({
        title: `Delete draft ${profile.name}?`,
        description: 'This permanently removes the unpublished preset draft.',
        confirmLabel: 'Delete draft',
        variant: 'destructive',
    });
    if (!confirmed) {
        return;
    }

    actionForm.delete(route('admin.exercise-profiles.delete', profile.id));
};
</script>

<template>
    <AppLayout
        :breadcrumbs="[
            { title: 'Admin', href: route('admin.index') },
            { title: 'Exercise profiles', href: route('admin.exercise-profiles') },
        ]"
    >
        <Head title="Admin · Exercise profiles" />
        <AdminLayout>
            <div class="space-y-8">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <HeadingSmall
                        title="Exercise profile presets"
                        description="Create draft profiles, review the details, then publish them for everyone to use."
                    />
                    <Button type="button" @click="openCreate">New draft</Button>
                </div>

                <InputError :message="actionForm.errors.profile" />

                <section class="space-y-4">
                    <h2 class="text-sm font-semibold text-foreground">Drafts</h2>
                    <p v-if="!drafts.length" class="rounded-xl border border-dashed border-border px-4 py-5 text-sm text-muted-foreground">
                        No draft profiles yet.
                    </p>
                    <div v-for="profile in drafts" :key="profile.id" class="rounded-xl border border-border bg-card p-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h3 class="font-semibold text-foreground">{{ profile.name }}</h3>
                                <p class="mt-1 text-sm text-muted-foreground">Target {{ profile.target_reps }} · Floor {{ profile.floor }}</p>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <Button type="button" variant="outline" size="sm" @click="openEdit(profile)">Edit</Button>
                                <Button type="button" size="sm" @click="publish(profile)">Publish</Button>
                                <Button
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
                        <p class="mt-3 text-xs text-muted-foreground">
                            Rest {{ formatRest(profile.working_rest_seconds) }} ·
                            {{ profile.warm_up_steps.length ? formatProfileWarmUpSteps(profile.warm_up_steps) : 'No warm-up' }}
                        </p>
                    </div>
                </section>

                <section class="space-y-4 border-t border-border pt-8">
                    <h2 class="text-sm font-semibold text-foreground"><BrandName class="mr-1" />presets</h2>
                    <div v-for="profile in published" :key="profile.id" class="rounded-xl border border-border bg-card p-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h3 class="font-semibold text-foreground"><BrandName class="mr-1" />{{ profile.name }}</h3>
                                <p class="mt-1 text-sm text-muted-foreground">Target {{ profile.target_reps }} · Floor {{ profile.floor }}</p>
                            </div>
                            <span class="rounded-full bg-primary/15 px-2 py-0.5 text-xs text-primary">Can't be edited</span>
                        </div>
                        <p class="mt-3 text-xs text-muted-foreground">
                            Rest {{ formatRest(profile.working_rest_seconds) }} ·
                            {{ profile.warm_up_steps.length ? formatProfileWarmUpSteps(profile.warm_up_steps) : 'No warm-up' }}
                        </p>
                    </div>
                </section>
            </div>

            <Dialog v-model:open="dialogOpen">
                <DialogContent class="max-h-[90dvh] overflow-y-auto sm:max-w-xl">
                    <DialogHeader>
                        <DialogTitle>{{ editingProfile ? `Edit draft: ${editingProfile.name}` : 'New draft profile' }}</DialogTitle>
                        <DialogDescription>
                            Review everything before publishing. Published profiles appear in everyone's profile list and cannot be changed later.
                        </DialogDescription>
                    </DialogHeader>

                    <form class="space-y-5" @submit.prevent="submit">
                        <label class="flex flex-col gap-1 text-sm text-muted-foreground">
                            Base name
                            <input
                                v-model="form.name"
                                class="rounded border border-border bg-background px-3 py-2 text-foreground"
                                required
                                maxlength="255"
                            />
                            <span class="flex flex-wrap items-center gap-1 text-xs text-muted-foreground/80">
                                The published display name uses the <BrandName class="text-xs" /> prefix.
                            </span>
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
                                <label
                                    v-else-if="(step.mode ?? 'percent') === 'fixed'"
                                    class="flex flex-1 flex-col gap-1 text-xs text-muted-foreground"
                                >
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
                                <button
                                    type="button"
                                    class="pb-2 text-xs text-muted-foreground hover:text-destructive"
                                    @click="removeWarmUpStep(index)"
                                >
                                    Remove
                                </button>
                            </div>
                            <p v-if="!form.warm_up_steps.length" class="text-xs text-muted-foreground">No warm-up steps.</p>
                            <InputError :message="form.errors.warm_up_steps" />
                        </div>

                        <DialogFooter>
                            <Button type="button" variant="outline" @click="closeDialog">Cancel</Button>
                            <Button type="submit" :disabled="form.processing">{{ form.processing ? 'Saving…' : 'Save draft' }}</Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AdminLayout>
    </AppLayout>
</template>
