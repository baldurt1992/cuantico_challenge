<script setup lang="ts">
import { computed, reactive, shallowRef } from "vue";
import { ApiError, requestJson } from "../lib/api";
import { statusNames } from "../lib/format";
import {
    deliveryActions,
    eventActions,
    type EventType,
} from "../lib/deliveryWorkflow";
import type { Courier, Delivery } from "../types";
import DatePicker from "./DatePicker.vue";
import LocationPicker from "./LocationPicker.vue";
import type { LocationPoint } from "../lib/demoLocations";
import DeliveryCostBreakdown from "./DeliveryCostBreakdown.vue";
const props = defineProps<{
    merchantId: number;
    merchantName: string;
    timezone: string;
    couriers: Courier[];
    delivery?: Delivery;
    type: EventType;
}>();
const emit = defineEmits<{
    saved: [delivery: Delivery];
    proceed: [delivery: Delivery, type: EventType];
    close: [];
}>();
const deliveryId = props.delivery?.id ?? crypto.randomUUID();
const eventId = crypto.randomUUID();
const form = reactive({
    occurred_at: new Date(Math.floor(Date.now() / 1000) * 1000).toISOString(),
    courier: undefined as number | undefined,
});
const location = shallowRef<LocationPoint | null>(null);
const plannedPickup = shallowRef<LocationPoint | null>(null);
const plannedDestination = shallowRef<LocationPoint | null>(null);
const loading = shallowRef(false);
const error = shallowRef("");
const result = shallowRef<Delivery | null>(null);
const duplicate = shallowRef(false);
const payload = shallowRef<Record<string, unknown> | null>(null);
const locked = computed(() => loading.value || payload.value !== null);
const needsCoordinates = computed(() =>
    ["picked_up", "delivered"].includes(props.type),
);
const nextActions = computed(() =>
    result.value ? deliveryActions(result.value) : [],
);
async function submit() {
    if (loading.value || result.value) return;
    error.value = "";
    if (!payload.value) {
        if (
            !form.occurred_at ||
            (props.type === "created" &&
                (!plannedPickup.value || !plannedDestination.value)) ||
            (props.type === "assigned" && !form.courier) ||
            (needsCoordinates.value && !location.value)
        ) {
            error.value =
                "Completa los datos necesarios para registrar esta acción.";
            return;
        }
        const data =
            props.type === "created"
                ? {
                      planned_pickup: plannedPickup.value,
                      planned_destination: plannedDestination.value,
                  }
                : props.type === "assigned"
                  ? { courier_id: form.courier }
                  : needsCoordinates.value
                    ? {
                          latitude: location.value!.latitude,
                          longitude: location.value!.longitude,
                      }
                    : {};
        payload.value = {
            event_id: eventId,
            delivery_id: deliveryId,
            merchant_id: props.merchantId,
            type: props.type,
            occurred_at: form.occurred_at,
            data,
        };
    }
    loading.value = true;
    try {
        const response = await requestJson<{
            duplicate: boolean;
            delivery: Delivery;
        }>("/api/v1/events", {
            method: "POST",
            body: JSON.stringify(payload.value),
        });
        result.value = response.delivery;
        duplicate.value = response.duplicate;
        emit("saved", response.delivery);
    } catch (failure) {
        error.value =
            failure instanceof Error
                ? failure.message
                : "No se pudo guardar la acción.";
        if (
            failure instanceof ApiError &&
            failure.status >= 400 &&
            failure.status < 500
        )
            payload.value = null;
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <div class="space-y-5">
        <div
            v-if="type !== 'created'"
            class="rounded-lg bg-elevated/50 p-3 text-sm space-y-1"
        >
            <p class="font-medium">
                {{ delivery?.delivery_address ?? merchantName }}
            </p>
            <p class="text-muted">
                {{
                    delivery
                        ? `Estado actual: ${statusNames[delivery.status ?? ""] ?? "Sin confirmar"}`
                        : "Nueva entrega del comercio seleccionado"
                }}
            </p>
        </div>
        <template v-if="result">
            <UAlert
                color="success"
                :title="duplicate ? 'Acción reconocida' : 'Acción registrada'"
                :description="
                    duplicate
                        ? 'Este envío ya estaba registrado. No se duplicó la entrega ni su costo.'
                        : `Estado: ${statusNames[result.status ?? ''] ?? 'Por revisar'}.`
                "
            />
            <DeliveryCostBreakdown
                v-if="result.calculation"
                :calculation="result.calculation"
            />
            <UAlert
                v-else
                :color="result.quality === 'conflict' ? 'warning' : 'info'"
                :description="
                    result.quality === 'conflict'
                        ? 'Hay eventos contradictorios. Revisa el detalle antes de continuar; el costo no está confirmado.'
                        : 'Costo pendiente. Se calculará al entregar o registrar una falla, cuando estén completos los datos necesarios.'
                "
            />
            <p class="text-xs text-muted">
                Los reportes conservan el período seleccionado. Si finalizaste
                hoy, selecciona un período que incluya hoy para consultar el
                costo.
            </p>
            <div class="flex flex-wrap gap-2">
                <UButton
                    v-for="(action, index) in nextActions"
                    :key="action"
                    :color="action === 'failed' ? 'error' : 'primary'"
                    :variant="index === 0 ? 'solid' : 'outline'"
                    @click="emit('proceed', result, action)"
                    >{{ eventActions[action] }}</UButton
                >
                <UButton
                    color="neutral"
                    variant="outline"
                    @click="emit('close')"
                    >Volver a entregas</UButton
                >
            </div>
        </template>
        <UForm v-else :state="form" class="space-y-4" @submit="submit">
            <template v-if="type === 'created'">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 md:gap-8">
                    <LocationPicker
                        v-model="plannedPickup"
                        kind="picked_up"
                        planning
                        :disabled="locked"
                    />
                    <LocationPicker
                        v-model="plannedDestination"
                        kind="delivered"
                        planning
                        :disabled="locked"
                    />
                </div>
                <UCollapsible class="border-t border-default pt-4">
                    <UButton
                        type="button"
                        color="neutral"
                        variant="link"
                        size="sm"
                        class="p-0"
                        icon="i-lucide-circle-help"
                        trailing-icon="i-lucide-chevron-down"
                        >Ayuda sobre ubicaciones y fechas</UButton
                    >
                    <template #content>
                        <div class="space-y-3 text-sm text-muted pt-3">
                            <p>
                                El catálogo de demostración incluye Museo del
                                Oro, Palacio Liévano y Parque Simón Bolívar, en
                                Bogotá. Busca un nombre o dirección del catálogo
                                y confirma cada punto; no es una búsqueda de
                                cualquier dirección.
                            </p>
                            <p>
                                Son referencias aproximadas para el ejercicio.
                                Puedes confirmarlas sin abrir el mapa o
                                ajustarlas desde «Mapa y coordenadas».
                            </p>
                            <p>
                                El origen y el destino quedarán guardados. Al
                                registrar recogida y entrega confirmarás dónde
                                ocurrió cada acción; el costo usa esas
                                ubicaciones reales.
                            </p>
                            <p>
                                La fecha comienza en el momento actual y se
                                muestra en la zona del comercio. Puedes indicar
                                cuándo ocurrió un hecho anterior; la recepción
                                la registra automáticamente el servidor.
                            </p>
                        </div>
                    </template>
                </UCollapsible>
            </template>
            <UFormField
                v-if="type === 'assigned'"
                label="Mensajero"
                name="courier_id"
                required
            >
                <UInputMenu
                    :model-value="form.courier"
                    @update:model-value="form.courier = $event ?? undefined"
                    value-key="value"
                    open-on-focus
                    open-on-click
                    :clear="{ 'aria-label': 'Limpiar mensajero asignado' }"
                    name="courier_id"
                    :items="
                        couriers.map((c) => ({ label: c.name, value: c.id }))
                    "
                    placeholder="Seleccionar mensajero"
                    :disabled="locked"
                    required
                    class="w-full"
                />
            </UFormField>
            <LocationPicker
                v-if="type === 'picked_up' || type === 'delivered'"
                v-model="location"
                :kind="type"
                :planned="
                    type === 'picked_up'
                        ? delivery?.planned_pickup
                        : delivery?.planned_destination
                "
                :disabled="locked"
            />
            <UAlert
                v-if="delivery?.issues.includes(`missing_${type}`)"
                color="info"
                description="Estás completando un evento faltante. Indica la fecha real en que ocurrió para ubicarlo correctamente en el historial."
            />
            <UAlert
                v-if="type === 'failed'"
                color="warning"
                description="La entrega quedará fallida y finalizará su recorrido. Se cobrará únicamente el 40 % de la tarifa base, sin recargos, cuando estén completos los eventos necesarios."
            />
            <UFormField
                label="Fecha y hora de ocurrencia"
                name="occurred_at"
                :help="
                    type === 'created'
                        ? `Hora local: ${timezone}`
                        : `Hora local del comercio: ${timezone}. Puedes registrar un hecho anterior; la recepción se registra automáticamente en el servidor.`
                "
                required
            >
                <DatePicker
                    v-model="form.occurred_at"
                    name="occurred_at"
                    label="Fecha y hora de ocurrencia"
                    datetime
                    :timezone="timezone"
                    :disabled="locked"
                    required
                />
            </UFormField>
            <UAlert v-if="error" color="error" :description="error" />
            <div
                :class="
                    type === 'created'
                        ? 'sticky bottom-0 flex justify-end border-t border-default bg-default pt-4 pb-1'
                        : ''
                "
            >
                <UButton
                    type="submit"
                    :loading="loading"
                    :disabled="loading"
                    icon="i-lucide-check"
                    >{{
                        payload
                            ? "Reintentar la misma acción"
                            : eventActions[type]
                    }}</UButton
                >
            </div>
        </UForm>
    </div>
</template>
