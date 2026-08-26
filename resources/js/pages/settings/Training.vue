<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { gramsToKg } from '@/lib/plateCalculator';
import type { PlateProfile, WarmUpDefaultsScope, WarmUpStep } from '@/settings/types';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    warm_up_steps_default: WarmUpStep[];
    warm_up_defaults_scope: WarmUpDefaultsScope;
    using_app_fallback: boolean;
    achievement_floor_default: number | null;
    progression_target_default: number;
    bump_when_default: 'any_set' | 'last_at_top_weight';
    deload_weight_factor_default: number;
    deload_reps_factor_default: number;
    deload_every_n_default: number;
    plate_profile: PlateProfile;
}>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Training',
        href: '/settings/training',
    },
];

const form = useForm({
    warm_up_steps_default: props.warm_up_steps_default.map((s) => ({ ...s })),
    warm_up_defaults_scope: props.warm_up_defaults_scope,
    achievement_floor_default: props.achievement_floor_default,
    progression_target_default: props.progression_target_default,
    bump_when_default: props.bump_when_default,
    deload_weight_factor_default: props.deload_weight_factor_default,
    deload_reps_factor_default: props.deload_reps_factor_default,
    deload_every_n_default: props.deload_every_n_default,
});

const resetForm = useForm({});

const setOptionalReps = (field: 'achievement_floor_default', raw: string) => {
    form[field] = raw === '' ? null : Number(raw);
};

const plateForm = useForm({
    name: props.plate_profile.name,
    bars: props.plate_profile.bars.map((b) => ({ ...b })),
    plates: props.plate_profile.plates.map((p) => ({ ...p })),
});

const addStep = () => {
    form.warm_up_steps_default.push({ percent: 50, reps: 5 });
};

const removeStep = (index: number) => {
    form.warm_up_steps_default.splice(index, 1);
};

const submit = () => {
    form.put(route('training.update'));
};

const resetToApp = () => {
    if (resetForm.processing) {
        return;
    }
    resetForm.post(route('training.reset'));
};

const addBar = () => {
    plateForm.bars.push({ name: 'Bar', weight_g: 20000, is_default: plateForm.bars.length === 0 });
};

const removeBar = (index: number) => {
    plateForm.bars.splice(index, 1);
};

const setDefaultBar = (index: number) => {
    plateForm.bars.forEach((bar, i) => {
        bar.is_default = i === index;
    });
};

const addPlate = () => {
    plateForm.plates.push({ denomination_g: 10000, count: 2, colour: null });
};

const removePlate = (index: number) => {
    plateForm.plates.splice(index, 1);
};

const savePlates = () => {
    plateForm.put(route('training.plates.update'));
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Training" />

        <SettingsLayout section="Preferences">
            <div class="w-full space-y-10">
                <p class="text-sm text-muted-foreground">
                    <Link :href="route('tutorial')" class="font-medium text-primary underline-offset-2 hover:underline">Read the tutorial</Link>
                    for how these defaults feed into routines and Play.
                </p>

                <section class="space-y-6">
                    <HeadingSmall
                        title="Warm-up defaults"
                        description="Seeded into new routine exercises. Each step is a percent of working weight and its own reps."
                    />

                    <p v-if="using_app_fallback" class="text-sm text-muted-foreground">Using the app fallback ladder until you save your own.</p>

                    <form class="space-y-4" @submit.prevent="submit">
                        <fieldset class="space-y-2">
                            <legend class="text-sm text-muted-foreground">Apply defaults to</legend>
                            <label class="flex items-center gap-2 text-sm">
                                <input v-model="form.warm_up_defaults_scope" type="radio" value="all_blocks" />
                                Every new exercise
                            </label>
                            <label class="flex items-center gap-2 text-sm">
                                <input v-model="form.warm_up_defaults_scope" type="radio" value="first_block" />
                                First exercise only
                            </label>
                        </fieldset>

                        <div v-for="(step, index) in form.warm_up_steps_default" :key="index" class="flex flex-wrap items-end gap-3">
                            <label class="flex flex-col gap-1 text-sm text-muted-foreground">
                                % of working
                                <input
                                    v-model.number="step.percent"
                                    type="number"
                                    min="1"
                                    max="100"
                                    class="w-24 rounded border border-border bg-card px-3 py-2 font-mono text-foreground"
                                    required
                                />
                            </label>
                            <label class="flex flex-col gap-1 text-sm text-muted-foreground">
                                Reps
                                <input
                                    v-model.number="step.reps"
                                    type="number"
                                    min="1"
                                    max="100"
                                    class="w-24 rounded border border-border bg-card px-3 py-2 font-mono text-foreground"
                                    required
                                />
                            </label>
                            <button
                                type="button"
                                class="rounded border border-border px-3 py-2 text-sm text-muted-foreground hover:text-destructive"
                                @click="removeStep(index)"
                            >
                                Remove
                            </button>
                        </div>

                        <InputError :message="form.errors.warm_up_steps_default" />

                        <div class="flex flex-wrap gap-3 pt-2">
                            <button
                                type="button"
                                class="rounded-full border border-border px-4 py-2 text-sm text-muted-foreground hover:text-foreground"
                                @click="addStep"
                            >
                                + Step
                            </button>
                            <button
                                type="button"
                                class="rounded-full border border-border px-4 py-2 text-sm text-muted-foreground hover:text-foreground"
                                :disabled="resetForm.processing"
                                @click="resetToApp"
                            >
                                Reset warm-ups
                            </button>
                        </div>

                        <div class="space-y-4 border-t border-border pt-6">
                            <HeadingSmall
                                title="Progression"
                                description="Defaults for new workouts. Per-exercise overrides in the routine editor win when set."
                            />

                            <label class="flex flex-col gap-1 text-sm text-muted-foreground">
                                Achievement Floor
                                <span class="text-xs text-muted-foreground/80">
                                    Minimum reps for a logged set’s weight to count as achieved (carry-forward). Leave blank to disable a default.
                                </span>
                                <input
                                    :value="form.achievement_floor_default ?? ''"
                                    type="number"
                                    min="1"
                                    max="100"
                                    placeholder="optional"
                                    class="mt-1 w-28 rounded border border-border bg-card px-3 py-2 font-mono text-foreground"
                                    @input="setOptionalReps('achievement_floor_default', ($event.target as HTMLInputElement).value)"
                                />
                                <InputError :message="form.errors.achievement_floor_default" />
                            </label>

                            <label class="flex flex-col gap-1 text-sm text-muted-foreground">
                                Default Target reps
                                <span class="text-xs text-muted-foreground/80">
                                    Seeded into new routine exercises and Play “Add exercise” blocks. Per-exercise Target in the editor still wins
                                    once set.
                                </span>
                                <input
                                    v-model.number="form.progression_target_default"
                                    type="number"
                                    min="1"
                                    max="100"
                                    class="mt-1 w-28 rounded border border-border bg-card px-3 py-2 font-mono text-foreground"
                                    required
                                />
                                <InputError :message="form.errors.progression_target_default" />
                            </label>

                            <fieldset class="space-y-2">
                                <legend class="text-sm text-muted-foreground">Bump when</legend>
                                <span class="block text-xs text-muted-foreground/80">
                                    Bump unlocks when you hit the exercise’s Target (prescribed) reps. Top weight = heaviest completed working set;
                                    the last of those decides under “Last set at top weight.”
                                </span>
                                <label class="flex items-center gap-2 text-sm">
                                    <input v-model="form.bump_when_default" type="radio" value="any_set" />
                                    Any set
                                </label>
                                <label class="flex items-center gap-2 text-sm">
                                    <input v-model="form.bump_when_default" type="radio" value="last_at_top_weight" />
                                    Last set at top weight
                                </label>
                                <InputError :message="form.errors.bump_when_default" />
                            </fieldset>
                        </div>

                        <div class="space-y-4 border-t border-border pt-6">
                            <HeadingSmall
                                title="Deload"
                                description="Defaults for new routines. A deload workout scales every exercise on that routine for one session; your usual working weights stay unchanged. Each routine can still override these in the editor."
                            />

                            <label class="flex flex-col gap-1 text-sm text-muted-foreground">
                                Weight multiplier
                                <span class="text-xs text-muted-foreground/80">
                                    Applied to working weight when you start a deload (e.g. 0.5 → half the usual load). Same factor for every exercise
                                    on the routine.
                                </span>
                                <input
                                    v-model.number="form.deload_weight_factor_default"
                                    type="number"
                                    step="0.05"
                                    min="0"
                                    max="5"
                                    class="mt-1 w-28 rounded border border-border bg-card px-3 py-2 font-mono text-foreground"
                                    required
                                />
                                <InputError :message="form.errors.deload_weight_factor_default" />
                            </label>

                            <label class="flex flex-col gap-1 text-sm text-muted-foreground">
                                Reps multiplier
                                <span class="text-xs text-muted-foreground/80">
                                    Applied to prescribed (Target) reps on a deload start (e.g. 0.5 → half the usual reps, rounded down). Same factor
                                    for every exercise.
                                </span>
                                <input
                                    v-model.number="form.deload_reps_factor_default"
                                    type="number"
                                    step="0.05"
                                    min="0"
                                    max="10"
                                    class="mt-1 w-28 rounded border border-border bg-card px-3 py-2 font-mono text-foreground"
                                    required
                                />
                                <InputError :message="form.errors.deload_reps_factor_default" />
                            </label>

                            <label class="flex flex-col gap-1 text-sm text-muted-foreground">
                                Every N standards
                                <span class="text-xs text-muted-foreground/80">
                                    After this many finished standard workouts on a routine since its last deload, the dashboard soft-emphasizes
                                    Deload (e.g. 3 ≈ every 4th session). Set 0 to never suggest — useful for rares or one-offs. The count is per
                                    routine, not shared across all of them.
                                </span>
                                <input
                                    v-model.number="form.deload_every_n_default"
                                    type="number"
                                    step="1"
                                    min="0"
                                    max="99"
                                    class="mt-1 w-28 rounded border border-border bg-card px-3 py-2 font-mono text-foreground"
                                    required
                                />
                                <InputError :message="form.errors.deload_every_n_default" />
                            </label>
                        </div>

                        <div class="flex flex-wrap gap-3 pt-2">
                            <button
                                type="submit"
                                class="rounded-full bg-primary px-5 py-2 text-sm font-semibold text-primary-foreground disabled:opacity-50"
                                :disabled="form.processing"
                            >
                                Save training defaults
                            </button>
                        </div>
                    </form>
                </section>

                <section class="space-y-6 border-t border-border pt-10">
                    <HeadingSmall title="Plate profile" description="Bars and plates for the calculator. Counts are total plates (both sides)." />

                    <form class="space-y-6" @submit.prevent="savePlates">
                        <label class="flex flex-col gap-1 text-sm text-muted-foreground">
                            Profile name
                            <input v-model="plateForm.name" class="rounded border border-border bg-card px-3 py-2 text-foreground" required />
                            <InputError :message="plateForm.errors.name" />
                        </label>

                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <p class="text-sm font-medium">Bars</p>
                                <button type="button" class="text-xs text-primary" @click="addBar">+ Bar</button>
                            </div>
                            <div v-for="(bar, index) in plateForm.bars" :key="index" class="flex flex-wrap items-end gap-2">
                                <label class="flex flex-col gap-1 text-xs text-muted-foreground">
                                    Name
                                    <input v-model="bar.name" class="w-28 rounded border border-border bg-card px-2 py-1.5 text-sm" required />
                                </label>
                                <label class="flex flex-col gap-1 text-xs text-muted-foreground">
                                    kg
                                    <input
                                        :value="gramsToKg(bar.weight_g)"
                                        type="number"
                                        step="0.5"
                                        min="0"
                                        class="w-20 rounded border border-border bg-card px-2 py-1.5 font-mono text-sm"
                                        required
                                        @input="bar.weight_g = Math.round(Number(($event.target as HTMLInputElement).value) * 1000)"
                                    />
                                </label>
                                <label class="flex items-center gap-1 pb-2 text-xs">
                                    <input type="radio" name="default_bar" :checked="bar.is_default" @change="setDefaultBar(index)" />
                                    Default
                                </label>
                                <button type="button" class="pb-2 text-xs text-muted-foreground hover:text-destructive" @click="removeBar(index)">
                                    Remove
                                </button>
                            </div>
                            <InputError :message="plateForm.errors.bars" />
                        </div>

                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <p class="text-sm font-medium">Plates</p>
                                <button type="button" class="text-xs text-primary" @click="addPlate">+ Plate</button>
                            </div>
                            <div v-for="(plate, index) in plateForm.plates" :key="index" class="flex flex-wrap items-end gap-2">
                                <label class="flex flex-col gap-1 text-xs text-muted-foreground">
                                    kg
                                    <input
                                        :value="gramsToKg(plate.denomination_g)"
                                        type="number"
                                        step="0.25"
                                        min="0.25"
                                        class="w-20 rounded border border-border bg-card px-2 py-1.5 font-mono text-sm"
                                        required
                                        @input="plate.denomination_g = Math.round(Number(($event.target as HTMLInputElement).value) * 1000)"
                                    />
                                </label>
                                <label class="flex flex-col gap-1 text-xs text-muted-foreground">
                                    Count
                                    <input
                                        v-model.number="plate.count"
                                        type="number"
                                        min="0"
                                        max="100"
                                        class="w-16 rounded border border-border bg-card px-2 py-1.5 font-mono text-sm"
                                        required
                                    />
                                </label>
                                <label class="flex flex-col gap-1 text-xs text-muted-foreground">
                                    Colour
                                    <input
                                        v-model="plate.colour"
                                        class="w-24 rounded border border-border bg-card px-2 py-1.5 text-sm"
                                        placeholder="optional"
                                    />
                                </label>
                                <button type="button" class="pb-2 text-xs text-muted-foreground hover:text-destructive" @click="removePlate(index)">
                                    Remove
                                </button>
                            </div>
                            <InputError :message="plateForm.errors.plates" />
                        </div>

                        <button
                            type="submit"
                            class="rounded-full bg-primary px-5 py-2 text-sm font-semibold text-primary-foreground disabled:opacity-50"
                            :disabled="plateForm.processing"
                        >
                            Save plates
                        </button>
                    </form>
                </section>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
