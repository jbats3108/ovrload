<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Sheet, SheetContent, SheetDescription, SheetTitle } from '@/components/ui/sheet';
import { routineEditorKey } from '@/routines/composables/useRoutineEditor';
import { filterExercises } from '@/routines/lib/catalog';
import type { EquipmentOption, ExerciseOption, MuscleGroupOption } from '@/routines/types';
import { useHttp } from '@inertiajs/vue3';
import { ChevronDown } from 'lucide-vue-next';
import { computed, inject, nextTick, ref, watch } from 'vue';

const model = defineModel<number | null>({ required: true });

const props = withDefaults(
    defineProps<{
        /** Highlight when this slot is the editor’s active selection. */
        active?: boolean;
        variant?: 'mobile' | 'desktop' | 'compact' | 'action';
        catalog?: ExerciseOption[];
        muscleGroups?: MuscleGroupOption[];
        equipmentOptions?: EquipmentOption[];
        triggerLabel?: string;
        disabled?: boolean;
    }>(),
    { active: false, variant: 'mobile', disabled: false },
);

const emit = defineEmits<{
    open: [];
}>();

const editor = inject(routineEditorKey, null);

const open = ref(false);
const query = ref('');
const searchEl = ref<HTMLInputElement | null>(null);
const creating = ref(false);
const catalogExtras = ref<ExerciseOption[]>([]);

const catalog = computed(() => {
    const base = props.catalog ?? editor?.catalog.value ?? [];
    const seen = new Set(base.map((exercise) => exercise.id));

    return [...base, ...catalogExtras.value.filter((exercise) => !seen.has(exercise.id))];
});
const muscleGroups = computed(() => props.muscleGroups ?? editor?.muscleGroups.value ?? []);
const equipmentOptions = computed(() => props.equipmentOptions ?? editor?.equipmentOptions.value ?? []);

const exerciseName = (id: number | null): string => catalog.value.find((exercise) => exercise.id === id)?.name ?? 'Exercise';

const matches = computed(() => filterExercises(catalog.value, query.value));

const label = computed(() => {
    if (props.triggerLabel) {
        return props.triggerLabel;
    }
    if (!catalog.value.length && !creating.value) {
        return 'Loading…';
    }
    return exerciseName(model.value);
});

const createForm = useHttp({
    name: '',
    primary_muscle_group: '',
    secondary_muscle_group: null as string | null,
    equipment: null as string | null,
});

watch(open, async (isOpen) => {
    if (!isOpen) {
        creating.value = false;
        return;
    }
    emit('open');
    query.value = '';
    await nextTick();
    searchEl.value?.focus();
});

watch(
    muscleGroups,
    (groups) => {
        if (!createForm.primary_muscle_group && groups[0]) {
            createForm.primary_muscle_group = groups[0].slug;
        }
    },
    { immediate: true },
);

const pick = (id: number) => {
    model.value = id;
    open.value = false;
    query.value = '';
    creating.value = false;
};

/**
 * Bypass Vue v-model’s composition gate — mobile keyboards (esp. Android) keep
 * `composing` true until space/accept, which delayed filtering mid-word.
 */
const syncQuery = (event: Event) => {
    query.value = (event.target as HTMLInputElement).value;
};

const startCreate = () => {
    creating.value = true;
    createForm.clearErrors();
    createForm.name = query.value.trim();
    createForm.secondary_muscle_group = null;
    createForm.equipment = null;
    if (!createForm.primary_muscle_group && muscleGroups.value[0]) {
        createForm.primary_muscle_group = muscleGroups.value[0].slug;
    }
};

const addToCatalog = (exercise: ExerciseOption) => {
    if (editor) {
        editor.addToCatalog(exercise);
        return;
    }

    if (catalog.value.some((item) => item.id === exercise.id)) {
        return;
    }

    catalogExtras.value = [...catalogExtras.value, exercise];
};

const cancelCreate = () => {
    creating.value = false;
    createForm.clearErrors();
};

const submitCreate = () => {
    createForm
        .transform((data) => ({
            ...data,
            secondary_muscle_group: data.secondary_muscle_group || null,
            equipment: data.equipment || null,
        }))
        .post(route('exercises.custom.store'), {
            onSuccess: (data) => {
                const created = data as ExerciseOption;
                addToCatalog({
                    id: created.id,
                    name: created.name,
                    primary_muscle_group: created.primary_muscle_group,
                    is_custom: true,
                });
                pick(created.id);
            },
        });
};
</script>

<template>
    <Sheet v-model:open="open">
        <button
            type="button"
            class="outline-none focus:border-primary"
            :class="[
                props.variant === 'mobile'
                    ? 'flex w-full items-center justify-between gap-2 rounded-xl border border-border bg-background px-3 py-2.5 text-left text-base'
                    : props.variant === 'action'
                      ? 'flex w-full items-center justify-center rounded-md border border-primary/40 px-3 py-1.5 text-sm font-medium text-primary hover:bg-primary/10 disabled:opacity-50'
                      : props.variant === 'compact'
                        ? 'flex items-center justify-between gap-2 rounded-md border border-border px-3 py-1.5 text-left text-sm text-muted-foreground hover:bg-secondary hover:text-foreground'
                        : 'flex w-44 items-center justify-between gap-2 rounded border border-border bg-card px-2 py-1 text-left text-sm',
                props.active ? 'border-primary' : '',
            ]"
            :disabled="props.disabled || (!catalog.length && !muscleGroups.length)"
            :aria-expanded="open"
            aria-haspopup="dialog"
            @click="open = true"
        >
            <span class="min-w-0 truncate" :class="props.variant === 'action' ? '' : 'text-foreground'">{{ label }}</span>
            <ChevronDown v-if="props.variant !== 'action'" class="size-4 shrink-0 text-muted-foreground" />
        </button>

        <SheetContent
            side="bottom"
            class="flex h-[min(85dvh,40rem)] max-h-[85dvh] flex-col gap-0 border-border p-0 [&>button]:top-3 [&>button]:right-3"
        >
            <div class="border-b border-border px-4 pt-4 pr-12 pb-3">
                <SheetTitle class="text-base font-semibold text-foreground">
                    {{ creating ? 'New custom exercise' : 'Choose exercise' }}
                </SheetTitle>
                <SheetDescription class="sr-only">
                    {{ creating ? 'Name your personal lift and pick a muscle group.' : 'Search by name or muscle group, then tap a match.' }}
                </SheetDescription>

                <template v-if="!creating">
                    <label class="mt-3 flex flex-col gap-1 text-xs text-muted-foreground">
                        Search
                        <input
                            ref="searchEl"
                            :value="query"
                            type="text"
                            inputmode="search"
                            enterkeyhint="search"
                            autocomplete="off"
                            autocapitalize="off"
                            autocorrect="off"
                            spellcheck="false"
                            placeholder="Name or muscle group…"
                            class="w-full rounded-xl border border-border bg-background px-3 py-2.5 text-base text-foreground outline-none focus:border-primary"
                            @input="syncQuery"
                            @compositionupdate="syncQuery"
                        />
                    </label>
                    <div class="mt-2 flex items-center justify-between gap-3">
                        <p class="text-xs text-muted-foreground">{{ matches.length }} of {{ catalog.length }}</p>
                        <button
                            type="button"
                            class="shrink-0 text-sm font-medium text-primary disabled:opacity-50"
                            :disabled="!muscleGroups.length"
                            @click="startCreate"
                        >
                            Create custom{{ query.trim() ? ` “${query.trim()}”` : '' }}
                        </button>
                    </div>
                </template>
            </div>

            <form
                v-if="creating"
                class="flex min-h-0 flex-1 flex-col gap-3 overflow-y-auto overscroll-contain px-4 py-4"
                @submit.prevent="submitCreate"
            >
                <label class="flex flex-col gap-1 text-xs text-muted-foreground">
                    Name
                    <input
                        v-model="createForm.name"
                        type="text"
                        required
                        maxlength="255"
                        autocomplete="off"
                        class="w-full rounded-xl border border-border bg-background px-3 py-2.5 text-base text-foreground outline-none focus:border-primary"
                    />
                    <InputError :message="createForm.errors.name" />
                </label>
                <label class="flex flex-col gap-1 text-xs text-muted-foreground">
                    Primary muscle group
                    <select
                        v-model="createForm.primary_muscle_group"
                        required
                        class="w-full rounded-xl border border-border bg-background px-3 py-2.5 text-base text-foreground outline-none focus:border-primary"
                    >
                        <option v-for="group in muscleGroups" :key="group.slug" :value="group.slug">
                            {{ group.name }}
                        </option>
                    </select>
                    <InputError :message="createForm.errors.primary_muscle_group" />
                </label>
                <label class="flex flex-col gap-1 text-xs text-muted-foreground">
                    Secondary (optional)
                    <select
                        v-model="createForm.secondary_muscle_group"
                        class="w-full rounded-xl border border-border bg-background px-3 py-2.5 text-base text-foreground outline-none focus:border-primary"
                    >
                        <option :value="null">None</option>
                        <option v-for="group in muscleGroups" :key="group.slug" :value="group.slug">
                            {{ group.name }}
                        </option>
                    </select>
                    <InputError :message="createForm.errors.secondary_muscle_group" />
                </label>
                <label class="flex flex-col gap-1 text-xs text-muted-foreground">
                    Equipment (optional)
                    <select
                        v-model="createForm.equipment"
                        class="w-full rounded-xl border border-border bg-background px-3 py-2.5 text-base text-foreground outline-none focus:border-primary"
                    >
                        <option :value="null">Unspecified</option>
                        <option v-for="opt in equipmentOptions" :key="opt.value" :value="opt.value">
                            {{ opt.label }}
                        </option>
                    </select>
                    <InputError :message="createForm.errors.equipment" />
                </label>
                <div class="mt-auto flex gap-2 pt-2">
                    <button
                        type="button"
                        class="flex-1 rounded-xl border border-border px-3 py-2.5 text-sm text-muted-foreground"
                        :disabled="createForm.processing"
                        @click="cancelCreate"
                    >
                        Back
                    </button>
                    <button
                        type="submit"
                        class="flex-1 rounded-xl bg-primary px-3 py-2.5 text-sm font-medium text-primary-foreground disabled:opacity-60"
                        :disabled="createForm.processing || !createForm.name.trim()"
                    >
                        {{ createForm.processing ? 'Saving…' : 'Create & select' }}
                    </button>
                </div>
            </form>

            <template v-else>
                <ul class="min-h-0 flex-1 divide-y divide-border overflow-y-auto overscroll-contain">
                    <li v-for="exercise in matches" :key="exercise.id">
                        <button
                            type="button"
                            class="flex w-full flex-col items-start gap-0.5 px-4 py-3 text-left active:bg-secondary"
                            :class="exercise.id === model ? 'bg-primary/10' : ''"
                            @click="pick(exercise.id)"
                        >
                            <span class="flex w-full items-center gap-2 text-sm font-medium text-foreground">
                                <span class="min-w-0 truncate">{{ exercise.name }}</span>
                                <span
                                    v-if="exercise.is_custom"
                                    class="shrink-0 text-[10px] font-normal tracking-wide text-muted-foreground uppercase"
                                >
                                    Custom
                                </span>
                            </span>
                            <span class="font-mono text-xs text-muted-foreground">{{ exercise.primary_muscle_group }}</span>
                        </button>
                    </li>
                    <li v-if="!matches.length" class="px-4 py-8 text-center text-sm text-muted-foreground">
                        <p>{{ catalog.length ? 'No matches.' : 'Loading exercises…' }}</p>
                        <button
                            v-if="catalog.length && muscleGroups.length"
                            type="button"
                            class="mt-3 text-sm font-medium text-primary"
                            @click="startCreate"
                        >
                            Create custom{{ query.trim() ? ` “${query.trim()}”` : '' }}
                        </button>
                    </li>
                </ul>
            </template>
        </SheetContent>
    </Sheet>
</template>
