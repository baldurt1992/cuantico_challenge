import type { LocationPoint } from "./lib/demoLocations";

export interface Merchant {
    id: number;
    name: string;
    timezone: string;
}
export interface Courier {
    id: number;
    name: string;
}
export interface DeliveryCalculation {
    rules_version: string;
    tariff_id: number;
    currency: string;
    base_minor: number;
    per_km_minor: number;
    distance_meters: number | null;
    distance_minor: number;
    subtotal_minor: number | null;
    night: boolean;
    night_percent: number;
    night_minor: number;
    failed_percent: number | null;
    completed_at: string;
    local_completed_at: string;
    timezone: string;
    total_minor: number;
}
export interface Delivery {
    id: string;
    merchant_id: number;
    status: string | null;
    quality: string;
    issues: string[];
    courier_id: number | null;
    courier_name: string | null;
    delivery_address: string | null;
    planned_pickup: LocationPoint | null;
    planned_destination: LocationPoint | null;
    created_occurred_at: string | null;
    completed_at: string | null;
    cost_minor: number | null;
    currency: string;
    calculation: DeliveryCalculation | null;
}
export interface DeliveryPage {
    data: Delivery[];
    page: number;
    last_page: number;
    total: number;
    queried_at: string;
}
export interface Period {
    start: string;
    end: string;
    start_date: string;
    end_date_exclusive: string;
    timezone: string;
    criterion: string;
}
export interface Totals {
    merchant_name: string;
    total_minor: number;
    deliveries: number;
    delivered: number;
    failed: number;
    period: Period;
    queried_at: string;
}
export interface CourierCounts {
    counts: Record<string, number>;
    courier_name: string;
    period: Period;
    queried_at: string;
}
export interface OperationsData {
    deliveries: DeliveryPage;
    totals: Totals;
    issues: { pending: number; conflict: number; scope: string };
    couriers: Courier[];
    courier_counts: CourierCounts | null;
}
export interface DeliveryDetail {
    delivery: Delivery;
    events: {
        event_id: string;
        type: string;
        occurred_at: string;
        received_at: string;
        data: Record<string, unknown>;
    }[];
    revisions: {
        id: number;
        reason: string;
        snapshot: Pick<
            Delivery,
            | "status"
            | "quality"
            | "issues"
            | "courier_id"
            | "delivery_address"
            | "planned_pickup"
            | "planned_destination"
            | "created_occurred_at"
            | "completed_at"
            | "cost_minor"
            | "calculation"
        > & { source_events: string[] };
        created_at: string;
    }[];
}
export type AgentReply = {
    request_id: string;
    message: string;
} & (
    | { tool: "get_merchant_totals"; data: Totals }
    | { tool: "get_courier_status_counts"; data: CourierCounts }
    | { tool: "search_deliveries"; data: DeliveryPage }
    | { tool?: undefined; data: null; help?: AgentHelp }
);

export type HelpTarget =
    | "costs-report"
    | "courier-summary"
    | "delivery-search"
    | "deliveries-table"
    | "event-entry";
export interface AgentHelp {
    role: "reader" | "operator";
    merchant_name: string;
    can_ingest: boolean;
    queries: {
        label: string;
        description: string;
        prompt: string | null;
    }[];
    actions: {
        label: string;
        description: string;
        command: string;
        href: string;
        target: HelpTarget;
    }[];
}
