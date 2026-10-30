<script setup lang="ts">
import { nextTick, shallowRef, toRef, watch } from "vue";
import { useToast } from "@nuxt/ui/composables/useToast";
import { useChat } from "../composables/useChat";
import { dateTime } from "../lib/format";
import ChatResult from "./ChatResult.vue";
import ThinkingIndicator from "./ThinkingIndicator.vue";
import type { HelpTarget } from "../types";
const props = defineProps<{
    enabled: boolean;
    merchantName: string;
    merchantId: number;
}>();
const { messages, loading, error, canRetry, send, retry } = useChat(
    toRef(props, "merchantId"),
);
const input = shallowRef("");
const open = shallowRef(false);
const history = shallowRef<HTMLElement | null>(null);
const toast = useToast();
const emit = defineEmits<{ navigate: [target: HelpTarget] }>();
let helpTarget: HelpTarget | undefined;
function navigate(target: HelpTarget) {
    helpTarget = target;
    open.value = false;
}
function interactOutside(event: CustomEvent<{ originalEvent: Event }>) {
    const target = event.detail.originalEvent.target;
    if (target instanceof Element && target.closest(".assistant-launcher")) {
        event.preventDefault();
    }
}
function closeAutoFocus(event: Event) {
    if (!helpTarget) return;
    event.preventDefault();
    emit("navigate", helpTarget);
    helpTarget = undefined;
}
async function copyMessage(event: MouseEvent) {
    const article = (event.currentTarget as HTMLElement | null)?.closest(
        "article",
    );
    const content = article?.querySelector<HTMLElement>(
        '[data-slot="content"]',
    );
    if (!content) return;
    try {
        await navigator.clipboard.writeText(content.innerText);
        toast.add({ title: "Respuesta copiada", icon: "i-lucide-check" });
    } catch {
        toast.add({ title: "No se pudo copiar la respuesta", color: "error" });
    }
}
watch(
    [() => messages.value.length, open, loading, history],
    async () => {
        await nextTick();
        if (history.value) history.value.scrollTop = history.value.scrollHeight;
    },
    { flush: "post" },
);
async function submit() {
    const text = input.value;
    input.value = "";
    await send(text);
}
</script>

<template>
    <div class="fixed right-4 bottom-4 sm:right-6 sm:bottom-6 z-40">
        <UPopover
            v-model:open="open"
            :content="{
                side: 'top',
                align: 'end',
                sideOffset: 12,
                collisionPadding: 16,
                onCloseAutoFocus: closeAutoFocus,
                onInteractOutside: interactOutside,
            }"
            :ui="{ content: 'assistant-panel rounded-2xl overflow-hidden' }"
        >
            <template #anchor>
                <span
                    class="absolute inset-0 pointer-events-none"
                    aria-hidden="true"
                />
            </template>
            <UButton
                aria-label="Asistente de operaciones"
                :title="open ? 'Cerrar asistente' : 'Abrir asistente'"
                :class="{ 'is-open': open }"
                class="assistant-launcher rounded-full size-14 justify-center shadow-lg"
                size="xl"
            >
                <span class="relative block size-6" aria-hidden="true">
                    <UIcon
                        name="i-lucide-sparkles"
                        class="assistant-sparkles absolute inset-0 size-6"
                    />
                    <UIcon
                        name="i-lucide-x"
                        class="assistant-close absolute inset-0 size-6"
                    />
                </span>
            </UButton>
            <template #content>
                <UCard
                    :ui="{
                        body: 'flex flex-col flex-1 min-h-0 p-0 sm:p-0',
                        header: 'shrink-0 p-4 sm:p-5',
                        footer: 'shrink-0 p-4 sm:p-4',
                    }"
                    class="flex flex-col h-[min(640px,calc(100dvh-116px))] w-[min(420px,calc(100vw-32px))] min-h-0 overflow-hidden rounded-2xl ring-0"
                    aria-labelledby="chat-title"
                >
                    <template #header
                        ><div class="flex items-start gap-3">
                            <span
                                class="bg-emerald-50 text-primary rounded-lg p-2 flex"
                                ><UIcon
                                    name="i-lucide-message-square"
                                    class="size-5"
                            /></span>
                            <div class="flex-1 min-w-0">
                                <h2
                                    id="chat-title"
                                    class="font-semibold text-base"
                                >
                                    Asistente de operaciones
                                </h2>
                                <p class="text-xs text-muted mt-1">
                                    {{ merchantName }} · solo consultas
                                </p>
                            </div>
                            <UButton
                                icon="i-lucide-x"
                                color="neutral"
                                variant="ghost"
                                aria-label="Cerrar ventana del asistente"
                                @click="open = false"
                            /></div
                    ></template>
                    <div
                        ref="history"
                        role="log"
                        aria-label="Conversación del asistente"
                        aria-live="polite"
                        class="flex-1 min-h-0 overflow-y-auto overscroll-contain p-5"
                    >
                        <div v-if="!messages.length" class="py-8">
                            <h3 class="font-semibold text-xl tracking-tight">
                                Los datos, en tus palabras.
                            </h3>
                            <p class="text-muted text-sm mt-3 leading-relaxed">
                                Pregunta por costos, estados o entregas. Verás
                                los resultados y el período de cada consulta.
                            </p>
                            <div class="flex flex-col items-start gap-2 mt-6">
                                <UButton
                                    color="neutral"
                                    variant="outline"
                                    size="sm"
                                    :disabled="!enabled || loading"
                                    class="whitespace-normal text-left"
                                    @click="
                                        send(
                                            '¿Cuánto costaron las entregas la semana pasada?',
                                        )
                                    "
                                    >Costos de la semana pasada
                                    <UIcon
                                        name="i-lucide-arrow-up-right" /></UButton
                                ><UButton
                                    color="neutral"
                                    variant="outline"
                                    size="sm"
                                    :disabled="!enabled || loading"
                                    @click="send('Busca entregas de Calle 80')"
                                    >Buscar entregas de Calle 80
                                    <UIcon name="i-lucide-arrow-up-right"
                                /></UButton>
                            </div>
                        </div>
                        <UChatMessages
                            v-else
                            :messages="messages"
                            :status="
                                loading
                                    ? 'submitted'
                                    : error
                                      ? 'error'
                                      : 'ready'
                            "
                            :user="{
                                side: 'right',
                                variant: 'solid',
                                color: 'primary',
                                avatar: { icon: 'i-lucide-user' },
                            }"
                            :assistant="{
                                side: 'left',
                                variant: 'outline',
                                avatar: { icon: 'i-lucide-bot' },
                                actions: [
                                    {
                                        label: 'Copiar respuesta',
                                        icon: 'i-lucide-copy',
                                        onClick: copyMessage,
                                    },
                                ],
                            }"
                            :should-auto-scroll="false"
                            :should-scroll-to-bottom="false"
                            :auto-scroll="false"
                            class="min-w-0"
                        >
                            <template #content="{ message }">
                                <ChatResult
                                    v-if="message.metadata.reply"
                                    :reply="message.metadata.reply"
                                    :help-disabled="!enabled || loading"
                                    @ask="send"
                                    @navigate="navigate"
                                />
                                <template
                                    v-else
                                    v-for="(part, index) in message.parts"
                                    :key="index"
                                >
                                    <p
                                        v-if="part.type === 'text'"
                                        class="whitespace-pre-wrap [overflow-wrap:anywhere] text-sm leading-relaxed"
                                    >
                                        {{ part.text }}
                                    </p>
                                </template>
                                <time
                                    v-if="
                                        !message.metadata.reply?.tool ||
                                        message.metadata.reply.tool ===
                                            'search_deliveries'
                                    "
                                    class="block text-[11px] mt-2"
                                    :class="
                                        message.role === 'user'
                                            ? 'text-white/80'
                                            : 'text-muted'
                                    "
                                    >{{ dateTime(message.metadata.date) }}</time
                                >
                            </template>
                            <template #indicator>
                                <ThinkingIndicator />
                            </template>
                        </UChatMessages>
                        <UAlert
                            v-if="error"
                            color="error"
                            variant="subtle"
                            :description="error"
                            class="mt-4"
                        >
                            <template v-if="canRetry" #actions>
                                <UButton
                                    type="button"
                                    icon="i-lucide-rotate-cw"
                                    color="error"
                                    variant="outline"
                                    :disabled="loading"
                                    :loading="loading"
                                    @click="retry"
                                    >Reintentar</UButton
                                >
                            </template>
                        </UAlert>
                    </div>
                    <template #footer>
                        <UAlert
                            v-if="!enabled"
                            color="warning"
                            variant="subtle"
                            description="Las consultas con IA no están disponibles. Puedes usar /help para ver tus permisos y acceder al panel."
                            class="mb-3"
                        />
                        <UChatPrompt
                            v-model="input"
                            class="assistant-prompt"
                            id="chat-message"
                            name="message"
                            :maxrows="4"
                            :disabled="loading"
                            :autofocus="false"
                            :maxlength="2000"
                            placeholder="Pregunta sobre este comercio…"
                            aria-label="Pregunta al asistente"
                            @submit="submit"
                            ><template #footer
                                ><UButton
                                    type="button"
                                    variant="link"
                                    size="xs"
                                    class="p-0"
                                    :disabled="loading"
                                    aria-label="Ver comandos y permisos con /help"
                                    @click="send('/help')"
                                    >Comandos: /help</UButton
                                ><UChatPromptSubmit
                                    :disabled="loading || !input.trim()"
                                    :loading="loading"
                                    aria-label="Enviar pregunta" /></template
                        ></UChatPrompt>
                    </template>
                </UCard>
            </template>
        </UPopover>
    </div>
</template>
