<script setup lang="ts">
import {
    onMounted,
    onScopeDispose,
    shallowRef,
    useTemplateRef,
    watch,
} from "vue";
import {
    map as createMap,
    tileLayer,
    marker,
    divIcon,
    control,
    type Map as LeafletMap,
    type Marker,
    type LatLng,
} from "leaflet";
import "leaflet/dist/leaflet.css";
import type { LocationPoint } from "../lib/demoLocations";
const props = defineProps<{ point: LocationPoint; disabled: boolean }>();
const emit = defineEmits<{
    change: [point: { latitude: number; longitude: number }];
}>();
const container = useTemplateRef<HTMLDivElement>("container");
const tilesReady = shallowRef(false);
const tileError = shallowRef(false);
let map: LeafletMap | undefined;
let pin: Marker | undefined;
let resizeObserver: ResizeObserver | undefined;
function choose(position: LatLng) {
    if (props.disabled || !tilesReady.value || tileError.value) return;
    const point = position.wrap();
    pin?.setLatLng(point);
    emit("change", {
        latitude: Number(point.lat.toFixed(6)),
        longitude: Number(point.lng.toFixed(6)),
    });
}
function useCenter() {
    if (map) choose(map.getCenter());
}
onMounted(() => {
    if (!container.value) return;
    map = createMap(container.value, {
        zoomControl: false,
        scrollWheelZoom: false,
        maxBounds: [
            [-85, -180],
            [85, 180],
        ],
        minZoom: 10,
        maxZoom: 19,
    }).setView([props.point.latitude, props.point.longitude], 17);
    control.zoom({ zoomInTitle: "Acercar", zoomOutTitle: "Alejar" }).addTo(map);
    const tiles = tileLayer(
        import.meta.env.VITE_MAP_TILE_URL ||
            "https://tile.openstreetmap.org/{z}/{x}/{y}.png",
        {
            attribution:
                '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            maxZoom: 19,
        },
    ).addTo(map);
    tiles.on("tileload", () => {
        tilesReady.value = true;
        if (!props.disabled && !tileError.value) pin?.dragging?.enable();
    });
    tiles.on("tileerror", () => {
        tileError.value = true;
        pin?.dragging?.disable();
    });
    pin = marker([props.point.latitude, props.point.longitude], {
        draggable: false,
        title: "Ubicación seleccionada",
        alt: "Ubicación seleccionada",
        icon: divIcon({
            className: "location-map-pin",
            html: '<span aria-hidden="true"></span>',
            iconSize: [24, 24],
            iconAnchor: [12, 12],
        }),
    }).addTo(map);
    pin.on("dragend", () => {
        if (pin) choose(pin.getLatLng());
    });
    map.on("click", (event) => choose(event.latlng));
    resizeObserver = new ResizeObserver(() =>
        map?.invalidateSize({ pan: false }),
    );
    resizeObserver.observe(container.value);
});
watch(
    () => [props.point.latitude, props.point.longitude],
    () => {
        pin?.setLatLng([props.point.latitude, props.point.longitude]);
        if (
            map &&
            !map
                .getBounds()
                .contains([props.point.latitude, props.point.longitude])
        )
            map.panTo([props.point.latitude, props.point.longitude]);
    },
);
watch(
    () => props.disabled,
    (disabled) => {
        if (disabled || !tilesReady.value || tileError.value)
            pin?.dragging?.disable();
        else pin?.dragging?.enable();
    },
);
onScopeDispose(() => {
    resizeObserver?.disconnect();
    map?.remove();
});
</script>

<template>
    <div class="space-y-3">
        <p class="text-xs text-muted">
            Pulsa un punto o mueve el marcador. Con teclado, enfoca el mapa, usa
            las flechas para moverlo y pulsa «Seleccionar centro del mapa».
            Después confirma la ubicación.
        </p>
        <UAlert
            v-if="tileError"
            color="warning"
            variant="subtle"
            description="No se pudo cargar el mapa completo. Puedes confirmar la ubicación del catálogo sin usar el mapa."
        />
        <p v-else-if="!tilesReady" role="status" class="text-xs text-muted">
            Cargando mapa opcional. Puedes continuar con la ubicación del
            catálogo.
        </p>
        <div
            ref="container"
            class="location-map h-64 w-full rounded-lg border border-default z-0"
            role="region"
            aria-label="Mapa para ajustar la ubicación"
            tabindex="0"
        />
        <UButton
            type="button"
            color="neutral"
            variant="outline"
            size="sm"
            :disabled="disabled || !tilesReady || tileError"
            @click="useCenter"
            >Seleccionar centro del mapa</UButton
        >
    </div>
</template>

<style scoped>
.location-map :deep(.location-map-pin span) {
    display: block;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background: #006d59;
    border: 3px solid white;
    box-shadow: 0 2px 8px #0006;
}
.location-map :deep(.leaflet-control-attribution) {
    font-size: 11px;
}
</style>
