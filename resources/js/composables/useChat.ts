import {
    computed,
    onScopeDispose,
    ref,
    shallowRef,
    watch,
    type Ref,
} from "vue";
import { ApiError, requestJson } from "../lib/api";
import type { AgentReply } from "../types";

export interface ChatItem {
    id: string;
    role: "user" | "assistant";
    parts: { type: "text"; text: string }[];
    metadata: { date: string; reply?: AgentReply };
}
export function useChat(merchantId: Ref<number>) {
    const messages = ref<ChatItem[]>([]);
    const loading = shallowRef(false);
    const error = shallowRef("");
    const failedMessage = shallowRef<string | null>(null);
    const canRetry = computed(() => failedMessage.value !== null);
    let generation = 0;
    let controller: AbortController | null = null;
    function clear() {
        generation++;
        controller?.abort();
        messages.value = [];
        loading.value = false;
        error.value = "";
        failedMessage.value = null;
    }
    watch(merchantId, clear);
    onScopeDispose(clear);
    async function request(text: string, appendQuestion: boolean) {
        if (!text.trim() || loading.value) return;
        const version = generation;
        loading.value = true;
        error.value = "";
        failedMessage.value = null;
        controller = new AbortController();
        if (appendQuestion)
            messages.value.push({
                id: crypto.randomUUID(),
                role: "user",
                parts: [{ type: "text", text }],
                metadata: { date: new Date().toISOString() },
            });
        try {
            const reply = await requestJson<AgentReply>(
                `/api/v1/merchants/${merchantId.value}/chat`,
                {
                    method: "POST",
                    body: JSON.stringify({ message: text }),
                    signal: controller.signal,
                },
            );
            if (version !== generation) return;
            messages.value.push({
                id: reply.request_id,
                role: "assistant",
                parts: [{ type: "text", text: reply.message }],
                metadata: { date: new Date().toISOString(), reply },
            });
        } catch (failure) {
            if (
                version === generation &&
                failure instanceof Error &&
                failure.name !== "AbortError"
            ) {
                error.value = failure.message;
                if (failure instanceof ApiError && failure.status === 504) {
                    failedMessage.value = text;
                }
            }
        } finally {
            if (version === generation) loading.value = false;
        }
    }
    async function send(text: string) {
        await request(text, true);
    }
    async function retry() {
        if (failedMessage.value === null) return;
        await request(failedMessage.value, false);
    }
    return { messages, loading, error, canRetry, send, retry };
}
