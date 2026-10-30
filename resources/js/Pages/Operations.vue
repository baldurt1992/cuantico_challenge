<script setup lang="ts">
import { es } from "@nuxt/ui/locale";
import { Head, router, usePage } from "@inertiajs/vue3";
import { computed, shallowRef, watch } from "vue";
import ChatPanel from "../components/ChatPanel.vue";
import CourierSummary from "../components/CourierSummary.vue";
import DeliveriesTable from "../components/DeliveriesTable.vue";
import EventForm from "../components/EventForm.vue";
import DatePicker from "../components/DatePicker.vue";
import { eventActions, type EventType } from "../lib/deliveryWorkflow";
import ReportsPanel from "../components/ReportsPanel.vue";
import { useOperations } from "../composables/useOperations";
import { dateTime, periodLabel, previousDate } from "../lib/format";
import type { Delivery, HelpTarget, Merchant } from "../types";
const props = defineProps<{ merchants: Merchant[]; agent_enabled: boolean }>();
const page = usePage<{
    auth: { user: { name: string; role: string } };
    version: string;
}>();
const merchantId = shallowRef<number | undefined>(props.merchants[0]?.id);
const merchant = computed(() =>
    props.merchants.find((item) => item.id === merchantId.value),
);
const { data, error, loading, updatedAt, filters, refresh } =
    useOperations(merchantId);
const showForm = shallowRef(false);
const selectedDelivery = shallowRef<Delivery>();
const selectedAction = shallowRef<EventType>("created");
const formVersion = shallowRef(0);
function prepareCreate() {
    selectedDelivery.value = undefined;
    selectedAction.value = "created";
    formVersion.value++;
}
function beginAction(delivery: Delivery, type: EventType) {
    selectedDelivery.value = delivery;
    selectedAction.value = type;
    formVersion.value++;
    showForm.value = true;
}
function deliverySaved() {
    search.value = "";
    filters.text = "";
    filters.page = 1;
    void refresh();
}
const search = shallowRef("");
const start = shallowRef("");
const end = shallowRef("");
const dateError = shallowRef("");
const isLastWeek = computed(() => !filters.start && !filters.end);
let syncPresetDates = true;
watch(
    () => data.value?.totals.period,
    (period, previous) => {
        const unchangedDraft =
            previous &&
            start.value === previous.start_date &&
            end.value === previousDate(previous.end_date_exclusive);
        if (period && isLastWeek.value && (syncPresetDates || unchangedDraft)) {
            start.value = period.start_date;
            end.value = previousDate(period.end_date_exclusive);
            syncPresetDates = false;
        }
    },
);
watch(merchantId, () => {
    showForm.value = false;
    search.value = "";
    if (isLastWeek.value) syncPresetDates = true;
});
function navigateChat(target: HelpTarget) {
    const allowed: HelpTarget[] = [
        "costs-report",
        "courier-summary",
        "delivery-search",
        "deliveries-table",
        "event-entry",
    ];
    if (!allowed.includes(target)) return;
    if (target === "event-entry") {
        if (page.props.auth.user.role === "operator") {
            prepareCreate();
            showForm.value = true;
        }
        return;
    }
    const element = document.getElementById(target);
    element?.scrollIntoView({ behavior: "auto", block: "start" });
    element?.focus({ preventScroll: true });
}

function searchDeliveries() {
    filters.text = search.value;
    filters.page = 1;
    void refresh();
}
function applyDates() {
    if (!start.value || !end.value || start.value > end.value) {
        dateError.value = "Selecciona un inicio y un fin válidos.";
        return;
    }
    dateError.value = "";
    filters.start = start.value;
    filters.end = end.value;
}
function previousWeek() {
    const alreadySelected = isLastWeek.value;
    syncPresetDates = true;
    filters.start = "";
    filters.end = "";
    dateError.value = "";
    if (alreadySelected) {
        if (data.value) {
            start.value = data.value.totals.period.start_date;
            end.value = previousDate(
                data.value.totals.period.end_date_exclusive,
            );
            syncPresetDates = false;
        }
        void refresh();
    }
}
</script>

<template>
    <UApp :locale="es">
        <Head title="Operaciones" />
        <a
            href="#operations-content"
            class="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-50 focus:bg-white focus:p-3"
            >Saltar al contenido</a
        >
        <UDashboardGroup>
            <UDashboardPanel id="operations" :ui="{ body: 'p-0 sm:p-0' }">
                <template #header>
                    <UDashboardNavbar
                        as="header"
                        :toggle="false"
                        class="bg-default"
                    >
                        <template #left>
                            <div class="flex items-center gap-3">
                                <UIcon
                                    name="i-lucide-route"
                                    class="size-6 text-primary"
                                /><span
                                    class="font-semibold text-lg tracking-tight"
                                    >Entregas</span
                                ><span
                                    class="hidden sm:inline text-xs text-muted border-l border-default pl-3"
                                    >Operaciones de entrega</span
                                >
                            </div>
                        </template>
                        <template #right>
                            <div class="flex items-center gap-3">
                                <span
                                    class="hidden sm:inline text-xs text-muted"
                                    >{{ page.props.auth.user.name }}</span
                                ><UBadge color="neutral" variant="subtle">{{
                                    page.props.auth.user.role === "operator"
                                        ? "Operador"
                                        : "Lector"
                                }}</UBadge
                                ><UButton
                                    icon="i-lucide-log-out"
                                    color="neutral"
                                    variant="ghost"
                                    aria-label="Cerrar sesión"
                                    @click="router.post('/logout')"
                                />
                            </div>
                        </template>
                    </UDashboardNavbar>
                </template>
                <template #body>
                    <main
                        id="operations-content"
                        tabindex="-1"
                        class="w-full max-w-[1600px] mx-auto px-4 sm:px-8 py-7"
                    >
                        <div
                            class="flex flex-col sm:flex-row sm:justify-between sm:items-end gap-5 mb-6"
                        >
                            <div>
                                <h1
                                    class="text-3xl font-semibold tracking-tight"
                                >
                                    Entregas y costos
                                </h1>
                                <p class="text-muted mt-2">
                                    Cada entrega, su estado y la explicación de
                                    su costo.
                                </p>
                            </div>
                            <UFormField label="Comercio activo"
                                ><UInputMenu
                                    :model-value="merchantId"
                                    @update:model-value="
                                        merchantId = $event ?? undefined
                                    "
                                    value-key="value"
                                    open-on-focus
                                    open-on-click
                                    :clear="{
                                        'aria-label': 'Limpiar comercio',
                                    }"
                                    name="merchant_id"
                                    :items="
                                        merchants.map((m) => ({
                                            label: m.name,
                                            value: m.id,
                                        }))
                                    "
                                    placeholder="Seleccionar comercio"
                                    class="w-full sm:w-64"
                                    size="lg"
                            /></UFormField>
                        </div>
                        <UAlert
                            v-if="!merchants.length"
                            color="warning"
                            title="Sin comercios asignados"
                            description="Solicita acceso a un comercio para consultar operaciones."
                        />
                        <div v-if="merchant" class="space-y-6">
                            <div class="min-w-0">
                                <div
                                    class="flex justify-between items-center gap-4 text-xs text-muted mb-4"
                                >
                                    <p>
                                        {{ merchant.timezone }} · datos de
                                        demostración
                                    </p>
                                    <UButton
                                        icon="i-lucide-refresh-cw"
                                        color="neutral"
                                        variant="ghost"
                                        size="xs"
                                        :loading="loading"
                                        @click="refresh"
                                        >Actualizar</UButton
                                    >
                                </div>
                                <UForm
                                    :state="{ start, end }"
                                    class="flex flex-wrap items-end gap-3 mb-5"
                                    aria-label="Período de costos"
                                    @submit="applyDates"
                                >
                                    <UFormField label="Desde"
                                        ><DatePicker
                                            v-model="start"
                                            name="start_date"
                                            label="Fecha inicial" /></UFormField
                                    ><UFormField label="Hasta, incluido"
                                        ><DatePicker
                                            v-model="end"
                                            name="end_date"
                                            label="Fecha final" /></UFormField
                                    ><UButton
                                        type="submit"
                                        color="neutral"
                                        variant="outline"
                                        >Aplicar</UButton
                                    ><UButton
                                        :color="
                                            isLastWeek ? 'primary' : 'neutral'
                                        "
                                        :variant="isLastWeek ? 'soft' : 'ghost'"
                                        :aria-pressed="isLastWeek"
                                        :disabled="loading"
                                        @click="previousWeek"
                                        >Semana pasada</UButton
                                    >
                                </UForm>
                                <p
                                    v-if="dateError"
                                    role="alert"
                                    class="text-red-700 text-xs mb-3"
                                >
                                    {{ dateError }}
                                </p>
                                <p v-if="data" class="text-xs text-muted mb-3">
                                    {{
                                        isLastWeek
                                            ? "Semana pasada"
                                            : "Período personalizado"
                                    }}
                                    · {{ periodLabel(data.totals.period) }} ·
                                    {{ data.totals.period.timezone }}. Aplica a
                                    costos por finalización y estados por
                                    mensajero.
                                </p>
                                <UAlert
                                    v-if="error"
                                    color="error"
                                    title="No se pudieron actualizar los datos"
                                    :description="error"
                                    class="mb-4"
                                />
                                <p
                                    v-if="error && data"
                                    class="text-amber-800 text-xs mb-3"
                                >
                                    Los resultados conservados pueden estar
                                    desactualizados.
                                </p>
                                <ReportsPanel
                                    id="costs-report"
                                    tabindex="-1"
                                    :data="data"
                                />
                                <CourierSummary
                                    id="courier-summary"
                                    tabindex="-1"
                                    v-model="filters.courier"
                                    :couriers="data?.couriers ?? []"
                                    :counts="data?.courier_counts ?? null"
                                    :loading="loading"
                                />
                                <div
                                    class="flex flex-wrap justify-between items-end gap-3 mt-7 mb-4"
                                >
                                    <div>
                                        <h2 class="text-lg font-semibold">
                                            Entregas del comercio
                                        </h2>
                                        <p class="text-xs text-muted mt-1">
                                            Todos los períodos · consulta el
                                            detalle para revisar el cálculo.
                                        </p>
                                    </div>
                                    <UModal
                                        v-if="
                                            page.props.auth.user.role ===
                                            'operator'
                                        "
                                        :key="merchant.id"
                                        v-model:open="showForm"
                                        :title="eventActions[selectedAction]"
                                        :description="
                                            selectedAction === 'created'
                                                ? `${merchant.name} · Define el origen y el destino.`
                                                : 'Registra lo que ocurrió. Cada acción conserva el historial y actualiza el estado y costo de la entrega.'
                                        "
                                        :close="{
                                            'aria-label': 'Cerrar formulario',
                                        }"
                                        :ui="{
                                            content:
                                                selectedAction === 'created'
                                                    ? 'sm:max-w-3xl'
                                                    : 'sm:max-w-2xl',
                                            header: 'pr-14',
                                        }"
                                    >
                                        <UButton
                                            id="event-entry"
                                            icon="i-lucide-plus"
                                            size="sm"
                                            @click="prepareCreate"
                                            >Crear entrega</UButton
                                        >
                                        <template #body>
                                            <EventForm
                                                :key="formVersion"
                                                :merchant-id="merchant.id"
                                                :merchant-name="merchant.name"
                                                :timezone="merchant.timezone"
                                                :couriers="data?.couriers ?? []"
                                                :delivery="selectedDelivery"
                                                :type="selectedAction"
                                                @saved="deliverySaved"
                                                @proceed="beginAction"
                                                @close="showForm = false"
                                            />
                                        </template>
                                    </UModal>
                                </div>
                                <UForm
                                    :state="{ search }"
                                    class="flex gap-2 mb-4"
                                    role="search"
                                    @submit="searchDeliveries"
                                    ><UInput
                                        v-model="search"
                                        id="delivery-search"
                                        name="text"
                                        icon="i-lucide-search"
                                        placeholder="Buscar por dirección, comercio o mensajero"
                                        aria-label="Buscar entregas"
                                        class="flex-1"
                                        :maxlength="100"
                                    /><UButton
                                        type="submit"
                                        color="neutral"
                                        variant="outline"
                                        :disabled="loading"
                                        >Buscar</UButton
                                    ></UForm
                                >
                                <DeliveriesTable
                                    id="deliveries-table"
                                    tabindex="-1"
                                    :page="data?.deliveries ?? null"
                                    :merchant-id="merchant.id"
                                    :timezone="merchant.timezone"
                                    :loading="loading"
                                    :can-manage="
                                        page.props.auth.user.role === 'operator'
                                    "
                                    @action="beginAction"
                                    @page="filters.page = $event"
                                />
                                <p class="text-[11px] text-muted mt-6">
                                    {{
                                        updatedAt
                                            ? `Última actualización: ${dateTime(updatedAt)}`
                                            : "Esperando primera actualización"
                                    }}
                                    · refresco cada 5 s con pestaña visible
                                </p>
                            </div>
                            <ChatPanel
                                :key="merchant.id"
                                :merchant-id="merchant.id"
                                :merchant-name="merchant.name"
                                :enabled="agent_enabled"
                                @navigate="navigateChat"
                            />
                        </div>
                        <footer
                            class="mt-10 pt-5 pb-20 border-t border-default flex flex-wrap justify-between gap-2 text-[11px] text-muted"
                        >
                            <span
                                >Operaciones de entrega · datos sintéticos</span
                            ><span>Versión {{ page.props.version }}</span>
                        </footer>
                    </main>
                </template>
            </UDashboardPanel>
        </UDashboardGroup>
    </UApp>
</template>
