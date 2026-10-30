<script setup lang="ts">
import { computed, defineAsyncComponent, shallowRef } from "vue";
import { demoLocations, type LocationPoint } from "../lib/demoLocations";
const props = defineProps<{
    disabled: boolean;
    kind: "picked_up" | "delivered";
    planning?: boolean;
    planned?: LocationPoint | null;
}>();
const point = defineModel<LocationPoint | null>({ required: true });
const query = shallowRef(props.planned?.label ?? "");
const selectedLabel = shallowRef(props.planned?.label ?? "");
const draft = shallowRef<LocationPoint | null>(
    props.planned ? { ...props.planned } : null,
);
const editing = shallowRef(!props.planned);
const showMap = shallowRef(false);
const LocationMap = defineAsyncComponent(() => import("./LocationMap.vue"));
const heading = computed(() =>
    props.planning
        ? props.kind === "picked_up"
            ? "Origen previsto"
            : "Destino previsto"
        : props.kind === "picked_up"
          ? "Lugar de recogida"
          : "Lugar de entrega",
);
const items = demoLocations.map((location) => ({
    label: `${location.name} · ${location.address}`,
    onSelect: () => selectLocation(location),
}));
function restorePlanned() {
    if (props.disabled || !props.planned) return;
    selectedLabel.value = props.planned.label;
    query.value = props.planned.label;
    draft.value = { ...props.planned };
    point.value = null;
    editing.value = false;
    showMap.value = false;
}
function selectLocation(location: (typeof demoLocations)[number]) {
    if (props.disabled) return;
    selectedLabel.value = `${location.name} · ${location.address}`;
    query.value = selectedLabel.value;
    draft.value = {
        latitude: location.latitude,
        longitude: location.longitude,
        label: selectedLabel.value,
    };
    point.value = null;
    showMap.value = false;
}
function search(value: string | null | undefined) {
    if (props.disabled) return;
    query.value = value ?? "";
    if (query.value !== selectedLabel.value) {
        draft.value = null;
        point.value = null;
        showMap.value = false;
    }
}
function adjust(value: { latitude: number; longitude: number }) {
    if (props.disabled || !draft.value) return;
    draft.value = {
        ...value,
        label: `Punto ajustado cerca de ${selectedLabel.value}`,
    };
    point.value = null;
}
function confirm() {
    if (!props.disabled && draft.value) point.value = { ...draft.value };
}
</script>

<template>
    <section class="space-y-3" :aria-label="heading">
        <div>
            <h3 class="font-medium text-sm">{{ heading }}</h3>
            <p
                v-if="!planning && planned && !editing"
                class="text-sm text-muted mt-1"
            >
                Este punto se eligió al crear la entrega. Confirma que la acción
                ocurrió aquí o cambia la ubicación real.
            </p>
            <p v-else-if="!planning" class="text-sm text-muted mt-1">
                Ubicaciones de demostración en Bogotá. Busca un nombre o
                dirección del catálogo; no es un buscador de cualquier
                dirección.
            </p>
        </div>
        <UFormField
            v-if="editing"
            :label="
                planning ? 'Buscar lugar' : 'Buscar ubicación de demostración'
            "
            :name="`demo_location_${kind}`"
        >
            <UInputMenu
                :model-value="query"
                mode="autocomplete"
                open-on-focus
                open-on-click
                :clear="{ 'aria-label': `Limpiar ${heading.toLowerCase()}` }"
                value-key="label"
                :items="items"
                :name="`demo_location_${kind}`"
                aria-label="Buscar ubicación de demostración"
                icon="i-lucide-search"
                placeholder="Por ejemplo: Museo del Oro"
                :disabled="disabled"
                :reset-search-term-on-blur="false"
                class="w-full"
                @update:model-value="search"
            >
                <template #empty
                    >No hay coincidencias en el catálogo. Prueba «Museo del
                    Oro», «Palacio Liévano» o «Parque Simón Bolívar».</template
                >
            </UInputMenu>
        </UFormField>
        <p v-if="editing && !planning" class="text-xs text-muted">
            Ejemplos: Museo del Oro · Palacio Liévano · Parque Simón Bolívar.
        </p>
        <UButton
            v-if="planned && !editing"
            type="button"
            color="neutral"
            variant="link"
            size="sm"
            :disabled="disabled"
            @click="editing = true"
            >Cambiar ubicación</UButton
        >
        <UButton
            v-if="planned && editing"
            type="button"
            color="neutral"
            variant="link"
            size="sm"
            :disabled="disabled"
            @click="restorePlanned"
            >Restablecer ubicación prevista</UButton
        >
        <template v-if="draft">
            <div
                :class="
                    planning
                        ? 'space-y-3'
                        : 'rounded-lg bg-elevated/50 p-3 space-y-2'
                "
            >
                <p v-if="!planning" class="text-sm font-medium leading-relaxed">
                    {{ draft.label }}
                </p>
                <p v-if="!planning" class="text-xs text-muted">
                    {{
                        planned && !editing
                            ? "Ubicación prevista. Confirmarla registra el punto real de esta acción."
                            : "Punto de referencia aproximado para el ejercicio. Puedes confirmarlo sin abrir el mapa."
                    }}
                </p>
                <div class="flex flex-wrap gap-2">
                    <UButton
                        v-if="!planning || !point"
                        type="button"
                        size="sm"
                        :variant="planning ? 'soft' : 'solid'"
                        icon="i-lucide-check"
                        :disabled="disabled || !!point"
                        @click="confirm"
                        >Confirmar ubicación</UButton
                    >
                    <UButton
                        v-if="!planning"
                        type="button"
                        size="sm"
                        color="neutral"
                        variant="outline"
                        icon="i-lucide-map"
                        :disabled="disabled"
                        :aria-expanded="showMap"
                        @click="showMap = !showMap"
                        >{{
                            showMap ? "Ocultar mapa" : "Ajustar en el mapa"
                        }}</UButton
                    >
                </div>
                <details v-if="!planning" class="text-xs text-muted">
                    <summary class="cursor-pointer">
                        Ver coordenadas seleccionadas
                    </summary>
                    <p class="mt-1">
                        Latitud {{ draft.latitude.toFixed(6) }} · Longitud
                        {{ draft.longitude.toFixed(6) }}
                    </p>
                </details>
            </div>
            <UCollapsible v-if="planning" class="space-y-2">
                <UButton
                    type="button"
                    color="neutral"
                    variant="link"
                    size="xs"
                    class="p-0"
                    trailing-icon="i-lucide-chevron-down"
                    >Mapa y coordenadas</UButton
                >
                <template #content>
                    <div class="space-y-3 pt-2">
                        <p class="text-xs text-muted">
                            Latitud {{ draft.latitude.toFixed(6) }} · Longitud
                            {{ draft.longitude.toFixed(6) }}
                        </p>
                        <UButton
                            type="button"
                            size="sm"
                            color="neutral"
                            variant="outline"
                            icon="i-lucide-map"
                            :disabled="disabled"
                            :aria-expanded="showMap"
                            @click="showMap = !showMap"
                            >{{
                                showMap ? "Ocultar mapa" : "Ajustar en el mapa"
                            }}</UButton
                        >
                        <LocationMap
                            v-if="showMap"
                            :point="draft"
                            :disabled="disabled"
                            @change="adjust"
                        />
                    </div>
                </template>
            </UCollapsible>
            <LocationMap
                v-if="showMap && !planning"
                :point="draft"
                :disabled="disabled"
                @change="adjust"
            />
        </template>
        <UAlert
            v-if="point && !planning"
            color="success"
            variant="subtle"
            title="Ubicación confirmada"
            :description="point.label"
        />
        <p
            v-else-if="point"
            role="status"
            class="flex items-center gap-1.5 text-xs text-primary"
        >
            <UIcon name="i-lucide-check" class="size-4 shrink-0" />Ubicación
            confirmada
        </p>
        <p v-else-if="!planning" role="status" class="text-xs text-muted">
            Selecciona una ubicación del catálogo y confírmala antes de
            registrar la acción.
        </p>
    </section>
</template>
