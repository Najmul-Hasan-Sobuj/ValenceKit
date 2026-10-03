<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import {
    CalendarDate,
    getLocalTimeZone,
    parseDate,
    today,
} from '@internationalized/date';
import {
    DatePickerCalendar,
    DatePickerCell,
    DatePickerCellTrigger,
    DatePickerContent,
    DatePickerGrid,
    DatePickerGridBody,
    DatePickerGridHead,
    DatePickerGridRow,
    DatePickerHeadCell,
    DatePickerHeader,
    DatePickerHeading,
    DatePickerNext,
    DatePickerPrev,
    DatePickerRoot,
    DatePickerTrigger,
} from 'reka-ui';
import type { DateValue } from '@internationalized/date';
import { Calendar as CalendarIcon, ChevronDown, ChevronLeft, ChevronRight } from '@lucide/vue';

interface Props {
    modelValue?: string;
    placeholder?: string;
    disabled?: boolean;
    class?: string;
}

const props = withDefaults(defineProps<Props>(), {
    modelValue: '',
    placeholder: 'Pick a date',
    disabled: false,
    class: '',
});

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
}>();

const open = ref(false);

const internalDate = ref<DateValue | undefined>(
    props.modelValue ? parseDateSafe(props.modelValue) : undefined
);

function parseDateSafe(val: string): DateValue | undefined {
    try {
        if (!val) return undefined;
        // Handles "YYYY-MM-DD" or ISO strings
        const datePart = val.split('T')[0];
        return parseDate(datePart);
    } catch {
        return undefined;
    }
}

watch(
    () => props.modelValue,
    (newVal) => {
        const parsed = parseDateSafe(newVal);
        if (parsed?.toString() !== internalDate.value?.toString()) {
            internalDate.value = parsed;
        }
    }
);

watch(internalDate, (newVal) => {
    if (newVal) {
        emit('update:modelValue', newVal.toString());
    } else {
        emit('update:modelValue', '');
    }
});

const pickerDate = computed<any>({
    get: () => internalDate.value,
    set: (value: DateValue | undefined) => {
        internalDate.value = value;
    },
});

const formattedLabel = computed(() => {
    if (!internalDate.value) return props.placeholder;
    try {
        const d = new Date(
            internalDate.value.year,
            internalDate.value.month - 1,
            internalDate.value.day
        );
        return d.toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
        });
    } catch {
        return internalDate.value.toString();
    }
});
</script>

<template>
    <DatePickerRoot
        v-model="pickerDate"
        v-model:open="open"
        :disabled="disabled"
        :close-on-select="true"
        locale="en-US"
    >
        <DatePickerTrigger as-child>
            <button
                type="button"
                :disabled="disabled"
                :class="[
                    'flex h-8 w-full items-center justify-between gap-2 rounded-lg border border-input bg-background/50 hover:bg-accent/40 px-2.5 text-xs font-normal shadow-2xs outline-none transition-colors focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50',
                    props.class,
                ]"
            >
                <div class="flex items-center gap-2 truncate">
                    <CalendarIcon class="size-3.5 text-muted-foreground shrink-0" />
                    <span :class="!internalDate ? 'text-muted-foreground' : 'text-foreground font-medium'">
                        {{ formattedLabel }}
                    </span>
                </div>
                <ChevronDown class="size-3 text-muted-foreground shrink-0 transition-transform duration-200" :class="{ 'rotate-180': open }" />
            </button>
        </DatePickerTrigger>

        <DatePickerContent
            class="z-50 w-auto rounded-xl border border-border bg-popover/95 backdrop-blur-md p-2.5 text-popover-foreground shadow-xl animate-in fade-in-0 zoom-in-95 duration-150"
            side="bottom"
            align="start"
        >
            <DatePickerCalendar v-slot="{ grid, weekDays }">
                <DatePickerHeader class="flex items-center justify-between pb-2 px-1">
                    <DatePickerPrev class="flex size-6 items-center justify-center rounded-md text-muted-foreground hover:bg-muted hover:text-foreground transition-colors cursor-pointer">
                        <ChevronLeft class="size-3.5" />
                    </DatePickerPrev>
                    <DatePickerHeading class="text-xs font-semibold text-foreground tracking-tight" />
                    <DatePickerNext class="flex size-6 items-center justify-center rounded-md text-muted-foreground hover:bg-muted hover:text-foreground transition-colors cursor-pointer">
                        <ChevronRight class="size-3.5" />
                    </DatePickerNext>
                </DatePickerHeader>

                <DatePickerGrid
                    v-for="month in grid"
                    :key="month.value.toString()"
                    :value="month.value"
                    class="w-full border-collapse select-none"
                >
                    <DatePickerGridHead>
                        <DatePickerGridRow class="mb-1 flex w-full justify-between">
                            <DatePickerHeadCell
                                v-for="day in weekDays"
                                :key="day"
                                class="w-7 text-[10px] font-semibold text-muted-foreground text-center uppercase"
                            >
                                {{ day.slice(0, 2) }}
                            </DatePickerHeadCell>
                        </DatePickerGridRow>
                    </DatePickerGridHead>

                    <DatePickerGridBody>
                        <DatePickerGridRow
                            v-for="(row, idx) in month.rows"
                            :key="idx"
                            class="flex w-full justify-between mt-0.5"
                        >
                            <DatePickerCell
                                v-for="cell in row"
                                :key="cell.toString()"
                                :date="cell"
                                class="relative p-0 text-center text-xs"
                            >
                                <DatePickerCellTrigger
                                    :day="cell"
                                    :month="month.value"
                                    class="flex size-7 items-center justify-center rounded-md text-xs transition-colors hover:bg-accent hover:text-accent-foreground data-[today]:border data-[today]:border-primary/40 data-[today]:font-semibold data-[selected]:bg-primary data-[selected]:!text-primary-foreground data-[selected]:font-semibold data-[unavailable]:text-muted-foreground/30 data-[disabled]:text-muted-foreground/30 data-[outside-view]:text-muted-foreground/40 cursor-pointer"
                                />
                            </DatePickerCell>
                        </DatePickerGridRow>
                    </DatePickerGridBody>
                </DatePickerGrid>
            </DatePickerCalendar>
        </DatePickerContent>
    </DatePickerRoot>
</template>
