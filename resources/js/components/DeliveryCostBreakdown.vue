<script setup lang="ts">
import { money } from "../lib/format";
import type { DeliveryCalculation } from "../types";

defineProps<{ calculation: DeliveryCalculation }>();
</script>

<template>
    <div class="space-y-4">
        <p
            v-if="calculation.failed_percent !== null"
            class="text-sm text-muted"
        >
            Entrega fallida: se cobra el {{ calculation.failed_percent }} % de
            la tarifa base, sin recargos.
        </p>
        <dl class="grid grid-cols-2 sm:grid-cols-3 gap-4 text-sm">
            <div>
                <dt class="text-xs text-muted">Tarifa base</dt>
                <dd class="font-medium mt-1">
                    {{ money(calculation.base_minor) }}
                </dd>
            </div>
            <template v-if="calculation.failed_percent === null">
                <div>
                    <dt class="text-xs text-muted">Valor por kilómetro</dt>
                    <dd class="font-medium mt-1">
                        {{ money(calculation.per_km_minor) }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-muted">Distancia aproximada</dt>
                    <dd class="font-medium mt-1">
                        {{
                            calculation.distance_meters === null
                                ? "No disponible"
                                : `${new Intl.NumberFormat("es-CO").format(calculation.distance_meters)} m`
                        }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-muted">Recargo por distancia</dt>
                    <dd class="font-medium mt-1">
                        {{ money(calculation.distance_minor) }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-muted">
                        Subtotal antes de recargos nocturnos
                    </dt>
                    <dd class="font-medium mt-1">
                        {{ money(calculation.subtotal_minor) }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-muted">
                        Recargo nocturno<span v-if="calculation.night">
                            ({{ calculation.night_percent }} %)</span
                        >
                    </dt>
                    <dd class="font-medium mt-1">
                        {{ money(calculation.night_minor) }}
                    </dd>
                </div>
            </template>
            <div>
                <dt class="text-xs text-muted">
                    Costo confirmado · {{ calculation.currency }}
                </dt>
                <dd class="font-semibold text-primary mt-1">
                    {{ money(calculation.total_minor) }}
                </dd>
            </div>
        </dl>
        <p class="text-xs text-muted">
            Tarifa {{ calculation.tariff_id }} · reglas de cálculo versión
            {{ calculation.rules_version }} · {{ calculation.timezone }}
        </p>
    </div>
</template>
