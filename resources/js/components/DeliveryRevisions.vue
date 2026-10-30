<script setup lang="ts">
import { computed } from "vue";
import {
    dateTime,
    deliveryIssueLabel,
    money,
    statusNames,
} from "../lib/format";
import type { DeliveryDetail } from "../types";
import DeliveryCostBreakdown from "./DeliveryCostBreakdown.vue";

const props = defineProps<{
    revisions: DeliveryDetail["revisions"];
    timezone: string;
}>();
const items = computed(() =>
    props.revisions.map((revision, index) => ({
        value: String(revision.id),
        label: `Revisión ${props.revisions.length - index} · ${dateTime(revision.created_at, revision.snapshot.calculation?.timezone ?? props.timezone)}`,
        revision,
    })),
);
</script>

<template>
    <section
        class="border-t border-default pt-4 mt-5"
        aria-labelledby="revisions-title"
    >
        <h3 id="revisions-title" class="font-semibold text-sm">
            Revisiones conservadas ({{ revisions.length }})
        </h3>
        <p class="text-xs text-muted mt-1 mb-3">
            Historial de estados y costos, de la revisión más reciente a la más
            antigua.
        </p>
        <UAccordion
            v-if="items.length"
            :items="items"
            type="multiple"
            :ui="{
                label: 'text-left whitespace-normal',
                body: 'space-y-4 pb-5',
            }"
        >
            <template #body="{ item }">
                <p class="text-sm font-medium">
                    {{
                        item.revision.reason === "conflict_detected"
                            ? "Se detectaron eventos en conflicto."
                            : item.revision.reason === "event_received"
                              ? "Se actualizó la entrega al recibir un evento."
                              : "Se actualizó el historial de la entrega."
                    }}
                </p>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                    <div>
                        <dt class="text-xs text-muted">
                            Estado en esta revisión
                        </dt>
                        <dd class="mt-1">
                            {{
                                statusNames[
                                    item.revision.snapshot.status ?? ""
                                ] ?? "Sin estado confirmado"
                            }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted">
                            Consistencia del historial
                        </dt>
                        <dd class="mt-1">
                            <UBadge
                                :color="
                                    item.revision.snapshot.quality ===
                                    'consistent'
                                        ? 'neutral'
                                        : 'warning'
                                "
                                variant="subtle"
                                >{{
                                    item.revision.snapshot.quality ===
                                    "conflict"
                                        ? "En conflicto"
                                        : item.revision.snapshot.quality ===
                                            "pending"
                                          ? "Faltan eventos"
                                          : "Secuencia válida"
                                }}</UBadge
                            >
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted">Dirección</dt>
                        <dd class="mt-1 [overflow-wrap:anywhere]">
                            {{
                                item.revision.snapshot.delivery_address ??
                                "Pendiente de recibir"
                            }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted">
                            Costo registrado · COP
                        </dt>
                        <dd class="font-semibold mt-1">
                            {{ money(item.revision.snapshot.cost_minor) }}
                        </dd>
                    </div>
                    <div v-if="item.revision.snapshot.created_occurred_at">
                        <dt class="text-xs text-muted">
                            Creación de la entrega
                        </dt>
                        <dd class="mt-1">
                            {{
                                dateTime(
                                    item.revision.snapshot.created_occurred_at,
                                    item.revision.snapshot.calculation
                                        ?.timezone ?? timezone,
                                )
                            }}
                        </dd>
                    </div>
                    <div v-if="item.revision.snapshot.completed_at">
                        <dt class="text-xs text-muted">
                            Finalización de la entrega
                        </dt>
                        <dd class="mt-1">
                            {{
                                dateTime(
                                    item.revision.snapshot.completed_at,
                                    item.revision.snapshot.calculation
                                        ?.timezone ?? timezone,
                                )
                            }}
                        </dd>
                    </div>
                </dl>
                <dl
                    class="space-y-2 text-xs"
                    v-if="
                        item.revision.snapshot.planned_pickup ||
                        item.revision.snapshot.planned_destination
                    "
                >
                    <div>
                        <dt class="text-muted">Origen previsto</dt>
                        <dd>
                            {{
                                item.revision.snapshot.planned_pickup?.label ??
                                "Por recibir"
                            }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-muted">Destino previsto</dt>
                        <dd>
                            {{
                                item.revision.snapshot.planned_destination
                                    ?.label ?? "Por recibir"
                            }}
                        </dd>
                    </div>
                </dl>
                <ul
                    v-if="item.revision.snapshot.issues.length"
                    class="space-y-1 text-sm text-muted list-disc pl-5"
                    aria-label="Incidencias de esta revisión"
                >
                    <li
                        v-for="issue in item.revision.snapshot.issues"
                        :key="issue"
                    >
                        {{ deliveryIssueLabel(issue) }}
                    </li>
                </ul>
                <DeliveryCostBreakdown
                    v-if="item.revision.snapshot.calculation"
                    :calculation="item.revision.snapshot.calculation"
                />
                <p v-else class="text-sm text-muted">
                    El costo aún no estaba confirmado en esta revisión.
                </p>
                <details
                    v-if="item.revision.snapshot.source_events.length"
                    class="text-xs"
                >
                    <summary class="cursor-pointer text-primary">
                        Eventos que respaldan esta revisión ({{
                            item.revision.snapshot.source_events.length
                        }})
                    </summary>
                    <ul
                        class="space-y-1 mt-2 text-muted"
                        aria-label="Referencias de eventos"
                    >
                        <li
                            v-for="id in item.revision.snapshot.source_events"
                            :key="id"
                            class="font-mono break-all"
                        >
                            {{ id }}
                        </li>
                    </ul>
                </details>
            </template>
        </UAccordion>
        <p v-else class="text-sm text-muted">No hay revisiones registradas.</p>
    </section>
</template>
