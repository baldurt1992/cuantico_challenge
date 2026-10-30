<script setup lang="ts">
import { onMounted, onUnmounted, shallowRef } from "vue";

const patterns: number[][][] = [
    [
        [0],
        [1],
        [2],
        [3],
        [7],
        [11],
        [15],
        [14],
        [13],
        [12],
        [8],
        [4],
        [5],
        [6],
        [10],
        [9],
    ],
    [
        [0, 4, 8, 12],
        [1, 5, 9, 13],
        [2, 6, 10, 14],
        [3, 7, 11, 15],
    ],
    [
        [5, 6, 9, 10],
        [1, 4, 7, 8, 11, 14],
        [0, 3, 12, 15],
        [1, 4, 7, 8, 11, 14],
        [5, 6, 9, 10],
    ],
    [[0], [1, 4], [2, 5, 8], [3, 6, 9, 12], [7, 10, 13], [11, 14], [15]],
];
const labels = ["Consultando…", "Un momento…", "Preparando respuesta…"];
const displayedText = shallowRef(labels[0] ?? "Consultando…");
const activeDots = shallowRef(new Set([0]));
let patternIndex = 0;
let stepIndex = 0;
let labelIndex = 0;
let matrixInterval: ReturnType<typeof setInterval> | undefined;
let textInterval: ReturnType<typeof setInterval> | undefined;
let frame: number | undefined;
let motionPreference: MediaQueryList | undefined;

function nextStep() {
    const pattern = patterns[patternIndex];
    if (!pattern) return;
    activeDots.value = new Set(pattern[stepIndex] ?? []);
    stepIndex = (stepIndex + 1) % pattern.length;
    if (stepIndex === 0) patternIndex = (patternIndex + 1) % patterns.length;
}

function changeText() {
    const from = displayedText.value;
    labelIndex = (labelIndex + 1) % labels.length;
    const to = labels[labelIndex] ?? labels[0] ?? "Consultando…";
    const characters = "abcdefghijklmnopqrstuvwxyz";
    const length = Math.max(from.length, to.length);
    let progress = 0;
    function step() {
        progress++;
        const reveal = (progress / 15) * length;
        displayedText.value = Array.from({ length }, (_, index) =>
            index < reveal - 2
                ? (to[index] ?? "")
                : index < reveal
                  ? characters[Math.floor(Math.random() * characters.length)]
                  : (from[index] ?? ""),
        ).join("");
        if (progress < 15) frame = requestAnimationFrame(step);
        else {
            displayedText.value = to;
            frame = undefined;
        }
    }
    frame = requestAnimationFrame(step);
}

function stop() {
    clearInterval(matrixInterval);
    clearInterval(textInterval);
    if (frame !== undefined) cancelAnimationFrame(frame);
    matrixInterval = undefined;
    textInterval = undefined;
    frame = undefined;
}

function updateMotion() {
    stop();
    displayedText.value = labels[0] ?? "Consultando…";
    if (motionPreference?.matches) return;
    nextStep();
    matrixInterval = setInterval(nextStep, 120);
    textInterval = setInterval(changeText, 3000);
}

onMounted(() => {
    motionPreference = window.matchMedia("(prefers-reduced-motion: reduce)");
    motionPreference.addEventListener("change", updateMotion);
    updateMotion();
});
onUnmounted(() => {
    stop();
    motionPreference?.removeEventListener("change", updateMotion);
});
</script>

<template>
    <div role="status" aria-label="Consultando al asistente">
        <span class="sr-only">Espera la respuesta.</span>
        <div
            class="flex items-center gap-3 text-muted overflow-hidden"
            aria-hidden="true"
        >
            <div class="grid grid-cols-4 gap-0.5 size-5 shrink-0">
                <span
                    v-for="index in 16"
                    :key="index"
                    class="rounded-xs bg-current transition-opacity duration-100"
                    :class="
                        activeDots.has(index - 1) ? 'opacity-100' : 'opacity-20'
                    "
                />
            </div>
            <UChatShimmer :text="displayedText" class="text-sm font-mono" />
        </div>
    </div>
</template>
