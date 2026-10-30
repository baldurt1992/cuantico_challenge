import {
    onMounted,
    onScopeDispose,
    reactive,
    shallowRef,
    watch,
    type Ref,
} from "vue";
import { requestJson } from "../lib/api";
import { nextDate } from "../lib/format";
import type { OperationsData } from "../types";

export function useOperations(merchantId: Ref<number | undefined>) {
    const data = shallowRef<OperationsData | null>(null);
    const error = shallowRef("");
    const loading = shallowRef(false);
    const updatedAt = shallowRef<string | null>(null);
    const filters = reactive({
        text: "",
        page: 1,
        start: "",
        end: "",
        courier: undefined as number | undefined,
    });
    let controller: AbortController | null = null;
    let generation = 0;
    let interval: ReturnType<typeof setInterval> | undefined;

    async function refresh() {
        if (!merchantId.value || loading.value || document.hidden) return;
        const version = generation;
        controller = new AbortController();
        loading.value = true;
        const query = new URLSearchParams({ page: String(filters.page) });
        if (filters.text.trim()) query.set("text", filters.text.trim());
        if (filters.start && filters.end) {
            query.set("start_date", filters.start);
            query.set("end_date", nextDate(filters.end));
        } else query.set("period", "last_week");
        if (filters.courier) query.set("courier_id", String(filters.courier));
        try {
            const result = await requestJson<OperationsData>(
                `/api/v1/merchants/${merchantId.value}/operations?${query}`,
                { signal: controller.signal },
            );
            if (version !== generation) return;
            data.value = result;
            updatedAt.value = result.totals.queried_at;
            error.value = "";
        } catch (failure) {
            if (
                version === generation &&
                failure instanceof Error &&
                failure.name !== "AbortError"
            )
                error.value = failure.message;
        } finally {
            if (version === generation) loading.value = false;
        }
    }

    function invalidate(clear: boolean) {
        generation++;
        controller?.abort();
        loading.value = false;
        error.value = "";
        if (clear) {
            data.value = null;
            updatedAt.value = null;
        }
        void refresh();
    }
    watch(merchantId, () => {
        filters.text = "";
        filters.page = 1;
        filters.courier = undefined;
        invalidate(true);
    });
    watch(
        () => [filters.page, filters.start, filters.end, filters.courier],
        () => invalidate(false),
    );
    function onVisibility() {
        if (!document.hidden) void refresh();
    }
    onMounted(() => {
        void refresh();
        interval = setInterval(() => {
            void refresh();
        }, 5000);
        document.addEventListener("visibilitychange", onVisibility);
    });
    onScopeDispose(() => {
        generation++;
        controller?.abort();
        clearInterval(interval);
        document.removeEventListener("visibilitychange", onVisibility);
    });
    return { data, error, loading, updatedAt, filters, refresh };
}
