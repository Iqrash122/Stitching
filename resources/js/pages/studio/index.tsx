import { Link, router, useForm } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import type {
    Base,
    Page,
    Customer,
    Order,
    Invoice,
    Payment,
    Followup,
    Event,
    Product,
    Template,
    MeasurementSearchCustomer,
} from './types';
import {
    Shell,
    Panel,
    Button,
    NewButton,
    Action,
    Form,
    Modal,
    DataTable,
    Pagination,
    Filters,
    Badge,
    money,
    decimal,
    stamp,
    localNow,
} from './ui';
import type { InputSpec, Payload } from './ui';
import { NewOrder, ProductEditor, TemplateEditor } from './editors';
import {
    allowNextUnsavedNavigation,
    ErrorSummary,
    useFirstErrorFocus,
    useUnsavedChanges,
} from './form-support';
interface Dashboard {
    period_from: string;
    period: string;
    refreshed_at: string;
    net_revenue: number;
    discounts: number;
    allocated_receipts: number;
    refunds: number;
    outstanding_now: number;
    unbilled: number;
    late: number;
    created_today: number;
    delivered_today: number;
    stages: { stage: string; count: number }[];
    returning: number;
    population: number;
    returning_rate: number;
    trend: { date: string; net: number }[];
    deliveries: Order[];
    follow_ups: number;
}
interface Props extends Base {
    export_jobs?: {
        id: number;
        report: string;
        format: string;
        state: string;
        error: string | null;
        row_count: number | null;
        created_at: string;
    }[];
    section: string;
    filters: Record<string, string>;
    rows?:
        | Page<Customer>
        | Page<Order>
        | Page<Invoice>
        | Page<Followup>
        | Page<Event>;
    dashboard?: Dashboard;
    payments?: Payment[];
    report_rows?: Record<string, unknown>[];
    report_count?: number;
    measurement_rows?: Page<MeasurementSearchCustomer>;
    prices?: {
        id: number;
        customer_id: number;
        product_id: number;
        price: number;
        active: boolean;
        version: number;
    }[];
}
export const customerFields: InputSpec[] = [
    { name: 'name', label: 'Customer name', required: true },
    { name: 'phone', label: 'Phone', required: true },
    { name: 'whatsapp', label: 'Separate WhatsApp number (optional)' },
    { name: 'address', label: 'Address', type: 'textarea' },
    { name: 'notes', label: 'Private customer notes', type: 'textarea' },
    { name: 'active', label: 'Active customer', type: 'checkbox' },
];
export const customerDefaults = {
    name: '',
    phone: '',
    whatsapp: '',
    address: '',
    notes: '',
    active: true,
};
const opts = (rows: { id: number; name: string }[]) =>
    rows.map((c) => ({ value: c.id, label: c.name }));
export function paymentFields(base: Base): InputSpec[] {
    return [
        {
            name: 'customer_id',
            label: 'Customer',
            type: 'select',
            options: opts(base.customers),
            required: true,
        },
        {
            name: 'amount',
            label: `Amount (${base.settings.currency})`,
            type: 'number',
            required: true,
        },
        {
            name: 'payment_method_id',
            label: 'Payment method',
            type: 'select',
            options: opts(base.methods),
            required: true,
        },
        {
            name: 'settlement',
            label: 'Settlement state',
            type: 'select',
            options: [
                { value: 'settled', label: 'Settled funds' },
                { value: 'pending', label: 'Pending bank settlement' },
            ],
            required: true,
        },
        {
            name: 'effective_at',
            label: `Date and time (${base.settings.timezone})`,
            type: 'datetime-local',
            required: true,
        },
        { name: 'external_reference', label: 'Bank / external reference' },
    ];
}
export default function StudioIndex(p: Props) {
    const [dialog, setDialog] = useState<string | null>(null);
    const [product, setProduct] = useState<Product>();
    const [template, setTemplate] = useState<Template>();
    const [orderCustomerId, setOrderCustomerId] = useState<number>();
    const [refreshing, setRefreshing] = useState(false);
    const refreshInFlight = useRef(false);
    const { section } = p;
    const reloadPartial = useCallback((only: string[]) => {
        if (refreshInFlight.current) return;
        refreshInFlight.current = true;
        setRefreshing(true);
        router.reload({
            only,
            onFinish: () => {
                refreshInFlight.current = false;
                setRefreshing(false);
            },
        });
    }, []);
    useEffect(() => {
        if (!['dashboard', 'exports'].includes(section)) return;
        const timer = setInterval(() => {
            if (
                document.visibilityState === 'visible' &&
                !document.querySelector('dialog[open]')
            )
                reloadPartial(
                    section === 'exports' ? ['export_jobs'] : ['dashboard'],
                );
        }, p.settings.refresh_seconds * 1000);
        return () => clearInterval(timer);
    }, [section, p.settings.refresh_seconds, reloadPartial]);
    const newAction =
        section === 'dashboard' || section === 'orders' ? (
            <NewButton
                label="New order"
                onClick={() => {
                    setOrderCustomerId(undefined);
                    setDialog('order');
                }}
            />
        ) : section === 'customers' || section === 'measurement-search' ? (
            <NewButton
                label="New customer"
                onClick={() => setDialog('customer')}
            />
        ) : section === 'products' ? (
            <NewButton
                label="New service"
                onClick={() => {
                    setProduct(undefined);
                    setDialog('product');
                }}
            />
        ) : section === 'templates' ? (
            <NewButton
                label="New template"
                onClick={() => {
                    setTemplate(undefined);
                    setDialog('template');
                }}
            />
        ) : undefined;
    const dateFields: InputSpec[] = [
        {
            name: 'effective_at',
            label: 'Financial date and time',
            type: 'datetime-local',
            required: true,
        },
        { name: 'reason', label: 'Reason', type: 'textarea', required: true },
    ];
    const orderColumns = [
        {
            label: 'Order',
            value: (o: Order) => (
                <Link href={`/studio/orders/${o.id}`}>{o.reference}</Link>
            ),
        },
        {
            label: 'Customer',
            value: (o: Order) => (
                <>
                    <strong>{o.customer?.name}</strong>
                    <div className="muted">{o.customer?.phone}</div>
                </>
            ),
        },
        {
            label: 'Delivery',
            value: (o: Order) => stamp(o.promised_at, p.settings.timezone),
        },
        {
            label: 'Stage',
            value: (o: Order) => (
                <>
                    <Badge stage={o.confirmed_at ? o.stage : 'Draft'} />
                    {o.confirmed_at &&
                        !['Delivered', 'Cancelled'].includes(o.stage) &&
                        new Date(o.promised_at) < new Date() && (
                            <>
                                {' '}
                                <Badge stage="Late" />
                            </>
                        )}
                </>
            ),
        },
        {
            label: 'Order total',
            value: (o: Order) => money(o.totals.total, p.settings.currency),
        },
    ];
    return (
        <Shell base={p} section={section} action={newAction}>
            {section === 'dashboard' &&
                p.dashboard &&
                (() => {
                    const d = p.dashboard;
                    const max = Math.max(1, ...d.trend.map((t) => t.net));
                    return (
                        <>
                            <div className="filter-bar">
                                <div className="actions">
                                    {['today', 'week', 'month'].map((v) => (
                                        <Link
                                            key={v}
                                            className={`button ${d.period === v ? '' : 'secondary'}`}
                                            href={`/studio?period=${v}`}
                                        >
                                            {v[0].toUpperCase() + v.slice(1)}
                                        </Link>
                                    ))}
                                </div>
                                <span
                                    className="metric-note"
                                    aria-live="polite"
                                >
                                    {refreshing
                                        ? 'Refreshing dashboard…'
                                        : `Updated ${stamp(d.refreshed_at, p.settings.timezone)}`}
                                </span>
                                <Button
                                    secondary
                                    disabled={refreshing}
                                    onClick={() => reloadPartial(['dashboard'])}
                                >
                                    {refreshing ? 'Refreshing…' : 'Refresh'}
                                </Button>
                            </div>
                            <div className="stats">
                                <Link
                                    className="stat"
                                    href={`/studio/reports?type=revenue&from=${encodeURIComponent(d.period_from)}&to=${encodeURIComponent(d.refreshed_at)}`}
                                >
                                    <small>
                                        Net invoiced revenue · {d.period}
                                    </small>
                                    <strong>
                                        {money(
                                            d.net_revenue,
                                            p.settings.currency,
                                        )}
                                    </strong>
                                    <p>Excluding tax, after credits</p>
                                </Link>
                                <Link
                                    className="stat"
                                    href={`/studio/reports?type=cashflow&from=${encodeURIComponent(d.period_from)}&to=${encodeURIComponent(d.refreshed_at)}`}
                                >
                                    <small>
                                        Net allocated receipts · {d.period}
                                    </small>
                                    <strong>
                                        {money(
                                            d.allocated_receipts - d.refunds,
                                            p.settings.currency,
                                        )}
                                    </strong>
                                    <p>Allocated money less refunds</p>
                                </Link>
                                <Link
                                    className="stat"
                                    href="/studio/reports?type=outstanding"
                                >
                                    <small>Outstanding now</small>
                                    <strong>
                                        {money(
                                            d.outstanding_now,
                                            p.settings.currency,
                                        )}
                                    </strong>
                                    <p>Current receivables across all dates</p>
                                </Link>
                                <Link
                                    className="stat"
                                    href="/studio/orders?late=1"
                                >
                                    <small>Late orders</small>
                                    <strong>{d.late}</strong>
                                    <p>Confirmed, active & past the promise</p>
                                </Link>
                            </div>
                            <div className="two-columns">
                                <Panel
                                    title="Seven-day net invoice trend"
                                    aside={
                                        <Link
                                            className="inline-link"
                                            href="/studio/reports?type=daily"
                                        >
                                            View report →
                                        </Link>
                                    }
                                >
                                    <div
                                        className="chart-bars"
                                        role="img"
                                        aria-label={d.trend
                                            .map(
                                                (t) =>
                                                    `${t.date}: ${money(t.net, p.settings.currency)}`,
                                            )
                                            .join('; ')}
                                    >
                                        {d.trend.map((t) => (
                                            <div
                                                className="chart-column"
                                                key={t.date}
                                            >
                                                <small>
                                                    {money(t.net, '')}
                                                </small>
                                                <div
                                                    className="bar"
                                                    style={{
                                                        height: `${Math.max(1, (t.net / max) * 110)}px`,
                                                    }}
                                                />
                                                <span>{t.date.slice(5)}</span>
                                            </div>
                                        ))}
                                    </div>
                                </Panel>
                                <Panel title="On the cutting table">
                                    <div className="compact-cards">
                                        {d.stages.map((s) => (
                                            <Link
                                                key={s.stage}
                                                href={`/studio/orders?stage=${s.stage}`}
                                            >
                                                <Badge stage={s.stage} />
                                                <strong>{s.count}</strong>
                                            </Link>
                                        ))}
                                    </div>
                                    <p className="metric-note">
                                        {d.created_today} orders created today ·{' '}
                                        {d.delivered_today} delivered today
                                    </p>
                                </Panel>
                            </div>
                            <Panel
                                title="Upcoming & overdue deliveries"
                                aside={
                                    <Link
                                        className="inline-link"
                                        href="/studio/orders"
                                    >
                                        All orders →
                                    </Link>
                                }
                            >
                                <DataTable
                                    rows={d.deliveries}
                                    columns={orderColumns}
                                />
                            </Panel>
                            <div className="three-columns">
                                <Panel title="Customers who return">
                                    <h1>{d.returning_rate}%</h1>
                                    <p className="muted">
                                        {d.returning} returning / {d.population}{' '}
                                        customers with at least one delivered
                                        order.
                                    </p>
                                    <Link
                                        className="inline-link"
                                        href="/studio/customers?inactive=30"
                                    >
                                        Review inactive customers →
                                    </Link>
                                </Panel>
                                <Panel title="Follow-up due">
                                    <h1>{d.follow_ups}</h1>
                                    <p className="muted">
                                        Review active orders before contacting a
                                        customer.
                                    </p>
                                    <Link
                                        className="inline-link"
                                        href="/studio/follow-ups"
                                    >
                                        Open activities →
                                    </Link>
                                </Panel>
                                <Panel title="Not yet billed">
                                    <h2>
                                        {money(d.unbilled, p.settings.currency)}
                                    </h2>
                                    <p className="muted">
                                        Confirmed orders without a posted
                                        invoice.
                                    </p>
                                    <p className="metric-note">
                                        Period discounts:{' '}
                                        {money(
                                            d.discounts,
                                            p.settings.currency,
                                        )}
                                    </p>
                                </Panel>
                            </div>
                        </>
                    );
                })()}
            {section === 'customers' &&
                (() => {
                    const page = p.rows as Page<Customer>;
                    return (
                        <Panel>
                            <Filters section="customers" filters={p.filters}>
                                <select
                                    name="inactive"
                                    aria-label="Inactivity"
                                    defaultValue={p.filters.inactive ?? ''}
                                >
                                    <option value="">All customers</option>
                                    {[30, 60, 90].map((n) => (
                                        <option key={n} value={n}>
                                            {n}+ days inactive
                                        </option>
                                    ))}
                                </select>
                                <label>
                                    <input
                                        type="checkbox"
                                        name="archived"
                                        value="1"
                                        defaultChecked={!!p.filters.archived}
                                    />{' '}
                                    Include archived
                                </label>
                            </Filters>
                            <DataTable
                                rows={page.data}
                                columns={[
                                    {
                                        label: 'Customer',
                                        value: (c) => (
                                            <Link
                                                href={`/studio/customers/${c.id}`}
                                            >
                                                {c.name}
                                            </Link>
                                        ),
                                    },
                                    { label: 'Phone', value: (c) => c.phone },
                                    {
                                        label: 'Completed orders',
                                        value: (c) => c.completed_count ?? 0,
                                    },
                                    {
                                        label: 'Relationship',
                                        value: (c) => (
                                            <Badge
                                                stage={
                                                    !c.completed_count
                                                        ? 'No completed orders'
                                                        : c.completed_count ===
                                                            1
                                                          ? 'One-time'
                                                          : 'Returning'
                                                }
                                            />
                                        ),
                                    },
                                    {
                                        label: 'Status',
                                        value: (c) =>
                                            c.active ? 'Active' : 'Archived',
                                    },
                                ]}
                            />
                            <Pagination page={page} />
                        </Panel>
                    );
                })()}
            {section === 'measurement-search' &&
                (() => {
                    const page = p.measurement_rows;
                    if (!page) return null;
                    return (
                        <>
                            <Panel title="Customer Measurement Search">
                                <Filters
                                    section="measurement-search"
                                    filters={p.filters}
                                />
                                <p className="muted measurement-search-help">
                                    Search by customer name, phone number,
                                    WhatsApp number, or order reference. Latest
                                    confirmed measurements are shown garment by
                                    garment.
                                </p>
                            </Panel>
                            {page.data.length === 0 ? (
                                <Panel>
                                    <div className="empty">
                                        <h3>No saved measurements found</h3>
                                        <p>
                                            Search another customer, or add a
                                            new customer and create their first
                                            stitching order.
                                        </p>
                                        <NewButton
                                            label="New customer"
                                            onClick={() =>
                                                setDialog('customer')
                                            }
                                        />
                                    </div>
                                </Panel>
                            ) : (
                                <div className="measurement-customer-list">
                                    {page.data.map((customer) => (
                                        <Panel
                                            key={customer.id}
                                            title={`${customer.name} · ${customer.phone}`}
                                            aside={
                                                <div className="actions measurement-search-actions">
                                                    <Link
                                                        className="button secondary"
                                                        href={`/studio/customers/${customer.id}`}
                                                    >
                                                        Customer profile
                                                    </Link>
                                                    <Button
                                                        secondary
                                                        onClick={() => {
                                                            setOrderCustomerId(
                                                                customer.id,
                                                            );
                                                            setDialog('order');
                                                        }}
                                                    >
                                                        New stitching order
                                                    </Button>
                                                    {customer.measurements
                                                        .length > 0 && (
                                                        <a
                                                            className="button secondary"
                                                            href={`/studio-document/customer/${customer.id}?scope=measurements`}
                                                        >
                                                            Print all sizes
                                                        </a>
                                                    )}
                                                </div>
                                            }
                                        >
                                            {customer.measurements.length ===
                                            0 ? (
                                                <div className="empty compact-empty">
                                                    <p>
                                                        Customer found, but no
                                                        confirmed measurement is
                                                        saved yet.
                                                    </p>
                                                    <Button
                                                        onClick={() => {
                                                            setOrderCustomerId(
                                                                customer.id,
                                                            );
                                                            setDialog('order');
                                                        }}
                                                    >
                                                        Create first order
                                                    </Button>
                                                </div>
                                            ) : (
                                                <div className="measurement-profile-grid">
                                                    {customer.measurements.map(
                                                        (profile) => (
                                                            <article
                                                                className="measurement-profile-card"
                                                                key={`${customer.id}-${profile.template_id}`}
                                                            >
                                                                <header>
                                                                    <div>
                                                                        <h3>
                                                                            {
                                                                                profile.template_name
                                                                            }
                                                                        </h3>
                                                                        <p className="muted">
                                                                            {
                                                                                profile.garment
                                                                            }{' '}
                                                                            ·{' '}
                                                                            {
                                                                                profile.source_order
                                                                            }{' '}
                                                                            ·{' '}
                                                                            {stamp(
                                                                                profile.source_date,
                                                                                p
                                                                                    .settings
                                                                                    .timezone,
                                                                            )}
                                                                        </p>
                                                                    </div>
                                                                    <div className="actions">
                                                                        <Link
                                                                            className="inline-link"
                                                                            href={`/studio/orders/${profile.source_order_id}`}
                                                                        >
                                                                            Open
                                                                            order
                                                                        </Link>
                                                                        <a
                                                                            className="inline-link"
                                                                            href={`/studio-document/measurements/${profile.source_order_id}`}
                                                                        >
                                                                            Print
                                                                            size
                                                                        </a>
                                                                    </div>
                                                                </header>
                                                                <div className="measurement-piece-list">
                                                                    {profile.pieces.map(
                                                                        (
                                                                            piece,
                                                                        ) => (
                                                                            <details
                                                                                key={
                                                                                    piece.id
                                                                                }
                                                                                className="measurement-piece"
                                                                                open={
                                                                                    profile
                                                                                        .pieces
                                                                                        .length ===
                                                                                    1
                                                                                }
                                                                            >
                                                                                <summary>
                                                                                    {piece.piece
                                                                                        ? `Piece ${piece.piece}`
                                                                                        : 'Shared size'}
                                                                                    {
                                                                                        ' · '
                                                                                    }
                                                                                    Revision{' '}
                                                                                    {
                                                                                        piece.revision
                                                                                    }
                                                                                </summary>
                                                                                <dl className="measurement-values">
                                                                                    {piece.fields.map(
                                                                                        (
                                                                                            field,
                                                                                        ) => (
                                                                                            <div
                                                                                                key={
                                                                                                    field.code
                                                                                                }
                                                                                            >
                                                                                                <dt>
                                                                                                    {
                                                                                                        field.label
                                                                                                    }
                                                                                                </dt>
                                                                                                <dd>
                                                                                                    {measurementValue(
                                                                                                        field,
                                                                                                    )}
                                                                                                    {field.unit
                                                                                                        ? ` ${field.unit}`
                                                                                                        : ''}
                                                                                                </dd>
                                                                                            </div>
                                                                                        ),
                                                                                    )}
                                                                                </dl>
                                                                            </details>
                                                                        ),
                                                                    )}
                                                                </div>
                                                            </article>
                                                        ),
                                                    )}
                                                </div>
                                            )}
                                        </Panel>
                                    ))}
                                </div>
                            )}
                            <Pagination page={page} />
                        </>
                    );
                })()}
            {section === 'orders' &&
                (() => {
                    const page = p.rows as Page<Order>;
                    return (
                        <Panel>
                            <Filters section="orders" filters={p.filters}>
                                <select
                                    name="stage"
                                    aria-label="Stage"
                                    defaultValue={p.filters.stage ?? ''}
                                >
                                    <option value="">All stages</option>
                                    {[
                                        'New',
                                        'Cutting',
                                        'Stitching',
                                        'Ready',
                                        'Delivered',
                                        'Cancelled',
                                    ].map((s) => (
                                        <option key={s}>{s}</option>
                                    ))}
                                </select>
                                <label>
                                    <input
                                        type="checkbox"
                                        name="late"
                                        value="1"
                                        defaultChecked={!!p.filters.late}
                                    />{' '}
                                    Late only
                                </label>
                            </Filters>
                            <DataTable
                                rows={page.data}
                                columns={orderColumns}
                            />
                            <Pagination page={page} />
                        </Panel>
                    );
                })()}
            {section === 'products' && (
                <Panel>
                    <DataTable
                        rows={p.products}
                        columns={[
                            {
                                label: 'Service',
                                value: (v) => <strong>{v.name}</strong>,
                            },
                            { label: 'Category', value: (v) => v.category },
                            {
                                label: 'Default price',
                                value: (v) =>
                                    money(v.price, p.settings.currency),
                            },
                            {
                                label: 'Template',
                                value: (v) =>
                                    p.templates.find(
                                        (t) => t.id === v.template_id,
                                    )?.name,
                            },
                            {
                                label: 'Status',
                                value: (v) =>
                                    v.active ? 'Active' : 'Archived',
                            },
                            {
                                label: 'Manage',
                                value: (v) => (
                                    <Button
                                        secondary
                                        onClick={() => {
                                            setProduct(v);
                                            setDialog('product');
                                        }}
                                    >
                                        Edit
                                    </Button>
                                ),
                            },
                        ]}
                    />
                </Panel>
            )}
            {section === 'templates' && (
                <div className="three-columns">
                    {p.templates.map((t) => (
                        <Panel key={t.id} title={t.name}>
                            <Badge stage={t.active ? 'Active' : 'Archived'} />
                            <p className="muted">
                                {t.fields.length} fields · {t.unit} · Version{' '}
                                {t.revision}
                            </p>
                            <Button
                                secondary
                                onClick={() => {
                                    setTemplate(t);
                                    setDialog('template');
                                }}
                            >
                                Edit template
                            </Button>
                        </Panel>
                    ))}
                </div>
            )}
            {section === 'prices' && (
                <Panel
                    title="Customer-specific stitching prices"
                    aside={
                        <Action
                            label="Add customer price"
                            url="/studio/config/prices"
                            initial={{
                                customer_id: '',
                                product_id: '',
                                price: '0',
                                active: true,
                            }}
                            fields={[
                                {
                                    name: 'customer_id',
                                    label: 'Customer',
                                    type: 'select',
                                    options: opts(p.customers),
                                    required: true,
                                },
                                {
                                    name: 'product_id',
                                    label: 'Service',
                                    type: 'select',
                                    options: opts(p.products),
                                    required: true,
                                },
                                {
                                    name: 'price',
                                    label: 'Price',
                                    type: 'number',
                                    required: true,
                                },
                                {
                                    name: 'active',
                                    label: 'Enabled',
                                    type: 'checkbox',
                                },
                            ]}
                        />
                    }
                >
                    <DataTable
                        rows={p.prices ?? []}
                        columns={[
                            {
                                label: 'Customer',
                                value: (r) =>
                                    p.customers.find(
                                        (c) => c.id === r.customer_id,
                                    )?.name,
                            },
                            {
                                label: 'Service',
                                value: (r) =>
                                    p.products.find(
                                        (c) => c.id === r.product_id,
                                    )?.name,
                            },
                            {
                                label: 'Price',
                                value: (r) =>
                                    money(r.price, p.settings.currency),
                            },
                            {
                                label: 'Status',
                                value: (r) =>
                                    r.active ? 'Enabled' : 'Disabled',
                            },
                            {
                                label: 'Edit',
                                value: (r) => (
                                    <Action
                                        label="Edit"
                                        url={`/studio/config/prices/${r.id}`}
                                        initial={{
                                            ...r,
                                            price: decimal(r.price),
                                        }}
                                        fields={[
                                            {
                                                name: 'price',
                                                label: 'Price',
                                                type: 'number',
                                                required: true,
                                            },
                                            {
                                                name: 'active',
                                                label: 'Enabled',
                                                type: 'checkbox',
                                            },
                                        ]}
                                    />
                                ),
                            },
                        ]}
                    />
                </Panel>
            )}
            {section === 'billing' &&
                (() => {
                    const page = p.rows as Page<Invoice>;
                    return (
                        <>
                            <Panel
                                title="Invoices"
                                aside={
                                    <Action
                                        label="Record payment / advance"
                                        url="/studio/finance/payment"
                                        initial={{
                                            customer_id: '',
                                            amount: '',
                                            payment_method_id:
                                                p.methods[0]?.id ?? '',
                                            settlement: 'settled',
                                            effective_at: localNow(
                                                p.settings.timezone,
                                            ),
                                            external_reference: '',
                                        }}
                                        fields={paymentFields(p)}
                                    >
                                        <p className="wide muted">
                                            This records unallocated customer
                                            funds. Open the customer order to
                                            allocate settled funds to its
                                            invoice.
                                        </p>
                                    </Action>
                                }
                            >
                                <DataTable
                                    rows={page.data}
                                    columns={[
                                        {
                                            label: 'Invoice',
                                            value: (i) => (
                                                <Link
                                                    href={`/studio/orders/${i.order_id}`}
                                                >
                                                    {i.reference}
                                                </Link>
                                            ),
                                        },
                                        {
                                            label: 'Customer',
                                            value: (i) => i.contact.name,
                                        },
                                        {
                                            label: 'Status',
                                            value: (i) => (
                                                <Badge stage={i.state} />
                                            ),
                                        },
                                        {
                                            label: 'Amount',
                                            value: (i) =>
                                                money(
                                                    i.totals.total,
                                                    p.settings.currency,
                                                ),
                                        },
                                        {
                                            label: 'Due',
                                            value: (i) =>
                                                money(
                                                    i.due ?? 0,
                                                    p.settings.currency,
                                                ),
                                        },
                                        {
                                            label: 'Print',
                                            value: (i) => (
                                                <a
                                                    href={`/studio-document/invoice/${i.id}`}
                                                >
                                                    PDF
                                                </a>
                                            ),
                                        },
                                    ]}
                                />
                                <Pagination page={page} />
                            </Panel>
                            <Panel title="Latest payments & advances">
                                <DataTable
                                    rows={p.payments ?? []}
                                    columns={[
                                        {
                                            label: 'Receipt',
                                            value: (v) => (
                                                <a
                                                    href={`/studio-document/receipt/${v.id}`}
                                                >
                                                    {v.reference}
                                                </a>
                                            ),
                                        },
                                        {
                                            label: 'Customer',
                                            value: (v) =>
                                                p.customers.find(
                                                    (c) =>
                                                        c.id === v.customer_id,
                                                )?.name,
                                        },
                                        {
                                            label: 'Amount',
                                            value: (v) =>
                                                money(
                                                    v.amount,
                                                    p.settings.currency,
                                                ),
                                        },
                                        {
                                            label: 'Unallocated',
                                            value: (v) =>
                                                money(
                                                    v.available,
                                                    p.settings.currency,
                                                ),
                                        },
                                        {
                                            label: 'Settlement',
                                            value: (v) => (
                                                <Badge stage={v.settlement} />
                                            ),
                                        },
                                        {
                                            label: 'Manage',
                                            value: (v) =>
                                                v.settlement === 'pending' ? (
                                                    <Action
                                                        label="Mark bank settled"
                                                        url={`/studio/finance/settle/${v.id}`}
                                                        initial={{
                                                            version: v.version,
                                                            effective_at:
                                                                localNow(
                                                                    p.settings
                                                                        .timezone,
                                                                ),
                                                            reason: '',
                                                        }}
                                                        fields={dateFields}
                                                    />
                                                ) : (
                                                    <Link
                                                        href={`/studio/customers/${v.customer_id}`}
                                                    >
                                                        Customer history →
                                                    </Link>
                                                ),
                                        },
                                    ]}
                                />
                            </Panel>
                        </>
                    );
                })()}
            {section === 'follow-ups' &&
                (() => {
                    const page = p.rows as Page<Followup>;
                    const fields: InputSpec[] = [
                        {
                            name: 'customer_id',
                            label: 'Customer',
                            type: 'select',
                            options: opts(p.customers),
                            required: true,
                        },
                        {
                            name: 'due_at',
                            label: 'Due date and time',
                            type: 'datetime-local',
                            required: true,
                        },
                        {
                            name: 'note',
                            label: 'Activity note',
                            type: 'textarea',
                            required: true,
                        },
                        {
                            name: 'completed',
                            label: 'Completed',
                            type: 'checkbox',
                        },
                    ];
                    return (
                        <Panel
                            title="Owner activities"
                            aside={
                                <Action
                                    label="Add follow-up"
                                    url="/studio/follow-ups"
                                    initial={{
                                        customer_id: '',
                                        due_at: localNow(p.settings.timezone),
                                        note: '',
                                        completed: false,
                                    }}
                                    fields={fields}
                                />
                            }
                        >
                            <DataTable
                                rows={page.data}
                                columns={[
                                    {
                                        label: 'Customer',
                                        value: (v) => (
                                            <Link
                                                href={`/studio/customers/${v.customer_id}`}
                                            >
                                                {v.name}
                                            </Link>
                                        ),
                                    },
                                    {
                                        label: 'Due',
                                        value: (v) =>
                                            stamp(
                                                v.due_at,
                                                p.settings.timezone,
                                            ),
                                    },
                                    { label: 'Note', value: (v) => v.note },
                                    {
                                        label: 'Status',
                                        value: (v) =>
                                            v.completed_at
                                                ? 'Completed'
                                                : 'Open',
                                    },
                                    {
                                        label: 'Manage',
                                        value: (v) => (
                                            <Action
                                                label="Update"
                                                url={`/studio/follow-ups/${v.id}`}
                                                initial={{
                                                    ...v,
                                                    completed: !!v.completed_at,
                                                }}
                                                fields={fields}
                                            />
                                        ),
                                    },
                                ]}
                            />
                            <Pagination page={page} />
                        </Panel>
                    );
                })()}
            {section === 'configuration' && (
                <>
                    <Panel title="Business settings">
                        <Form
                            url="/studio/config/business/1"
                            initial={
                                JSON.parse(
                                    JSON.stringify(p.settings),
                                ) as Payload
                            }
                            fields={[
                                {
                                    name: 'name',
                                    label: 'Business name',
                                    required: true,
                                },
                                { name: 'phone', label: 'Business phone' },
                                {
                                    name: 'address',
                                    label: 'Business address',
                                    type: 'textarea',
                                },
                                {
                                    name: 'currency',
                                    label: 'Currency code',
                                    required: true,
                                    help: 'Two decimal places; currency is locked after invoicing.',
                                },
                                {
                                    name: 'timezone',
                                    label: 'Timezone',
                                    required: true,
                                },
                                {
                                    name: 'country',
                                    label: 'Phone country code (PK, US…)',
                                    required: true,
                                },
                                {
                                    name: 'recent_days',
                                    label: 'Recent / retention window (days)',
                                    type: 'number',
                                    required: true,
                                },
                                {
                                    name: 'refresh_seconds',
                                    label: 'Dashboard refresh interval (seconds)',
                                    type: 'number',
                                    required: true,
                                },
                                {
                                    name: 'credit_delivery',
                                    label: 'Allow delivery on credit with a reason',
                                    type: 'checkbox',
                                },
                                {
                                    name: 'cost_reporting',
                                    label: 'Enable direct-cost margin reporting',
                                    type: 'checkbox',
                                },
                                {
                                    name: 'whatsapp_template',
                                    label: 'WhatsApp message template',
                                    type: 'textarea',
                                    required: true,
                                    help: 'Use {name}, {order}, and {stage}. Messages are never sent automatically.',
                                },
                                {
                                    name: 'invoice_footer',
                                    label: 'Invoice footer',
                                    type: 'textarea',
                                },
                            ]}
                        />
                    </Panel>
                    <Panel title="Business logo">
                        <LogoUpload version={p.settings.version} />
                    </Panel>
                    <Panel
                        title="Payment methods"
                        aside={
                            <Action
                                label="Add method"
                                url="/studio/config/methods"
                                initial={{
                                    name: '',
                                    kind: 'cash',
                                    active: true,
                                }}
                                fields={[
                                    {
                                        name: 'name',
                                        label: 'Method name',
                                        required: true,
                                    },
                                    {
                                        name: 'kind',
                                        label: 'Kind',
                                        type: 'select',
                                        options: [
                                            { value: 'cash', label: 'Cash' },
                                            {
                                                value: 'bank',
                                                label: 'Manual bank transfer',
                                            },
                                        ],
                                        required: true,
                                    },
                                    {
                                        name: 'active',
                                        label: 'Active',
                                        type: 'checkbox',
                                    },
                                ]}
                            />
                        }
                    >
                        <DataTable
                            rows={p.methods}
                            columns={[
                                { label: 'Name', value: (m) => m.name },
                                { label: 'Kind', value: (m) => m.kind },
                                {
                                    label: 'Manage',
                                    value: (m) => (
                                        <Action
                                            label="Edit"
                                            url={`/studio/config/methods/${m.id}`}
                                            initial={{ ...m }}
                                            fields={[
                                                {
                                                    name: 'name',
                                                    label: 'Method name',
                                                    required: true,
                                                },
                                                {
                                                    name: 'kind',
                                                    label: 'Kind',
                                                    type: 'select',
                                                    options: [
                                                        {
                                                            value: 'cash',
                                                            label: 'Cash',
                                                        },
                                                        {
                                                            value: 'bank',
                                                            label: 'Manual bank transfer',
                                                        },
                                                    ],
                                                },
                                                {
                                                    name: 'active',
                                                    label: 'Active',
                                                    type: 'checkbox',
                                                },
                                            ]}
                                        />
                                    ),
                                },
                            ]}
                        />
                    </Panel>
                </>
            )}
            {section === 'reports' && (
                <Panel title="Business reports">
                    <form
                        className="report-filter-form"
                        action="/studio/reports"
                    >
                        <div className="filter-bar report-filter-primary">
                            <select
                                name="type"
                                aria-label="Report type"
                                defaultValue={p.filters.type ?? 'orders'}
                            >
                                {[
                                    'orders',
                                    'revenue',
                                    'cashflow',
                                    'items',
                                    'customers',
                                    'payments',
                                    'discounts',
                                    'delivery',
                                    'outstanding',
                                    'credits',
                                    ...(p.settings.cost_reporting
                                        ? ['profit']
                                        : []),
                                    'daily',
                                    'weekly',
                                    'monthly',
                                    'invoices',
                                ].map((t) => (
                                    <option key={t}>{t}</option>
                                ))}
                            </select>
                            <input
                                name="q"
                                aria-label="Search order or customer"
                                placeholder="Order / customer search"
                                defaultValue={p.filters.q ?? ''}
                            />
                            <select
                                name="customer_id"
                                aria-label="Customer"
                                defaultValue={p.filters.customer_id ?? ''}
                            >
                                <option value="">All customers</option>
                                {p.customers.map((c) => (
                                    <option key={c.id} value={c.id}>
                                        {c.name}
                                    </option>
                                ))}
                            </select>
                            <select
                                name="product_id"
                                aria-label="Product or service"
                                defaultValue={p.filters.product_id ?? ''}
                            >
                                <option value="">
                                    All products / services
                                </option>
                                {p.products.map((product) => (
                                    <option key={product.id} value={product.id}>
                                        {product.name}
                                    </option>
                                ))}
                            </select>
                            <select
                                name="stage"
                                aria-label="Workflow state"
                                defaultValue={p.filters.stage ?? ''}
                            >
                                <option value="">All workflow states</option>
                                {[
                                    'New',
                                    'Cutting',
                                    'Stitching',
                                    'Ready',
                                    'Delivered',
                                    'Cancelled',
                                ].map((stage) => (
                                    <option key={stage} value={stage}>
                                        {stage}
                                    </option>
                                ))}
                            </select>
                            <select
                                name="billing_status"
                                aria-label="Billing status"
                                defaultValue={p.filters.billing_status ?? ''}
                            >
                                <option value="">Any billing status</option>
                                <option value="unbilled">Unbilled</option>
                                <option value="draft">Draft invoice</option>
                                <option value="posted">Posted invoice</option>
                            </select>
                            <select
                                name="payment_status"
                                aria-label="Balance status"
                                defaultValue={p.filters.payment_status ?? ''}
                            >
                                <option value="">Any balance status</option>
                                <option value="unpaid">Unpaid</option>
                                <option value="partial">
                                    Partially settled
                                </option>
                                <option value="paid">Fully settled</option>
                                <option value="credited">
                                    Has credit settlement
                                </option>
                            </select>
                            <Button type="submit">Run report</Button>
                            <Link href="/studio/reports">Reset</Link>
                        </div>
                        <details
                            className="advanced-report-filters"
                            open={[
                                'from',
                                'to',
                                'cutoff',
                                'order_from',
                                'order_to',
                                'invoice_from',
                                'invoice_to',
                                'payment_from',
                                'payment_to',
                                'promised_from',
                                'promised_to',
                                'delivered_from',
                                'delivered_to',
                            ].some((key) => Boolean(p.filters[key]))}
                        >
                            <summary>Advanced date filters</summary>
                            <p className="metric-note">
                                Event dates apply to the selected report. The
                                specific ranges below constrain their named
                                business dates without changing the historical
                                cutoff.
                            </p>
                            <div className="report-date-grid">
                                <label>
                                    Event from
                                    <input
                                        type="datetime-local"
                                        name="from"
                                        defaultValue={p.filters.from ?? ''}
                                    />
                                </label>
                                <label>
                                    Event to
                                    <input
                                        type="datetime-local"
                                        name="to"
                                        defaultValue={p.filters.to ?? ''}
                                    />
                                </label>
                                <label>
                                    Order date from
                                    <input
                                        type="datetime-local"
                                        name="order_from"
                                        defaultValue={
                                            p.filters.order_from ?? ''
                                        }
                                    />
                                </label>
                                <label>
                                    Order date to
                                    <input
                                        type="datetime-local"
                                        name="order_to"
                                        defaultValue={p.filters.order_to ?? ''}
                                    />
                                </label>
                                <label>
                                    Invoice date from
                                    <input
                                        type="datetime-local"
                                        name="invoice_from"
                                        defaultValue={
                                            p.filters.invoice_from ?? ''
                                        }
                                    />
                                </label>
                                <label>
                                    Invoice date to
                                    <input
                                        type="datetime-local"
                                        name="invoice_to"
                                        defaultValue={
                                            p.filters.invoice_to ?? ''
                                        }
                                    />
                                </label>
                                <label>
                                    Payment date from
                                    <input
                                        type="datetime-local"
                                        name="payment_from"
                                        defaultValue={
                                            p.filters.payment_from ?? ''
                                        }
                                    />
                                </label>
                                <label>
                                    Payment date to
                                    <input
                                        type="datetime-local"
                                        name="payment_to"
                                        defaultValue={
                                            p.filters.payment_to ?? ''
                                        }
                                    />
                                </label>
                                <label>
                                    Promised delivery from
                                    <input
                                        type="datetime-local"
                                        name="promised_from"
                                        defaultValue={
                                            p.filters.promised_from ?? ''
                                        }
                                    />
                                </label>
                                <label>
                                    Promised delivery to
                                    <input
                                        type="datetime-local"
                                        name="promised_to"
                                        defaultValue={
                                            p.filters.promised_to ?? ''
                                        }
                                    />
                                </label>
                                <label>
                                    Actual delivery from
                                    <input
                                        type="datetime-local"
                                        name="delivered_from"
                                        defaultValue={
                                            p.filters.delivered_from ?? ''
                                        }
                                    />
                                </label>
                                <label>
                                    Actual delivery to
                                    <input
                                        type="datetime-local"
                                        name="delivered_to"
                                        defaultValue={
                                            p.filters.delivered_to ?? ''
                                        }
                                    />
                                </label>
                                <label>
                                    Historical cutoff
                                    <input
                                        type="datetime-local"
                                        name="cutoff"
                                        defaultValue={p.filters.cutoff ?? ''}
                                    />
                                </label>
                            </div>
                        </details>
                    </form>
                    <div className="actions">
                        {['csv', 'xlsx'].map((format) => (
                            <Link
                                method="post"
                                as="button"
                                key={format}
                                className="button secondary"
                                href={`/studio-export/${p.filters.type ?? 'orders'}?${new URLSearchParams({ ...p.filters, format }).toString()}`}
                            >
                                Export {format.toUpperCase()}
                            </Link>
                        ))}
                        <a
                            className="button secondary"
                            href={`/studio-report-pdf/${p.filters.type ?? 'orders'}?${new URLSearchParams(p.filters).toString()}`}
                        >
                            Internal PDF
                        </a>
                    </div>
                    <p className="metric-note">
                        Showing up to 200 of {p.report_count} matching rows.
                        Export includes all matching rows. Financial figures are
                        in {p.settings.currency}.
                    </p>
                    <DataTable
                        rows={p.report_rows ?? []}
                        columns={Object.keys(p.report_rows?.[0] ?? {}).map(
                            (key) => ({
                                label: key
                                    .replace('_minor', '')
                                    .replaceAll('_', ' '),
                                value: (r: Record<string, unknown>) =>
                                    key.endsWith('_minor') &&
                                    typeof r[key] === 'number'
                                        ? money(r[key], p.settings.currency)
                                        : reportText(r[key]),
                            }),
                        )}
                    />
                </Panel>
            )}
            {section === 'exports' && (
                <Panel title="Private report downloads">
                    <Button
                        secondary
                        disabled={refreshing}
                        onClick={() => reloadPartial(['export_jobs'])}
                    >
                        {refreshing ? 'Refreshing…' : 'Refresh status'}
                    </Button>
                    <span
                        className="metric-note"
                        role="status"
                        aria-live="polite"
                    >
                        {refreshing ? 'Checking export status…' : ''}
                    </span>
                    <DataTable
                        rows={p.export_jobs ?? []}
                        columns={[
                            {
                                label: 'Report',
                                value: (j) =>
                                    `${j.report} · ${j.format.toUpperCase()}`,
                            },
                            {
                                label: 'Requested',
                                value: (j) =>
                                    stamp(j.created_at, p.settings.timezone),
                            },
                            {
                                label: 'State',
                                value: (j) => <Badge stage={j.state} />,
                            },
                            { label: 'Rows', value: (j) => j.row_count ?? '—' },
                            {
                                label: 'Download',
                                value: (j) =>
                                    j.state === 'ready' ? (
                                        <a href={`/studio-download/${j.id}`}>
                                            Download file
                                        </a>
                                    ) : j.state === 'failed' ? (
                                        <>
                                            <p>{j.error}</p>
                                            <Action
                                                label="Retry"
                                                url={`/studio-exports/${j.id}/retry`}
                                                initial={{}}
                                            />
                                        </>
                                    ) : (
                                        <span>Waiting for queue worker</span>
                                    ),
                            },
                        ]}
                    />
                </Panel>
            )}
            {section === 'audit' &&
                (() => {
                    const page = p.rows as Page<Event>;
                    return (
                        <Panel>
                            <DataTable
                                rows={page.data}
                                columns={[
                                    {
                                        label: 'Action',
                                        value: (e) =>
                                            e.action.replaceAll('_', ' '),
                                    },
                                    {
                                        label: 'Date',
                                        value: (e) =>
                                            stamp(
                                                e.created_at,
                                                p.settings.timezone,
                                            ),
                                    },
                                    {
                                        label: 'Details',
                                        value: (e) => (
                                            <details>
                                                <summary>View changes</summary>
                                                <pre>
                                                    {JSON.stringify(
                                                        e.changes,
                                                        null,
                                                        2,
                                                    )}
                                                </pre>
                                            </details>
                                        ),
                                    },
                                ]}
                            />
                            <Pagination page={page} />
                        </Panel>
                    );
                })()}
            {dialog && (
                <Modal
                    title={
                        dialog === 'order'
                            ? 'New stitching order'
                            : dialog === 'customer'
                              ? 'New customer'
                              : dialog === 'product'
                                ? 'Stitching service'
                                : 'Measurement template'
                    }
                    onClose={() => {
                        setOrderCustomerId(undefined);
                        setDialog(null);
                    }}
                >
                    {dialog === 'order' ? (
                        <NewOrder
                            base={p}
                            initialCustomerId={orderCustomerId}
                            onDone={() => {
                                setOrderCustomerId(undefined);
                                setDialog(null);
                            }}
                        />
                    ) : dialog === 'customer' ? (
                        <Form
                            url="/studio/customers"
                            initial={customerDefaults}
                            fields={customerFields}
                            onDone={() => setDialog(null)}
                        />
                    ) : dialog === 'product' ? (
                        <ProductEditor
                            base={p}
                            product={product}
                            onDone={() => setDialog(null)}
                        />
                    ) : (
                        <TemplateEditor
                            template={template}
                            onDone={() => setDialog(null)}
                        />
                    )}
                </Modal>
            )}
        </Shell>
    );
}

function measurementValue(field: import('./types').Field): string {
    if (!field.is_set || field.value === null || field.value === undefined)
        return '—';
    if (field.type === 'choice') {
        return (
            field.options.find((option) => option.code === field.value)
                ?.label ?? String(field.value)
        );
    }
    return String(field.value);
}

function LogoUpload({ version }: { version: number }) {
    const f = useForm<{ logo: File | null; version: number }>({
        logo: null,
        version,
    });
    const submissionLock = useRef(false);
    const fileRef = useRef<HTMLInputElement>(null);
    const formRef = useFirstErrorFocus(f.errors);
    useUnsavedChanges(f.data.logo !== null);
    return (
        <form
            ref={formRef}
            className="logo-form"
            aria-busy={f.processing}
            onSubmit={(e) => {
                e.preventDefault();
                if (f.processing || submissionLock.current) return;
                submissionLock.current = true;
                allowNextUnsavedNavigation();
                f.post('/studio/logo', {
                    forceFormData: true,
                    onSuccess: () => {
                        f.setData('version', (f.data.version ?? version) + 1);
                        f.reset('logo');
                        if (fileRef.current) fileRef.current.value = '';
                        router.reload({ only: ['settings'] });
                    },
                    onFinish: () => {
                        submissionLock.current = false;
                    },
                });
            }}
        >
            <input
                ref={fileRef}
                type="file"
                accept="image/png,image/jpeg"
                required
                aria-label="Upload business logo"
                aria-invalid={Boolean(f.errors.logo) || undefined}
                aria-describedby={
                    f.errors.logo ? 'logo-upload-error' : undefined
                }
                onChange={(e) => f.setData('logo', e.target.files?.[0] ?? null)}
            />
            {f.errors.logo && (
                <em className="field-error" id="logo-upload-error">
                    {f.errors.logo}
                </em>
            )}
            <Button type="submit" disabled={f.processing}>
                {f.processing ? 'Uploading…' : 'Upload PNG / JPEG'}
            </Button>
            <small>Up to 2 MB. Stored privately for documents.</small>
            <ErrorSummary errors={f.errors} title="Logo upload failed." />
        </form>
    );
}

function reportText(value: unknown): string {
    if (value === null || value === undefined) return '—';
    if (
        typeof value === 'string' ||
        typeof value === 'number' ||
        typeof value === 'boolean'
    )
        return String(value);
    return JSON.stringify(value);
}
