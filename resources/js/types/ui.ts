import type { CalendarDate } from '@internationalized/date';

export type Appearance = 'light' | 'dark' | 'system';
export type ResolvedAppearance = 'light' | 'dark';

export type AppVariant = 'header' | 'sidebar';

export type FlashToast = {
    type: 'success' | 'info' | 'warning' | 'error';
    message: string;
};

export type DateRange = {
    start: CalendarDate | undefined;
    end: CalendarDate | undefined;
};
