<script setup lang="ts">
import type { DateRange, DateValue } from '@internationalized/date';
import { CalendarDate, getLocalTimeZone, today } from '@internationalized/date';
import { CalendarDays, ChevronLeft, ChevronRight } from '@lucide/vue';
import { computed, ref } from 'vue';
import {
    DateRangePickerCalendar,
    DateRangePickerCell,
    DateRangePickerCellTrigger,
    DateRangePickerContent,
    DateRangePickerGrid,
    DateRangePickerGridBody,
    DateRangePickerGridHead,
    DateRangePickerGridRow,
    DateRangePickerHeadCell,
    DateRangePickerHeader,
    DateRangePickerNext,
    DateRangePickerPrev,
    DateRangePickerRoot,
    DateRangePickerTrigger,
} from 'reka-ui';
const model = defineModel<DateRange | undefined>();
const open = defineModel<boolean>('open', { default: false });
const currentDate = today(getLocalTimeZone());
const placeholder = ref<DateValue>(currentDate);

const monthOptions = Array.from({ length: 12 }, (_, index) => ({
    value: index + 1,
    label: new Intl.DateTimeFormat('en-US', { month: 'long' }).format(new Date(2020, index, 1)),
}));
const yearOptions = Array.from({ length: currentDate.year - 1999 }, (_, index) => currentDate.year - index);
const displayedMonth = computed(() => placeholder.value.month);
const displayedYear = computed(() => placeholder.value.year);

const presetOptions = [
    { label: 'Today', range: { start: currentDate, end: currentDate } },
    { label: 'Yesterday', range: { start: currentDate.subtract({ days: 1 }), end: currentDate.subtract({ days: 1 }) } },
    { label: 'Last 7 days', range: { start: currentDate.subtract({ days: 6 }), end: currentDate } },
    { label: 'Last 30 days', range: { start: currentDate.subtract({ days: 29 }), end: currentDate } },
    { label: 'This month', range: { start: new CalendarDate(currentDate.year, currentDate.month, 1), end: currentDate } },
];

function formatDate(value?: DateValue): string {
    return value?.toDate(getLocalTimeZone()).toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    }) ?? '';
}

function dateLabel(range?: DateRange): string {
    if (!range?.start) return 'Last login date';
    if (!range.end) return formatDate(range.start);
    return `${formatDate(range.start)} - ${formatDate(range.end)}`;
}

function applyPreset(range: DateRange): void {
    model.value = range;
    open.value = false;
}

function clearRange(): void {
    model.value = undefined;
    open.value = false;
}

function changeMonth(month: number): void {
    placeholder.value = new CalendarDate(displayedYear.value, month, 1);
}

function changeYear(year: number): void {
    placeholder.value = new CalendarDate(year, displayedMonth.value, 1);
}
</script>

<template>
    <DateRangePickerRoot v-model="model" v-model:open="open" v-model:placeholder="placeholder" :number-of-months="2" :max-value="currentDate" :close-on-select="false" locale="en-US">
        <DateRangePickerTrigger as-child>
            <button type="button" class="border-input bg-background hover:bg-accent hover:text-accent-foreground flex h-9 w-full items-center gap-2 rounded-md border px-3 text-left text-sm font-normal shadow-xs outline-none transition-colors focus-visible:ring-2 focus-visible:ring-ring/50 lg:w-64">
                <CalendarDays class="text-muted-foreground size-4" />
                <span class="truncate" :class="!model?.start && 'text-muted-foreground'">{{ dateLabel(model) }}</span>
            </button>
        </DateRangePickerTrigger>
        <DateRangePickerContent align="start" class="bg-popover text-popover-foreground z-50 mt-2 w-[min(100vw-2rem,42rem)] rounded-lg border p-3 shadow-lg outline-none">
            <div class="border-border mb-3 flex flex-wrap gap-1 border-b pb-3">
                <button
                    v-for="preset in presetOptions"
                    :key="preset.label"
                    type="button"
                    class="hover:bg-accent hover:text-accent-foreground rounded-md px-2.5 py-1.5 text-xs font-medium transition-colors"
                    @click="applyPreset(preset.range)"
                >
                    {{ preset.label }}
                </button>
            </div>
            <DateRangePickerCalendar v-slot="{ grid, weekDays }">
                <DateRangePickerHeader class="flex items-center justify-between pb-3">
                    <DateRangePickerPrev class="hover:bg-accent inline-flex size-8 items-center justify-center rounded-md border">
                        <ChevronLeft class="size-4" />
                    </DateRangePickerPrev>
                    <div class="flex items-center gap-2">
                        <select
                            aria-label="Select month"
                            class="bg-background h-8 rounded-md border px-2 text-sm font-semibold outline-none focus-visible:ring-2 focus-visible:ring-ring/50"
                            :value="displayedMonth"
                            @change="changeMonth(Number(($event.target as HTMLSelectElement).value))"
                        >
                            <option v-for="month in monthOptions" :key="month.value" :value="month.value">{{ month.label }}</option>
                        </select>
                        <select
                            aria-label="Select year"
                            class="bg-background h-8 rounded-md border px-2 text-sm font-semibold outline-none focus-visible:ring-2 focus-visible:ring-ring/50"
                            :value="displayedYear"
                            @change="changeYear(Number(($event.target as HTMLSelectElement).value))"
                        >
                            <option v-for="year in yearOptions" :key="year" :value="year">{{ year }}</option>
                        </select>
                    </div>
                    <DateRangePickerNext class="hover:bg-accent inline-flex size-8 items-center justify-center rounded-md border">
                        <ChevronRight class="size-4" />
                    </DateRangePickerNext>
                </DateRangePickerHeader>
                <div class="grid gap-4 sm:grid-cols-2">
                    <DateRangePickerGrid v-for="month in grid" :key="month.value.toString()" :value="month.value" class="w-full select-none sm:w-64">
                        <DateRangePickerGridHead>
                            <DateRangePickerGridRow class="mb-2 grid grid-cols-7">
                                <DateRangePickerHeadCell v-for="day in weekDays" :key="day" class="text-muted-foreground text-center text-[0.8rem] font-normal">
                                    {{ day.slice(0, 2) }}
                                </DateRangePickerHeadCell>
                            </DateRangePickerGridRow>
                        </DateRangePickerGridHead>
                        <DateRangePickerGridBody>
                            <DateRangePickerGridRow v-for="(weekDates, index) in month.rows" :key="`week-${index}`" class="grid grid-cols-7">
                                <DateRangePickerCell v-for="weekDate in weekDates" :key="weekDate.toString()" :date="weekDate" class="relative p-0 text-center text-sm">
                                    <DateRangePickerCellTrigger :day="weekDate" :month="month.value" class="data-[selected]:bg-primary data-[selected]:text-primary-foreground data-[selection-start]:rounded-l-md data-[selection-end]:rounded-r-md data-[selected]:rounded-md data-[outside-view]:text-muted-foreground hover:bg-accent inline-flex size-9 items-center justify-center rounded-md font-normal outline-none data-[today]:font-semibold focus-visible:ring-2 focus-visible:ring-ring/50" />
                                </DateRangePickerCell>
                            </DateRangePickerGridRow>
                        </DateRangePickerGridBody>
                    </DateRangePickerGrid>
                </div>
            </DateRangePickerCalendar>
            <div class="border-border mt-3 flex items-center justify-between gap-3 border-t pt-3">
                <span class="text-muted-foreground truncate text-xs">{{ model?.start ? dateLabel(model) : 'Choose a start and end date' }}</span>
                <div class="flex shrink-0 gap-2">
                    <button type="button" class="hover:bg-accent rounded-md px-2.5 py-1.5 text-xs font-medium" @click="clearRange">Clear</button>
                    <button type="button" class="bg-primary text-primary-foreground hover:bg-primary/90 rounded-md px-2.5 py-1.5 text-xs font-medium" @click="open = false">Done</button>
                </div>
            </div>
        </DateRangePickerContent>
    </DateRangePickerRoot>
</template>
