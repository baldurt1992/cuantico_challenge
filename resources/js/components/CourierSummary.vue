<script setup lang="ts">
import { computed } from "vue";
import { statusNames } from "../lib/format";
import type { Courier, OperationsData } from "../types";

const props = defineProps<{
    couriers: Courier[];
    counts: OperationsData["courier_counts"];
    loading: boolean;
}>();
const courier = defineModel<number>();
const skeletonCount = computed(
    () => Object.keys(props.counts?.counts ?? {}).length || 3,
);
</script>

<template>
    <UCard as="section" class="mt-5" aria-labelledby="courier-summary-title">
        <div
            class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4"
        >
            <div>
                <h2 id="courier-summary-title" class="font-semibold">
                    Estados por mensajero
                </h2>
                <p id="courier-summary-help" class="text-sm text-muted mt-1">
                    Estado actual de las entregas creadas en el período
                    seleccionado.
                </p>
            </div>
            <div class="flex items-end gap-2">
                <UFormField label="Mensajero" name="courier_id" class="flex-1">
                    <UInputMenu
                        :model-value="courier"
                        @update:model-value="courier = $event ?? undefined"
                        value-key="value"
                        open-on-focus
                        open-on-click
                        :clear="{ 'aria-label': 'Limpiar mensajero' }"
                        name="courier_id"
                        :items="
                            couriers.map((item) => ({
                                label: item.name,
                                value: item.id,
                            }))
                        "
                        placeholder="Seleccionar mensajero"
                        aria-describedby="courier-summary-help"
                        class="w-full sm:w-64"
                    />
                </UFormField>
            </div>
        </div>
        <div
            class="mt-4"
            :class="{ 'min-h-28': courier }"
            :aria-busy="loading && !!courier"
            aria-live="polite"
            aria-atomic="true"
        >
            <p v-if="!courier" class="text-sm text-muted">
                Elige un mensajero para ver la distribución de sus entregas por
                estado.
            </p>
            <div
                v-else-if="loading"
                role="status"
                aria-label="Consultando estados por mensajero"
            >
                <span class="sr-only">Consultando estados…</span>
                <div aria-hidden="true">
                    <USkeleton
                        class="h-4 w-64 max-w-full mb-3 motion-reduce:animate-none"
                    />
                    <div class="flex flex-wrap gap-3">
                        <div
                            v-for="index in skeletonCount"
                            :key="index"
                            class="rounded-lg bg-elevated/50 px-4 py-3 min-w-28"
                        >
                            <USkeleton
                                class="h-4 w-20 motion-reduce:animate-none"
                            />
                            <USkeleton
                                class="h-7 w-8 mt-1 motion-reduce:animate-none"
                            />
                        </div>
                    </div>
                </div>
            </div>
            <template v-else-if="counts">
                <p class="text-xs text-muted mb-3">
                    {{ counts.courier_name }} · creadas desde
                    {{ counts.period.start_date }} hasta
                    {{ counts.period.end_date_exclusive }} (fin excluido)
                </p>
                <dl
                    v-if="Object.keys(counts.counts).length"
                    class="flex flex-wrap gap-3"
                >
                    <div
                        v-for="(count, status) in counts.counts"
                        :key="status"
                        class="rounded-lg bg-elevated/50 px-4 py-3 min-w-28"
                    >
                        <dt class="text-xs text-muted">
                            {{ statusNames[status] ?? status }}
                        </dt>
                        <dd class="text-lg font-semibold mt-1">{{ count }}</dd>
                    </div>
                </dl>
                <p v-else class="text-sm text-muted">
                    Este mensajero no tiene entregas creadas en el período
                    seleccionado.
                </p>
            </template>
        </div>
    </UCard>
</template>
