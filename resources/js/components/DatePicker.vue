<script setup lang="ts">
import { computed, shallowRef } from "vue";
import {
    parseDate,
    parseAbsolute,
    toCalendarDate,
    toZoned,
    now,
    type DateValue,
} from "@internationalized/date";
const props = defineProps<{
    name: string;
    label: string;
    datetime?: boolean;
    timezone?: string;
    disabled?: boolean;
    required?: boolean;
}>();
const model = defineModel<string>({ required: true });
const open = shallowRef(false);
const zone = computed(() => props.timezone ?? "UTC");
const value = computed<DateValue | undefined>({
    get() {
        return model.value
            ? props.datetime
                ? parseAbsolute(model.value, zone.value)
                : parseDate(model.value)
            : undefined;
    },
    set(date) {
        model.value = date
            ? props.datetime
                ? toZoned(date, zone.value).toDate().toISOString()
                : date.toString()
            : "";
    },
});
const calendarDate = computed({
    get: () => (value.value ? toCalendarDate(value.value) : undefined),
    set(date) {
        if (!date) return;
        value.value = props.datetime
            ? (value.value ?? now(zone.value)).set({
                  year: date.year,
                  month: date.month,
                  day: date.day,
              })
            : date;
        open.value = false;
    },
});
</script>

<template>
    <UInputDate
        v-model="value"
        :name="name"
        :aria-label="label"
        locale="es"
        :hour-cycle="24"
        :granularity="datetime ? 'second' : 'day'"
        hide-time-zone
        :disabled="disabled"
        :required="required"
        class="w-fit max-w-full"
    >
        <template #trailing>
            <UPopover v-model:open="open">
                <UButton
                    type="button"
                    icon="i-lucide-calendar"
                    color="neutral"
                    variant="link"
                    size="sm"
                    class="px-0"
                    :aria-label="`Abrir calendario: ${label}`"
                    :disabled="disabled"
                />
                <template #content
                    ><UCalendar v-model="calendarDate" locale="es" class="p-2"
                /></template>
            </UPopover>
        </template>
    </UInputDate>
</template>
