export interface Field {
    code: string;
    label: string;
    section: string;
    sequence: number;
    type: 'numeric' | 'text' | 'choice';
    unit: string;
    required: boolean;
    min: string | number | null;
    max: string | number | null;
    help: string;
    options: { code: string; label: string }[];
    checkbox?: boolean;
    group?: string | null;
    depends_on?: string | null;
    value?: string | null;
    is_set?: boolean;
}
export interface Template {
    id: number;
    name: string;
    code: string;
    revision: number;
    version: number;
    unit: string;
    fields: Field[];
    active: boolean;
    notes: string | null;
}
export interface Tax {
    name: string;
    type: 'percent' | 'fixed';
    rate: number;
    included: boolean;
    compound: boolean;
}
export interface Product {
    id: number;
    name: string;
    category: string;
    template_id: number;
    price: number;
    cost: number;
    cost_known: boolean;
    active: boolean;
    taxes: Tax[];
    version: number;
}
export interface Customer {
    id: number;
    name: string;
    phone: string;
    whatsapp?: string;
    address?: string;
    notes?: string;
    active: boolean;
    version: number;
    completed_count?: number;
}
export interface Method {
    id: number;
    name: string;
    kind: string;
    active: boolean;
    version: number;
}
export interface Settings {
    id: number;
    name: string;
    currency: string;
    timezone: string;
    country: string;
    phone: string;
    address: string;
    invoice_footer: string;
    credit_delivery: boolean;
    cost_reporting: boolean;
    recent_days: number;
    refresh_seconds: number;
    whatsapp_template: string;
    version: number;
}
export interface Totals {
    gross: number;
    discount: number;
    net: number;
    tax: number;
    total: number;
    lines?: LineTotals[];
}
export interface LineTotals {
    gross: number;
    line_discount: number;
    header_discount: number;
    net: number;
    tax: number;
    total: number;
}
export interface Snapshot {
    id: number;
    piece: number;
    revision: number;
    current: boolean;
    template_id: number;
    template_name: string;
    unit: string;
    fields: Field[];
    frozen_at: string | null;
    reason: string | null;
    source_snapshot_id: number | null;
    source_order?: string;
    source_date?: string;
}
export interface Line {
    discount_type: string;
    discount_value: number;
    id: number;
    product_id: number;
    description: string;
    category: string;
    quantity: number;
    unit_price: number;
    price_source: string;
    offered_price: number;
    measurement_mode: string;
    instructions: string;
    totals: LineTotals;
    snapshots: Snapshot[];
    template_id: number;
}
export interface Invoice {
    id: number;
    order_id: number;
    customer_id: number;
    reference: string;
    state: string;
    version: number;
    totals: Totals;
    contact: { name: string; phone: string };
    due?: number;
    lines: Line[];
}
export interface Order {
    discount_type: string;
    discount_value: number;
    id: number;
    reference: string;
    customer_id: number;
    version: number;
    stage: string;
    ordered_at: string;
    promised_at: string;
    original_promised_at: string | null;
    confirmed_at: string | null;
    delivered_at: string | null;
    notes: string;
    internal_notes: string;
    customer: Customer;
    contact: Customer;
    lines: Line[];
    invoice: Invoice | null;
    totals: Totals;
}
export interface Payment {
    id: number;
    customer_id: number;
    payment_method_id: number;
    reference: string;
    amount: number;
    available: number;
    settlement: string;
    version: number;
    effective_at: string;
}
export interface Followup {
    id: number;
    customer_id: number;
    name?: string;
    due_at: string;
    note: string;
    completed_at: string | null;
    version: number;
}
export interface Allocation {
    id: number;
    payment_id: number;
    amount: number;
    effective_at: string;
    reverses_id: number | null;
}
export interface Credit {
    id: number;
    reference: string;
    amount: number;
    net: number;
    tax: number;
    reason: string;
    refundable: number;
}
export interface Event {
    id: number;
    action: string;
    created_at: string;
    changes: Record<string, unknown>;
}

export interface MeasurementSearchPiece {
    id: number;
    piece: number;
    revision: number;
    unit: string;
    fields: Field[];
}
export interface MeasurementSearchProfile {
    template_id: number;
    template_name: string;
    garment: string;
    source_order_id: number;
    source_order: string;
    source_date: string;
    pieces: MeasurementSearchPiece[];
}
export interface MeasurementSearchCustomer {
    id: number;
    name: string;
    phone: string;
    whatsapp?: string;
    measurements: MeasurementSearchProfile[];
}

export interface Page<T> {
    data: T[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
    total: number;
}
export interface Base {
    settings: Settings;
    products: Product[];
    templates: Template[];
    customers: Customer[];
    methods: Method[];
    success?: string;
    errors?: Record<string, string>;
}
