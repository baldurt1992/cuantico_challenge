<script setup lang="ts">
import { shallowRef, watch } from "vue";
import { requestJson } from "../lib/api";
import { dateTime, deliveryIssueLabel, statusNames } from "../lib/format";
import DeliveryCostBreakdown from "./DeliveryCostBreakdown.vue";
import DeliveryRevisions from "./DeliveryRevisions.vue";
import type { DeliveryDetail } from "../types";
const props = defineProps<{
    merchantId: number;
    deliveryId: string;
    timezone: string;
}>();
const detail = shallowRef<DeliveryDetail | null>(null);
const error = shallowRef("");
watch(
    () => [props.merchantId, props.deliveryId],
    async (_, __, onCleanup) => {
        const controller = new AbortController();
        onCleanup(() => controller.abort());
        detail.value = null;
        error.value = "";
        try {
            detail.value = await requestJson<DeliveryDetail>(
                `/api/v1/merchants/${props.merchantId}/deliveries/${props.deliveryId}`,
                { signal: controller.signal },
            );
        } catch (failure) {
            if (failure instanceof Error && failure.name !== "AbortError")
                error.value = failure.message;
        }
    },
    { immediate: true },
);
</script>

<template>
    <div>
        <UAlert v-if="error" color="error" :description="error" class="mt-4" />
        <p v-else-if="!detail" role="status" class="py-6 text-muted">
            Cargando historial…
        </p>
        <template v-else>
            <dl
                class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm my-4"
                aria-label="Recorrido previsto"
            >
                <div>
                    <dt class="text-xs text-muted">Origen previsto</dt>
                    <dd class="font-medium mt-1">
                        {{
                            detail.delivery.planned_pickup?.label ??
                            "Por recibir con la creación"
                        }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-muted">Destino previsto</dt>
                    <dd class="font-medium mt-1">
                        {{
                            detail.delivery.planned_destination?.label ??
                            "Por recibir con la creación"
                        }}
                    </dd>
                </div>
            </dl>
            <p class="text-xs text-muted">
                El costo usa los puntos reales registrados en recogida y
                entrega. Las ubicaciones previstas se conservan como referencia
                del recorrido.
            </p>
            <div v-if="detail.delivery.issues.length" class="mt-4">
                <UBadge color="warning" variant="subtle"
                    >Revisión pendiente</UBadge
                >
                <ul class="text-xs text-muted mt-2 space-y-1">
                    <li v-for="issue in detail.delivery.issues" :key="issue">
                        {{ deliveryIssueLabel(issue) }}
                    </li>
                </ul>
            </div>
            <DeliveryCostBreakdown
                v-if="detail.delivery.calculation"
                :calculation="detail.delivery.calculation"
                class="my-5"
            />
            <ol class="my-5 space-y-3">
                <li
                    v-for="event in detail.events"
                    :key="event.event_id"
                    class="flex gap-3 text-sm"
                >
                    <UIcon
                        name="i-lucide-circle-check"
                        class="size-4 text-primary mt-0.5 shrink-0"
                    />
                    <div>
                        <span class="font-medium">{{
                            statusNames[event.type]
                        }}</span
                        ><span class="text-muted ml-2">{{
                            dateTime(event.occurred_at, timezone)
                        }}</span>
                        <p class="text-xs text-muted mt-1">
                            Recibido {{ dateTime(event.received_at, timezone) }}
                        </p>
                    </div>
                </li>
            </ol>
            <DeliveryRevisions
                :revisions="detail.revisions"
                :timezone="timezone"
            />
        </template>
    </div>
</template>
