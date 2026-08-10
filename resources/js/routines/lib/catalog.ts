import type { ExerciseOption } from '@/routines/types';
import { filterByQuery } from '@/shared/lib/catalogFilter';

export function filterExercises(catalog: ExerciseOption[], query: string): ExerciseOption[] {
    return filterByQuery(catalog, query, (e) => [e.name, e.primary_muscle_group ?? '', e.is_custom ? 'custom' : '']);
}
