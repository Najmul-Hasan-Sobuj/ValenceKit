<script setup lang="ts">
import {
    CalendarDate,
    endOfMonth,
    getLocalTimeZone,
    isEqualDay,
    startOfMonth,
    startOfYear,
    today,
} from '@internationalized/date';
import { CalendarDays, Check, ChevronDown, ChevronLeft, ChevronRight, X } from '@lucide/vue';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
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
    DateRangePickerNext,
    DateRangePickerPrev,
    DateRangePickerRoot,
    DateRangePickerTrigger,
} from 'reka-ui';
import type { DateValue } from '@internationalized/date';
import type { DateRange } from '@/types';

const props = withDefaults(defineProps<{
    placeholder?: string;
    class?: string;
}>(), {
    placeholder: 'Filter by date range',
});

const externalModel = defineModel<unknown>();
const model = computed<DateRange | undefined>({
    get: () => externalModel.value as DateRange | undefined,
    set: (value) => {
        externalModel.value = value;
    },
});
const open = defineModel<boolean>('open', { default: false });
const currentDate = today(getLocalTimeZone());

const rootModel = computed<DateRange | undefined>({
    get: () => model.value ?? { start: undefined, end: undefined },
    set: (val: any) => {
        if (!val || !val.start) {
            model.value = undefined;
        } else {
            model.value = val;
        }
    },
});
const pickerModel = computed<any>({
    get: () => rootModel.value,
    set: (value: DateRange | undefined) => {
        rootModel.value = value;
    },
});

function resolveInitialPlaceholder(): DateValue {
    if (model.value?.start) {
        if (model.value.start.year === currentDate.year && model.value.start.month === currentDate.month) {
            return currentDate.subtract({ months: 1 });
        }
        return model.value.start;
    }
    return currentDate.subtract({ months: 1 });
}

const placeholder = ref<DateValue>(resolveInitialPlaceholder());
const pickerPlaceholder = computed<any>({
    get: () => placeholder.value,
    set: (value: DateValue) => {
        placeholder.value = value;
    },
});

// Month & Year dropdown states
const isMonthOpen = ref(false);
const isYearOpen = ref(false);
const selectorContainerRef = ref<HTMLElement | null>(null);
const activeYearEl = ref<HTMLElement | null>(null);

function handleOutsideClick(event: MouseEvent): void {
    if (!isMonthOpen.value && !isYearOpen.value) {
        return;
    }
    const target = event.target as Node | null;
    if (selectorContainerRef.value && target && selectorContainerRef.value.contains(target)) {
        return;
    }
    isMonthOpen.value = false;
    isYearOpen.value = false;
}

onMounted(() => {
    window.addEventListener('click', handleOutsideClick, true);
});

onUnmounted(() => {
    window.removeEventListener('click', handleOutsideClick, true);
});

watch(open, (isOpen) => {
    if (isOpen) {
        placeholder.value = resolveInitialPlaceholder();
    } else {
        isMonthOpen.value = false;
        isYearOpen.value = false;
        if (model.value?.start && !model.value?.end) {
            model.value = {
                start: model.value.start,
                end: model.value.start,
            };
        }
    }
});

watch(isYearOpen, async (isOpen) => {
    if (isOpen) {
        isMonthOpen.value = false;
        await nextTick();
        activeYearEl.value?.scrollIntoView({ block: 'center' });
    }
});

function toggleMonthDropdown(): void {
    isYearOpen.value = false;
    isMonthOpen.value = !isMonthOpen.value;
}

function toggleYearDropdown(): void {
    isMonthOpen.value = false;
    isYearOpen.value = !isYearOpen.value;
}

const monthOptions = [
    { value: 1, label: 'January', shortLabel: 'Jan' },
    { value: 2, label: 'February', shortLabel: 'Feb' },
    { value: 3, label: 'March', shortLabel: 'Mar' },
    { value: 4, label: 'April', shortLabel: 'Apr' },
    { value: 5, label: 'May', shortLabel: 'May' },
    { value: 6, label: 'June', shortLabel: 'Jun' },
    { value: 7, label: 'July', shortLabel: 'Jul' },
    { value: 8, label: 'August', shortLabel: 'Aug' },
    { value: 9, label: 'September', shortLabel: 'Sep' },
    { value: 10, label: 'October', shortLabel: 'Oct' },
    { value: 11, label: 'November', shortLabel: 'Nov' },
    { value: 12, label: 'December', shortLabel: 'Dec' },
];

const displayedMonth = computed(() => placeholder.value.month);
const displayedYear = computed(() => placeholder.value.year);

const currentMonthLabel = computed(() => {
    return monthOptions.find((m) => m.value === displayedMonth.value)?.label ?? 'Month';
});

const yearOptions = computed(() => {
    const list: number[] = [];
    const minYear = 2018;
    for (let yr = currentDate.year; yr >= minYear; yr--) {
        list.push(yr);
    }
    return list;
});

function isMonthFuture(month: number, year?: number): boolean {
    const yr = year ?? displayedYear.value;
    if (yr > currentDate.year) return true;
    if (yr === currentDate.year && month > currentDate.month) return true;
    return false;
}

function selectMonth(month: number, currentMonthValue?: DateValue): void {
    const year = currentMonthValue?.year ?? displayedYear.value;
    if (isMonthFuture(month, year)) return;
    let targetMonth = month;
    if (year === currentDate.year && targetMonth === currentDate.month) {
        targetMonth = Math.max(1, targetMonth - 1);
    }
    placeholder.value = new CalendarDate(year, targetMonth, 1);
    isMonthOpen.value = false;
}

function selectYear(year: number, currentMonthValue?: DateValue): void {
    let targetMonth = currentMonthValue?.month ?? displayedMonth.value;
    if (year === currentDate.year && targetMonth >= currentDate.month) {
        targetMonth = Math.max(1, currentDate.month - 1);
    }
    placeholder.value = new CalendarDate(year, targetMonth, 1);
    isYearOpen.value = false;
}

function formatMonthYear(dateValue?: DateValue): string {
    if (!dateValue) return '';
    return new Intl.DateTimeFormat('en-US', { month: 'long', year: 'numeric' }).format(
        dateValue.toDate(getLocalTimeZone()),
    );
}

const presetOptions = [
    {
        label: 'Today',
        getValue: () => ({ start: currentDate, end: currentDate }),
    },
    {
        label: 'Yesterday',
        getValue: () => {
            const yesterday = currentDate.subtract({ days: 1 });
            return { start: yesterday, end: yesterday };
        },
    },
    {
        label: 'Last 7 days',
        getValue: () => ({
            start: currentDate.subtract({ days: 6 }),
            end: currentDate,
        }),
    },
    {
        label: 'Last 30 days',
        getValue: () => ({
            start: currentDate.subtract({ days: 29 }),
            end: currentDate,
        }),
    },
    {
        label: 'This month',
        getValue: () => ({
            start: startOfMonth(currentDate),
            end: currentDate,
        }),
    },
    {
        label: 'Last month',
        getValue: () => {
            const prevMonth = currentDate.subtract({ months: 1 });
            return {
                start: startOfMonth(prevMonth),
                end: endOfMonth(prevMonth),
            };
        },
    },
    {
        label: 'This year',
        getValue: () => ({
            start: startOfYear(currentDate),
            end: currentDate,
        }),
    },
];

function isPresetActive(preset: (typeof presetOptions)[number]): boolean {
    if (!model.value?.start || !model.value?.end) return false;
    const range = preset.getValue();
    return isEqualDay(model.value.start, range.start) && isEqualDay(model.value.end, range.end);
}

function applyPreset(preset: (typeof presetOptions)[number]): void {
    const range = preset.getValue();
    model.value = range;
    if (range.start.year === currentDate.year && range.start.month === currentDate.month) {
        placeholder.value = currentDate.subtract({ months: 1 });
    } else {
        placeholder.value = range.start;
    }
    open.value = false;
}

function formatDate(value?: DateValue): string {
    return value?.toDate(getLocalTimeZone()).toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    }) ?? '';
}

function daySpan(start?: DateValue, end?: DateValue): number {
    if (!start || !end) return 0;
    const s = start.toDate(getLocalTimeZone()).getTime();
    const e = end.toDate(getLocalTimeZone()).getTime();
    return Math.max(1, Math.round(Math.abs(e - s) / (1000 * 60 * 60 * 24)) + 1);
}

function dateLabel(range?: DateRange): string {
    if (!range?.start) return props.placeholder;
    if (!range.end || isEqualDay(range.start, range.end)) {
        return formatDate(range.start);
    }
    return `${formatDate(range.start)} – ${formatDate(range.end)}`;
}

function applySelection(): void {
    if (model.value?.start && !model.value?.end) {
        model.value = {
            start: model.value.start,
            end: model.value.start,
        };
    }
    open.value = false;
}

function clearRange(): void {
    model.value = undefined;
    placeholder.value = currentDate.subtract({ months: 1 });
    open.value = false;
}
</script>

<template>
    <DateRangePickerRoot
        v-model="pickerModel"
        v-model:open="open"
        v-model:placeholder="pickerPlaceholder"
        :number-of-months="2"
        :max-value="currentDate"
        :close-on-select="false"
        locale="en-US"
    >
        <div class="relative flex items-center">
            <DateRangePickerTrigger as-child>
                <button
                    type="button"
                    :class="[
                        'border-input bg-background hover:bg-accent/40 hover:text-accent-foreground flex h-9 w-full items-center justify-between gap-2 rounded-md border px-3 text-left text-sm font-normal shadow-xs outline-none transition-colors focus-visible:ring-2 focus-visible:ring-ring/50',
                        props.class
                    ]"
                >
                    <div class="flex items-center gap-2 truncate min-w-0">
                        <CalendarDays class="text-muted-foreground size-4 shrink-0" />
                        <span class="truncate" :class="!model?.start && 'text-muted-foreground'">{{ dateLabel(model) }}</span>
                    </div>
                    <div class="flex items-center gap-1 shrink-0 ml-2">
                        <span v-if="model?.start" class="size-4" />
                        <ChevronDown class="text-muted-foreground size-3.5 transition-transform duration-200" :class="{ 'rotate-180': open }" />
                    </div>
                </button>
            </DateRangePickerTrigger>

            <button
                v-if="model?.start"
                type="button"
                class="absolute right-7 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground size-5 rounded-xs p-1 hover:bg-accent inline-flex items-center justify-center transition-colors cursor-pointer z-10"
                title="Clear date range"
                @click.stop.prevent="clearRange"
            >
                <X class="size-3" />
            </button>
        </div>

        <DateRangePickerContent
            align="end"
            :side-offset="6"
            :collision-padding="12"
            class="bg-popover text-popover-foreground z-50 rounded-xl border border-border shadow-2xl outline-none p-0 overflow-hidden"
        >
            <div class="flex flex-col md:flex-row">
                <!-- Presets Sidebar -->
                <div class="border-b md:border-b-0 md:border-r border-border p-3 flex md:flex-col gap-1 w-full md:w-36 shrink-0 bg-muted/20 overflow-x-auto">
                    <div class="text-[11px] font-semibold text-muted-foreground uppercase tracking-wider px-2 py-1 hidden md:block">
                        Quick ranges
                    </div>
                    <button
                        v-for="preset in presetOptions"
                        :key="preset.label"
                        type="button"
                        class="flex items-center justify-between whitespace-nowrap rounded-md px-2.5 py-1.5 text-xs font-medium transition-colors text-left"
                        :class="[
                            isPresetActive(preset)
                                ? 'bg-primary/10 text-primary font-semibold'
                                : 'text-muted-foreground hover:bg-accent hover:text-foreground'
                        ]"
                        @click="applyPreset(preset)"
                    >
                        <span>{{ preset.label }}</span>
                        <Check v-if="isPresetActive(preset)" class="size-3.5 text-primary shrink-0 ml-1.5 hidden md:block" />
                    </button>
                </div>

                <!-- Calendar Content -->
                <div class="p-3 flex flex-col">
                    <!-- Date Selection Summary Bar -->
                    <div class="mb-2 flex items-center justify-between rounded-lg border border-border/80 bg-muted/40 px-3 py-1.5 text-xs">
                        <div class="flex items-center gap-2">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">From</span>
                            <span class="rounded border border-border/60 bg-background/90 px-2 py-0.5 font-semibold text-foreground shadow-2xs">
                                {{ model?.start ? formatDate(model.start) : 'Pick start date' }}
                            </span>
                        </div>
                        <span class="text-xs text-muted-foreground/60">→</span>
                        <div class="flex items-center gap-2">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">To</span>
                            <span
                                class="rounded border px-2 py-0.5 font-semibold shadow-2xs transition-colors"
                                :class="[
                                    model?.end
                                        ? 'border-border/60 bg-background/90 text-foreground'
                                        : model?.start
                                            ? 'border-primary/40 bg-primary/10 text-primary font-bold animate-pulse'
                                            : 'border-transparent bg-transparent text-muted-foreground/50 italic'
                                ]"
                            >
                                {{ model?.end ? formatDate(model.end) : (model?.start ? 'Pick end date...' : 'Pick end date') }}
                            </span>
                        </div>
                        <span
                            v-if="model?.start && model?.end"
                            class="hidden sm:inline-flex items-center rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-semibold text-primary"
                        >
                            {{ isEqualDay(model.start, model.end) ? '1 day' : `${daySpan(model.start, model.end)} days` }}
                        </span>
                    </div>

                    <DateRangePickerCalendar v-slot="{ grid, weekDays }">
                        <div class="flex flex-col sm:flex-row gap-5">
                            <div v-for="(month, monthIndex) in grid" :key="month.value.toString()" class="flex flex-col">
                                <!-- Month Header -->
                                <div class="flex items-center justify-between h-8 mb-1.5">
                                    <template v-if="monthIndex === 0">
                                        <DateRangePickerPrev class="inline-flex size-8 items-center justify-center rounded-md border border-input bg-background/80 hover:bg-accent hover:text-accent-foreground transition-colors shadow-2xs">
                                            <ChevronLeft class="size-4" />
                                        </DateRangePickerPrev>

                                        <!-- Custom Month & Year Selectors Container -->
                                        <div ref="selectorContainerRef" class="relative flex items-center gap-1.5">
                                            <!-- Month Selector Button -->
                                            <button
                                                type="button"
                                                class="inline-flex h-8 items-center gap-1.5 rounded-md border border-input bg-background/80 hover:bg-accent hover:text-accent-foreground px-2.5 text-xs font-semibold shadow-2xs transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring/50"
                                                :class="{ 'ring-2 ring-ring/50 bg-accent text-accent-foreground': isMonthOpen }"
                                                @click.stop="toggleMonthDropdown"
                                            >
                                                <span>{{ monthOptions.find((m) => m.value === month.value.month)?.label ?? 'Month' }}</span>
                                                <ChevronDown class="size-3.5 text-muted-foreground transition-transform duration-200" :class="{ 'rotate-180': isMonthOpen }" />
                                            </button>

                                            <!-- Year Selector Button -->
                                            <button
                                                type="button"
                                                class="inline-flex h-8 items-center gap-1.5 rounded-md border border-input bg-background/80 hover:bg-accent hover:text-accent-foreground px-2.5 text-xs font-semibold shadow-2xs transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring/50"
                                                :class="{ 'ring-2 ring-ring/50 bg-accent text-accent-foreground': isYearOpen }"
                                                @click.stop="toggleYearDropdown"
                                            >
                                                <span>{{ month.value.year }}</span>
                                                <ChevronDown class="size-3.5 text-muted-foreground transition-transform duration-200" :class="{ 'rotate-180': isYearOpen }" />
                                            </button>

                                            <!-- Month Dropdown Panel (3x4 Grid) -->
                                            <div
                                                v-if="isMonthOpen"
                                                class="absolute left-0 top-full mt-1.5 z-50 w-64 rounded-xl border border-border bg-popover/95 backdrop-blur-md text-popover-foreground p-2.5 shadow-2xl animate-in fade-in-0 zoom-in-95 duration-150"
                                                @click.stop
                                            >
                                                <div class="px-2 py-1 text-[11px] font-semibold text-muted-foreground uppercase tracking-wider border-b border-border/50 mb-1.5 flex items-center justify-between">
                                                    <span>Select Month</span>
                                                    <span class="text-foreground/80 font-normal">{{ month.value.year }}</span>
                                                </div>
                                                <div class="grid grid-cols-3 gap-1.5">
                                                    <button
                                                        v-for="m in monthOptions"
                                                        :key="m.value"
                                                        type="button"
                                                        class="flex h-8 items-center justify-center rounded-md text-xs font-medium transition-all"
                                                        :class="[
                                                            m.value === month.value.month
                                                                ? 'bg-primary text-primary-foreground font-semibold shadow-2xs'
                                                                : isMonthFuture(m.value, month.value.year)
                                                                    ? 'text-muted-foreground/30 cursor-not-allowed pointer-events-none'
                                                                    : 'text-foreground hover:bg-accent hover:text-accent-foreground'
                                                        ]"
                                                        :disabled="isMonthFuture(m.value, month.value.year)"
                                                        @click="selectMonth(m.value, month.value)"
                                                    >
                                                        {{ m.shortLabel }}
                                                    </button>
                                                </div>
                                            </div>

                                            <!-- Year Dropdown Panel -->
                                            <div
                                                v-if="isYearOpen"
                                                class="absolute right-0 top-full mt-1.5 z-50 w-36 rounded-xl border border-border bg-popover/95 backdrop-blur-md text-popover-foreground p-1.5 shadow-2xl animate-in fade-in-0 zoom-in-95 duration-150"
                                                @click.stop
                                            >
                                                <div class="px-2 py-1 text-[11px] font-semibold text-muted-foreground uppercase tracking-wider border-b border-border/50 mb-1">
                                                    Select Year
                                                </div>
                                                <div class="max-h-52 overflow-y-auto space-y-0.5 p-0.5">
                                                    <button
                                                        v-for="yr in yearOptions"
                                                        :key="yr"
                                                        :ref="(el) => { if (yr === month.value.year) activeYearEl = el as HTMLElement }"
                                                        type="button"
                                                        class="flex w-full items-center justify-between rounded-md px-2.5 py-1.5 text-xs font-medium transition-colors"
                                                        :class="[
                                                            yr === month.value.year
                                                                ? 'bg-primary text-primary-foreground font-semibold shadow-2xs'
                                                                : 'text-foreground hover:bg-accent hover:text-accent-foreground'
                                                        ]"
                                                        @click="selectYear(yr, month.value)"
                                                    >
                                                        <span>{{ yr }}</span>
                                                        <Check v-if="yr === month.value.year" class="size-3.5" />
                                                    </button>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- If single month view, show Next here -->
                                        <DateRangePickerNext v-if="grid.length === 1" class="inline-flex size-8 items-center justify-center rounded-md border border-input bg-background/80 hover:bg-accent hover:text-accent-foreground transition-colors shadow-2xs">
                                            <ChevronRight class="size-4" />
                                        </DateRangePickerNext>
                                        <div v-else class="size-8" />
                                    </template>

                                    <!-- Month 2 Header -->
                                    <template v-else>
                                        <div class="size-8" />
                                        <div class="text-sm font-semibold tracking-tight text-foreground select-none">
                                            {{ formatMonthYear(month.value) }}
                                        </div>
                                        <DateRangePickerNext class="inline-flex size-8 items-center justify-center rounded-md border border-input bg-background/80 hover:bg-accent hover:text-accent-foreground transition-colors shadow-2xs">
                                            <ChevronRight class="size-4" />
                                        </DateRangePickerNext>
                                    </template>
                                </div>

                                <!-- Grid for month -->
                                <DateRangePickerGrid :value="month.value" class="w-full select-none sm:w-64">
                                    <DateRangePickerGridHead>
                                        <DateRangePickerGridRow class="mb-1 grid grid-cols-7">
                                            <DateRangePickerHeadCell v-for="day in weekDays" :key="day" class="text-muted-foreground text-center text-[0.8rem] font-medium py-0.5">
                                                {{ day.slice(0, 2) }}
                                            </DateRangePickerHeadCell>
                                        </DateRangePickerGridRow>
                                    </DateRangePickerGridHead>
                                    <DateRangePickerGridBody>
                                        <DateRangePickerGridRow v-for="(weekDates, index) in month.rows" :key="`week-${index}`" class="grid grid-cols-7 my-0.5">
                                            <DateRangePickerCell
                                                v-for="weekDate in weekDates"
                                                :key="weekDate.toString()"
                                                :date="weekDate"
                                                class="relative p-0 text-center text-sm
                                                    [&:has([data-selected]:not([data-outside-view]))]:bg-primary/15
                                                    [&:has([data-outside-view])]:bg-transparent
                                                    first:[&:has([data-selected]:not([data-outside-view]))]:rounded-l-md
                                                    last:[&:has([data-selected]:not([data-outside-view]))]:rounded-r-md
                                                    [&:has([data-selection-start]:not([data-outside-view]))]:rounded-l-md
                                                    [&:has([data-selection-end]:not([data-outside-view]))]:rounded-r-md
                                                    [&:has([data-selection-start][data-selection-end]:not([data-outside-view]))]:rounded-md"
                                            >
                                                <DateRangePickerCellTrigger
                                                    :day="weekDate"
                                                    :month="month.value"
                                                    class="inline-flex h-8 w-full items-center justify-center font-normal text-sm outline-none transition-colors rounded-none
                                                        hover:bg-accent/80 hover:text-accent-foreground
                                                        focus-visible:ring-2 focus-visible:ring-ring/50
                                                        data-[today]:border data-[today]:border-primary/40 data-[today]:font-semibold data-[today]:rounded-md
                                                        data-[selection-start]:!bg-primary data-[selection-start]:!text-primary-foreground data-[selection-start]:font-semibold data-[selection-start]:!rounded-md
                                                        data-[selection-end]:!bg-primary data-[selection-end]:!text-primary-foreground data-[selection-end]:font-semibold data-[selection-end]:!rounded-md
                                                        data-[outside-view]:invisible data-[outside-view]:pointer-events-none
                                                        data-[disabled]:text-muted-foreground/20 data-[disabled]:pointer-events-none"
                                                />
                                            </DateRangePickerCell>
                                        </DateRangePickerGridRow>
                                    </DateRangePickerGridBody>
                                </DateRangePickerGrid>
                            </div>
                        </div>
                    </DateRangePickerCalendar>

                    <!-- Footer Bar -->
                    <div class="border-border mt-2.5 flex items-center justify-between gap-3 border-t pt-2.5">
                        <div class="flex items-center gap-1.5 text-xs text-muted-foreground truncate">
                            <CalendarDays class="size-3.5 shrink-0" />
                            <span class="truncate font-medium text-foreground">
                                {{ model?.start ? dateLabel(model) : 'Select a date or range' }}
                            </span>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <button
                                v-if="model?.start"
                                type="button"
                                class="hover:bg-accent text-muted-foreground hover:text-foreground rounded-md px-2.5 py-1.5 text-xs font-medium transition-colors"
                                @click="clearRange"
                            >
                                Reset
                            </button>
                            <button
                                type="button"
                                class="bg-primary text-primary-foreground hover:bg-primary/90 rounded-md px-3 py-1.5 text-xs font-semibold shadow-2xs transition-colors"
                                @click="applySelection"
                            >
                                Apply
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </DateRangePickerContent>
    </DateRangePickerRoot>
</template>

<style scoped>
:deep([data-outside-view]) {
    visibility: hidden !important;
    pointer-events: none !important;
}

:deep([role="gridcell"]:has([data-selected]:not([data-outside-view]))) {
    background-color: color-mix(in srgb, var(--primary) 15%, transparent);
}

:deep([role="gridcell"]:has([data-outside-view])) {
    background-color: transparent !important;
}

:deep([role="gridcell"]:first-child:has([data-selected]:not([data-outside-view]))),
:deep([role="gridcell"]:has([data-selection-start]:not([data-outside-view]))) {
    border-top-left-radius: 0.375rem;
    border-bottom-left-radius: 0.375rem;
}

:deep([role="gridcell"]:last-child:has([data-selected]:not([data-outside-view]))),
:deep([role="gridcell"]:has([data-selection-end]:not([data-outside-view]))) {
    border-top-right-radius: 0.375rem;
    border-bottom-right-radius: 0.375rem;
}

:deep([role="gridcell"]:has([data-selection-start][data-selection-end]:not([data-outside-view]))) {
    border-radius: 0.375rem;
}

:deep([data-reka-calendar-cell-trigger]) {
    border-radius: 0;
    background-color: transparent;
    color: var(--foreground);
}

:deep([data-reka-calendar-cell-trigger]:hover:not([data-selection-start]):not([data-selection-end])) {
    background-color: color-mix(in srgb, var(--primary) 22%, transparent);
    color: var(--foreground);
}

:deep([data-reka-calendar-cell-trigger][data-selection-start]),
:deep([data-reka-calendar-cell-trigger][data-selection-end]) {
    background-color: var(--primary) !important;
    color: var(--primary-foreground) !important;
    border-radius: 0.375rem !important;
    font-weight: 600;
}

:deep([data-reka-calendar-cell-trigger][data-today]:not([data-selection-start]):not([data-selection-end])) {
    border: 1px solid color-mix(in srgb, var(--primary) 40%, transparent);
    border-radius: 0.375rem;
}
</style>
