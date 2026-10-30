<script setup lang="ts">
import type { TableColumn } from "@nuxt/ui";
import type { Delivery, DeliveryPage } from "../types";
import {
    deliveryActions,
    eventActions,
    type EventType,
} from "../lib/deliveryWorkflow";
import DeliveryDetail from "./DeliveryDetail.vue";
import { money, statusNames } from "../lib/format";
const props = defineProps<{
    merchantId: number;
    timezone: string;
    page: DeliveryPage | null;
    loading: boolean;
    canManage: boolean;
}>();
const emit = defineEmits<{
    page: [page: number];
    action: [delivery: Delivery, type: EventType];
}>();
const columns: TableColumn<Delivery>[] = [
    { id: "origin", header: "Origen previsto" },
    { accessorKey: "delivery_address", header: "Destino previsto" },
    { accessorKey: "courier_name", header: "Mensajero" },
    { accessorKey: "status", header: "Estado" },
    {
        accessorKey: "cost_minor",
        header: "Costo",
        meta: { class: { th: "text-right", td: "text-right font-medium" } },
    },
    { id: "actions", header: "Acciones" },
];
function changePage(value: number) {
    if (value !== props.page?.page) emit("page", value);
}
</script>

<template>
    <UCard
        :ui="{ body: 'p-0 sm:p-0', footer: 'px-4 py-3 sm:px-4 sm:py-3' }"
        class="overflow-hidden"
    >
        <UTable
            :data="page?.data ?? []"
            :columns="columns"
            :loading="loading"
            :ui="{
                root: 'relative overflow-auto',
                th: 'bg-elevated/50 text-xs',
                td: 'py-4',
            }"
            aria-label="Entregas del comercio seleccionado"
        >
            <template #origin-cell="{ row }"
                ><span class="text-sm">{{
                    row.original.planned_pickup?.label ??
                    "Por recibir con la creación"
                }}</span></template
            >
            <template #delivery_address-cell="{ row }"
                ><p class="font-medium text-default">
                    {{ row.original.delivery_address ?? "Destino pendiente" }}
                </p>
                <p class="font-mono text-xs text-muted mt-1">
                    {{ row.original.id.slice(0, 8) }}
                </p></template
            >
            <template #courier_name-cell="{ row }"
                ><span class="text-muted">{{
                    row.original.courier_name ?? "Sin asignación"
                }}</span></template
            >
            <template #status-cell="{ row }"
                ><UBadge
                    :color="
                        row.original.quality === 'conflict'
                            ? 'warning'
                            : row.original.status === 'delivered'
                              ? 'success'
                              : 'neutral'
                    "
                    variant="subtle"
                    >{{
                        row.original.quality === "conflict"
                            ? "Conflicto"
                            : (statusNames[row.original.status ?? ""] ??
                              "Pendiente")
                    }}</UBadge
                >
                <p
                    v-if="row.original.quality === 'pending'"
                    class="text-xs text-muted mt-1"
                >
                    Faltan eventos
                </p></template
            >
            <template #cost_minor-cell="{ row }"
                ><span>{{ money(row.original.cost_minor) }}</span>
                <p
                    v-if="row.original.cost_minor === null"
                    class="text-xs text-muted font-normal mt-1"
                >
                    {{
                        row.original.quality === "conflict"
                            ? "Revisar conflicto"
                            : "Pendiente de confirmar"
                    }}
                </p></template
            >
            <template #actions-cell="{ row }">
                <div class="flex flex-wrap items-center gap-2">
                    <template
                        v-if="canManage && deliveryActions(row.original).length"
                    >
                        <UButton
                            size="sm"
                            variant="soft"
                            :disabled="loading"
                            @click="
                                emit(
                                    'action',
                                    row.original,
                                    deliveryActions(row.original)[0]!,
                                )
                            "
                            >{{
                                deliveryActions(row.original)[0] === "created"
                                    ? "Completar creación"
                                    : eventActions[
                                          deliveryActions(row.original)[0]!
                                      ]
                            }}</UButton
                        >
                        <UDropdownMenu
                            v-if="deliveryActions(row.original).length > 1"
                            :items="
                                deliveryActions(row.original)
                                    .slice(1)
                                    .map((type) => ({
                                        label: eventActions[type],
                                        onSelect: () =>
                                            emit('action', row.original, type),
                                    }))
                            "
                        >
                            <UButton
                                icon="i-lucide-ellipsis"
                                color="neutral"
                                variant="ghost"
                                :aria-label="`Más acciones para ${row.original.delivery_address ?? 'esta entrega'}`"
                                :disabled="loading"
                            />
                        </UDropdownMenu>
                    </template>
                    <UModal
                        title="Detalle y trazabilidad"
                        :description="`Entrega ${row.original.id}`"
                        :close="{ 'aria-label': 'Cerrar detalle' }"
                        :ui="{ content: 'sm:max-w-4xl' }"
                    >
                        <UButton
                            icon="i-lucide-arrow-up-right"
                            color="neutral"
                            variant="ghost"
                            :aria-label="`Ver detalle ${row.original.id.slice(0, 8)}`"
                        />
                        <template #body>
                            <DeliveryDetail
                                :merchant-id="merchantId"
                                :delivery-id="row.original.id"
                                :timezone="timezone"
                            />
                        </template>
                    </UModal>
                </div>
            </template>
            <template #empty
                ><UEmpty
                    icon="i-lucide-package-search"
                    :title="
                        loading ? 'Cargando entregas…' : 'Sin coincidencias'
                    "
                    description="Prueba otra dirección, comercio o mensajero."
                    size="sm"
            /></template>
        </UTable>
        <template v-if="page" #footer
            ><div class="flex flex-wrap items-center justify-between gap-3">
                <span class="text-xs text-muted"
                    >{{ page.total }} entregas · página {{ page.page }} de
                    {{ page.last_page }}</span
                ><UPagination
                    :page="page.page"
                    :total="page.total"
                    :items-per-page="20"
                    :sibling-count="0"
                    :show-edges="false"
                    :disabled="loading"
                    size="xs"
                    color="neutral"
                    @update:page="changePage"
                /></div
        ></template>
    </UCard>
</template>
