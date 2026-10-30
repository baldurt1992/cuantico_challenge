import type { Period } from "../types";

export const statusNames: Record<string, string> = {
    created: "Creada",
    assigned: "Asignada",
    picked_up: "Recogida",
    in_transit: "En ruta",
    delivered: "Entregada",
    failed: "Fallida",
    conflict: "Conflicto",
};
export function deliveryIssueLabel(issue: string): string {
    const labels: Record<string, string> = {
        repeated_status: "Se recibió más de un evento para el mismo estado.",
        incompatible_sequence: "La secuencia de estados es incompatible.",
        contradictory_terminals: "La entrega figura como entregada y fallida.",
        missing_tariff: "No hay una tarifa aplicable a la fecha de creación.",
    };
    if (issue.startsWith("missing_")) {
        const status = statusNames[issue.slice(8)];
        if (status)
            return `Falta el evento: ${status.toLocaleLowerCase("es-CO")}.`;
    }
    return labels[issue] ?? "Hay una incidencia que requiere revisión.";
}
export function money(minor: number | null): string {
    return minor === null
        ? "Pendiente"
        : new Intl.NumberFormat("es-CO", {
              style: "currency",
              currency: "COP",
              minimumFractionDigits: 2,
          }).format(minor / 100);
}
export function dateTime(
    utc: string | null,
    timezone = "America/Bogota",
): string {
    if (!utc) return "Sin fecha";
    const date = new Date(
        utc.includes("T") ? utc : utc.replace(" ", "T") + "Z",
    );
    return new Intl.DateTimeFormat("es-CO", {
        dateStyle: "medium",
        timeStyle: "short",
        timeZone: timezone,
    }).format(date);
}
export function nextDate(date: string): string {
    const value = new Date(`${date}T00:00:00Z`);
    value.setUTCDate(value.getUTCDate() + 1);
    return value.toISOString().slice(0, 10);
}

export function previousDate(date: string): string {
    const value = new Date(`${date}T00:00:00Z`);
    value.setUTCDate(value.getUTCDate() - 1);
    return value.toISOString().slice(0, 10);
}

export function periodLabel(period: Period): string {
    const start = new Date(`${period.start_date}T00:00:00Z`);
    const end = new Date(`${period.end_date_exclusive}T00:00:00Z`);
    end.setUTCDate(end.getUTCDate() - 1);
    return new Intl.DateTimeFormat("es-CO", {
        dateStyle: "long",
        timeZone: "UTC",
    }).formatRange(start, end);
}
