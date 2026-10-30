<script setup lang="ts">
import type { OperationsData } from "../types";
import { money } from "../lib/format";
defineProps<{ data: OperationsData | null }>();
</script>

<template>
    <section
        aria-label="Resumen de costos"
        class="border-y border-default py-5 grid grid-cols-2 sm:grid-cols-4 gap-5"
    >
        <div>
            <p class="text-xs text-muted mb-2">Costo confirmado · COP</p>
            <p class="text-xl sm:text-2xl font-semibold tracking-tight">
                {{ data ? money(data.totals.total_minor) : "—" }}
            </p>
        </div>
        <div>
            <p class="text-xs text-muted mb-2">Finalizadas en el período</p>
            <p class="text-xl sm:text-2xl font-semibold">
                {{ data?.totals.deliveries ?? "—" }}
            </p>
            <p v-if="data" class="text-xs text-muted mt-1">
                {{ data.totals.delivered }} entregadas ·
                {{ data.totals.failed }} fallidas
            </p>
        </div>
        <div>
            <p class="text-xs text-muted mb-2">Sin costo confirmado</p>
            <p class="text-xl sm:text-2xl font-semibold">
                {{ data?.issues.pending ?? "—" }}
            </p>
            <p class="text-xs text-muted mt-1">Estado actual del comercio</p>
        </div>
        <div>
            <p class="text-xs text-muted mb-2">Conflictos por revisar</p>
            <p
                class="text-xl sm:text-2xl font-semibold"
                :class="data?.issues.conflict ? 'text-amber-800' : ''"
            >
                {{ data?.issues.conflict ?? "—" }}
            </p>
            <p class="text-xs text-muted mt-1">
                Excluidos del costo confirmado
            </p>
        </div>
    </section>
</template>
