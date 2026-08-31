<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { gramsToKg } from '@/lib/plateCalculator';
import ExerciseProfilesSection from '@/settings/components/ExerciseProfilesSection.vue';
import type { ExerciseProfilePage, PlateProfile, WarmUpDefaultsScope, WarmUpStep } from '@/settings/types';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    warm_up_steps_default: WarmUpStep[];
    warm_up_defaults_scope: WarmUpDefaultsScope;
    using_app_fallback: boolean;
    achievement_floor_default: number | null;
    progression_target_default: number;
    progression_style_default: 'straight_sets' | 'progressive_overload';
    progressive_mid_block_default: 'ask' | 'auto';
    deload_weight_factor_default: number;
    deload_reps_factor_default: number;
    deload_every_n_default: number;
    plate_profile: PlateProfile;
    exercise_profiles: ExerciseProfilePage;
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
    progression_style_default: props.progression_style_default,
    progressive_mid_block_default: props.progressive_mid_block_default,
    deload_weight_factor_default: props.deload_weight_factor_default,
    deload_reps_factor_default: props.deload_reps_factor_default,
    deload_every_n_default: props.deload_every_n_default,
});

const plateForm = useForm({
    name: props.plate_profile.name,
    bars: props.plate_profile.bars.map((b) => ({ ...b })),
    plates: props.plate_profile.plates.map((p) => ({ ...p })),
});

const submit = () => {
    form.put(route('training.update'));
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

                <ExerciseProfilesSection :exercise-profiles="exercise_profiles" />

                <section class="space-y-6">
                    <HeadingSmall
                        title="Warm-up placement"
                        description="Choose where the selected profile's warm-up ladder is seeded when you add routine exercises."
                    />

                    <form class="space-y-4" @submit.prevent="submit">
                        <fieldset class="space-y-2">
                            <legend class="text-sm text-muted-foreground">Apply profile warm-ups to</legend>
                            <label class="flex items-center gap-2 text-sm">
                                <input v-model="form.warm_up_defaults_scope" type="radio" value="all_blocks" />
                                Every new exercise
                            </label>
                            <label class="flex items-center gap-2 text-sm">
                                <input v-model="form.warm_up_defaults_scope" type="radio" value="first_block" />
                                First exercise only
                            </label>
                        </fieldset>

                        <InputError :message="form.errors.warm_up_defaults_scope" />

                        <button
                            type="submit"
                            class="rounded-full bg-primary px-5 py-2 text-sm font-semibold text-primary-foreground disabled:opacity-50"
                            :disabled="form.processing"
                        >
                            Save warm-up placement
                        </button>

                        <div class="space-y-4 border-t border-border pt-6">
                            <HeadingSmall
                                title="Progression"
                                description="Defaults for new workouts. Target and Floor now come from exercise profiles."
                            />

                            <fieldset class="space-y-2">
                                <legend class="text-sm text-muted-foreground">Progression style</legend>
                                <span class="block text-xs text-muted-foreground/80">
                                    Controls mid-session ramping and when a finish bump is offered after you hit Target reps. Snapshotted when a
                                    workout starts.
                                </span>
                                <label class="flex items-center gap-2 text-sm">
                                    <input v-model="form.progression_style_default" type="radio" value="straight_sets" />
                                    Straight Sets — same weight all block; finish bump if any set hit Target
                                </label>
                                <label class="flex items-center gap-2 text-sm">
                                    <input v-model="form.progression_style_default" type="radio" value="progressive_overload" />
                                    Progressive Overload — bump the next set when Target is hit; finish bump if the final working set was at your top
                                    weight and hit Target
                                </label>
                                <InputError :message="form.errors.progression_style_default" />
                            </fieldset>

                            <fieldset v-if="form.progression_style_default === 'progressive_overload'" class="space-y-2">
                                <legend class="text-sm text-muted-foreground">Mid-block bump</legend>
                                <span class="block text-xs text-muted-foreground/80">
                                    When a working set hits Target, raise the next set by 2.5 kg automatically or ask on rest.
                                </span>
                                <label class="flex items-center gap-2 text-sm">
                                    <input v-model="form.progressive_mid_block_default" type="radio" value="ask" />
                                    Ask on rest
                                </label>
                                <label class="flex items-center gap-2 text-sm">
                                    <input v-model="form.progressive_mid_block_default" type="radio" value="auto" />
                                    Auto-bump next set
                                </label>
                                <InputError :message="form.errors.progressive_mid_block_default" />
                            </fieldset>
                        </div>

                        <div class="space-y-4 border-t border-border pt-6">
                            <HeadingSmall
                                title="Deload"
                                description="Defaults for new routines. A deload workout scales every exercise on that routine for one session; your usual working weights stay unchanged. Each routine can set its own values in the editor."
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
