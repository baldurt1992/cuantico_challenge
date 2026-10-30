<script setup lang="ts">
import { dateTime, money, periodLabel, statusNames } from "../lib/format";
import type { AgentReply, HelpTarget } from "../types";
import ChatHelp from "./ChatHelp.vue";

defineProps<{ reply: AgentReply; helpDisabled?: boolean }>();
defineEmits<{ navigate: [target: HelpTarget]; ask: [prompt: string] }>();
</script>

<template>
    <div v-if="reply.tool === 'get_merchant_totals'" class="space-y-3 text-sm">
        <p class="font-medium">Costos de {{ reply.data.merchant_name }}</p>
        <p class="text-xs text-muted">
            {{ periodLabel(reply.data.period) }} ·
            {{ reply.data.period.timezone }}
        </p>
        <dl class="space-y-2">
            <div class="rounded-lg bg-default p-3 ring ring-default">
                <dt class="text-xs text-muted">Costo total confirmado · COP</dt>
                <dd class="text-xl font-semibold text-primary mt-1">
                    {{ money(reply.data.total_minor) }}
                </dd>
            </div>
            <div class="flex justify-between gap-3">
                <dt>Entregas con costo confirmado</dt>
                <dd class="font-semibold">{{ reply.data.deliveries }}</dd>
            </div>
            <div class="flex justify-between gap-3">
                <dt>Entregadas</dt>
                <dd class="font-semibold">{{ reply.data.delivered }}</dd>
            </div>
            <div class="flex justify-between gap-3">
                <dt>Fallidas</dt>
                <dd class="font-semibold">{{ reply.data.failed }}</dd>
            </div>
        </dl>
        <p class="text-xs text-muted">
            Se cuentan las entregas finalizadas en este período. Las que tienen
            costos pendientes o conflictos quedan fuera del total.
        </p>
        <p v-if="reply.data.deliveries === 0" class="text-sm">
            No hay entregas con costo confirmado en este período.
        </p>
        <p class="text-xs text-muted">
            Datos al
            {{ dateTime(reply.data.queried_at, reply.data.period.timezone) }}.
        </p>
    </div>
    <div
        v-else-if="reply.tool === 'get_courier_status_counts'"
        class="space-y-3 text-sm"
    >
        <p class="font-medium">
            Estados de las entregas de {{ reply.data.courier_name }}
        </p>
        <p class="text-xs text-muted">
            {{ periodLabel(reply.data.period) }} ·
            {{ reply.data.period.timezone }}
        </p>
        <dl v-if="Object.keys(reply.data.counts).length" class="space-y-2">
            <div
                v-for="(count, status) in reply.data.counts"
                :key="status"
                class="flex justify-between gap-3 rounded-lg bg-default p-3 ring ring-default"
            >
                <dt>{{ statusNames[status] ?? "Sin estado confirmado" }}</dt>
                <dd class="font-semibold">{{ count }}</dd>
            </div>
        </dl>
        <p v-else>No hay entregas de este mensajero creadas en el período.</p>
        <p class="text-xs text-muted">
            Se muestra el estado actual de las entregas creadas en este período.
        </p>
        <p class="text-xs text-muted">
            Datos al
            {{ dateTime(reply.data.queried_at, reply.data.period.timezone) }}.
        </p>
    </div>
    <div
        v-else-if="reply.tool === 'search_deliveries'"
        class="space-y-3 text-sm"
    >
        <p class="font-medium">
            {{
                reply.data.total === 1
                    ? "Encontré 1 entrega."
                    : `Encontré ${reply.data.total} entregas.`
            }}
        </p>
        <ul
            v-if="reply.data.data.length"
            class="space-y-3"
            aria-label="Entregas encontradas"
        >
            <li
                v-for="delivery in reply.data.data"
                :key="delivery.id"
                class="space-y-2 rounded-lg bg-default p-3 ring ring-default"
            >
                <p class="font-medium [overflow-wrap:anywhere]">
                    {{ delivery.delivery_address ?? "Dirección pendiente" }}
                </p>
                <UBadge
                    :color="
                        delivery.quality === 'conflict' ? 'warning' : 'neutral'
                    "
                    variant="subtle"
                    >{{
                        delivery.quality === "conflict"
                            ? "Conflicto por revisar"
                            : (statusNames[delivery.status ?? ""] ??
                              "Sin estado confirmado")
                    }}</UBadge
                >
                <dl class="space-y-1 text-xs">
                    <div class="flex justify-between gap-3">
                        <dt class="text-muted">Mensajero</dt>
                        <dd>{{ delivery.courier_name ?? "Sin asignación" }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-muted">Costo confirmado</dt>
                        <dd class="font-semibold">
                            {{ money(delivery.cost_minor)
                            }}<span v-if="delivery.cost_minor !== null">
                                COP</span
                            >
                        </dd>
                    </div>
                </dl>
                <p
                    v-if="delivery.quality === 'pending'"
                    class="text-xs text-muted"
                >
                    Faltan eventos para confirmar el estado o el costo.
                </p>
                <p class="text-xs text-muted">
                    Referencia {{ delivery.id.slice(0, 8) }}
                </p>
            </li>
        </ul>
        <p v-else>
            {{
                reply.data.total === 0
                    ? "Prueba con otra dirección o nombre de mensajero."
                    : "Esta página no tiene resultados. Pide la primera página de la búsqueda."
            }}
        </p>
        <p v-if="reply.data.total > 0" class="text-xs text-muted">
            Mostrando {{ reply.data.data.length }} de {{ reply.data.total }} ·
            página {{ reply.data.page }} de {{ reply.data.last_page }}.
        </p>
    </div>
    <div v-else>
        <p
            class="whitespace-pre-wrap [overflow-wrap:anywhere] text-sm leading-relaxed"
        >
            {{ reply.message }}
        </p>
        <ChatHelp
            v-if="reply.help"
            :help="reply.help"
            :disabled="helpDisabled"
            @ask="$emit('ask', $event)"
            @navigate="$emit('navigate', $event)"
        />
    </div>
</template>
