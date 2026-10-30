import type { Delivery } from "../types";
export type EventType =
    | "created"
    | "assigned"
    | "picked_up"
    | "in_transit"
    | "delivered"
    | "failed";
export const eventActions: Record<EventType, string> = {
    created: "Crear entrega",
    assigned: "Asignar mensajero",
    picked_up: "Registrar recogida",
    in_transit: "Marcar en ruta",
    delivered: "Registrar entrega",
    failed: "Registrar falla",
};
const sequence: EventType[] = [
    "created",
    "assigned",
    "picked_up",
    "in_transit",
    "delivered",
];
export function deliveryActions(delivery: Delivery): EventType[] {
    if (delivery.quality === "conflict") return [];
    const missing = sequence.filter((type) =>
        delivery.issues.includes(`missing_${type}`),
    );
    if (missing.length) return missing;
    if (delivery.status === "delivered" || delivery.status === "failed")
        return [];
    const index = sequence.indexOf(delivery.status as EventType);
    return index >= 0 && index < sequence.length - 1
        ? [sequence[index + 1]!, "failed"]
        : [];
}
