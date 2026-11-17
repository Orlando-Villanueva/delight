import type { SettingsValues } from './reader-types.ts';

export function reconcileSettings(current: SettingsValues, previous: SettingsValues, confirmed: SettingsValues): SettingsValues {
    const next = { ...confirmed };
    for (const field of Object.keys(previous) as (keyof SettingsValues)[]) {
        if (current[field] !== previous[field]) Object.assign(next, { [field]: current[field] });
    }
    return next;
}
