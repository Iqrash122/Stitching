import { Link } from '@inertiajs/react';
import { useState } from 'react';
import type {
    Base,
    Customer,
    Order,
    Invoice,
    Payment,
    Followup,
    Page,
} from './types';
import {
    Shell,
    Panel,
    Action,
    Badge,
    DataTable,
    Pagination,
    money,
    stamp,
    Button,
    Modal,
} from './ui';
import { customerFields } from './index';
interface Props extends Base {
    customer: Customer;
    stats: {
        since: string;
        confirmed_count: number;
        completed_count: number;
        classification: string;
        latest_confirmed: string;
        latest_completed: string;
        days_since_confirmed: number | null;
        days_since_completed: number | null;
        net_spending: number;
        allocated: number;
        due: number;
        active_orders: Order[];
    };
    orders: Page<Order>;
    invoices: Invoice[];
    payments: Payment[];
    followups: Followup[];
}
export default function CustomerPage(p: Props) {
    const c = p.customer;
    const s = p.stats;
    const [wa, setWa] = useState(false);
    const order = s.active_orders[0]; const template =
        p.settings?.whatsapp_template ??
        'Hello {name}, your order {order} is currently {stage}.';

    const text = template
        .replaceAll('{name}', c?.name ?? '')
        .replaceAll('{order}', order?.reference ?? '')
        .replaceAll('{stage}', order?.stage ?? '');
    const number =
        (
            c as Customer & {
                normalized_phone: string;
                normalized_whatsapp?: string;
            }
        ).normalized_whatsapp ||
        (c as Customer & { normalized_phone: string }).normalized_phone;
    return (
        <Shell
            base={p}
            section="customers"
            title={c.name}
            action={
                <div className="actions">
                    <Action
                        label="Edit customer"
                        url={`/studio/customers/${c.id}`}
                        initial={{
                            id: c.id,
                            version: c.version,
                            name: c.name,
                            phone: c.phone,
                            whatsapp: c.whatsapp ?? '',
                            address: c.address ?? '',
                            notes: c.notes ?? '',
                            active: c.active,
                        }}
                        fields={customerFields}
                    />
                    <Button secondary onClick={() => setWa(true)}>
                        WhatsApp preview
                    </Button>
                </div>
            }
        >
            <div className="stats">
                <div className="stat">
                    <small>Customer relationship</small>
                    <strong>{s.completed_count}</strong>
                    <Badge stage={s.classification} />
                </div>
                <div className="stat">
                    <small>Net invoiced spending</small>
                    <strong>
                        {money(s.net_spending, p.settings.currency)}
                    </strong>
                    <p>Excluding tax, after credits</p>
                </div>
                <div className="stat">
                    <small>Allocated payments</small>
                    <strong>{money(s.allocated, p.settings.currency)}</strong>
                </div>
                <div className="stat">
                    <small>Outstanding balance</small>
                    <strong>{money(s.due, p.settings.currency)}</strong>
                </div>
            </div>
            <div className="two-columns">
                <Panel title="Customer details">
                    <p>
                        <strong>{c.phone}</strong>
                        {c.whatsapp && ` · WhatsApp ${c.whatsapp}`}
                    </p>
                    <p>{c.address}</p>
                    <p className="muted">
                        Customer since {stamp(s.since, p.settings.timezone)}
                    </p>
                    <p>Private notes: {c.notes || 'None'}</p>
                </Panel>
                <Panel title="Order history at a glance">
                    <div className="summary-list">
                        <div>
                            <span>Confirmed orders</span>
                            <strong>{s.confirmed_count}</strong>
                        </div>
                        <div>
                            <span>Latest confirmed</span>
                            <span>
                                {s.latest_confirmed ?? '—'} ·{' '}
                                {s.days_since_confirmed ?? '—'} days ago
                            </span>
                        </div>
                        <div>
                            <span>Latest completed</span>
                            <span>
                                {s.latest_completed ?? '—'} ·{' '}
                                {s.days_since_completed ?? '—'} days ago
                            </span>
                        </div>
                    </div>
                    <div className="actions" style={{ marginTop: 20 }}>
                        {['orders', 'billing', 'measurements'].map((scope) => (
                            <a
                                key={scope}
                                className="button secondary"
                                href={`/studio-document/customer/${c.id}?scope=${scope}`}
                            >
                                {scope} PDF
                            </a>
                        ))}
                    </div>
                </Panel>
            </div>
            <Panel title="Active orders — review before contacting">
                <DataTable
                    rows={s.active_orders}
                    columns={[
                        {
                            label: 'Order',
                            value: (o) => (
                                <Link href={`/studio/orders/${o.id}`}>
                                    {o.reference}
                                </Link>
                            ),
                        },
                        {
                            label: 'Stage',
                            value: (o) => <Badge stage={o.stage} />,
                        },
                        {
                            label: 'Promised delivery',
                            value: (o) =>
                                stamp(o.promised_at, p.settings.timezone),
                        },
                    ]}
                />
            </Panel>
            <Panel title="All orders & measurement history">
                <DataTable
                    rows={p.orders.data}
                    columns={[
                        {
                            label: 'Order',
                            value: (o) => (
                                <Link href={`/studio/orders/${o.id}`}>
                                    {o.reference}
                                </Link>
                            ),
                        },
                        {
                            label: 'Date',
                            value: (o) =>
                                stamp(o.ordered_at, p.settings.timezone),
                        },
                        {
                            label: 'Stage',
                            value: (o) => <Badge stage={o.stage} />,
                        },
                        {
                            label: 'Total',
                            value: (o) =>
                                money(o.totals.total, p.settings.currency),
                        },
                        {
                            label: 'Measurements',
                            value: (o) => (
                                <a
                                    href={`/studio-document/measurements/${o.id}`}
                                >
                                    Print sheet
                                </a>
                            ),
                        },
                    ]}
                />
                <Pagination page={p.orders} />
            </Panel>
            <Panel title="Invoices">
                <DataTable
                    rows={p.invoices}
                    columns={[
                        {
                            label: 'Invoice',
                            value: (i) => (
                                <Link href={`/studio/orders/${i.order_id}`}>
                                    {i.reference}
                                </Link>
                            ),
                        },
                        {
                            label: 'State',
                            value: (i) => <Badge stage={i.state} />,
                        },
                        {
                            label: 'Total',
                            value: (i) =>
                                money(i.totals.total, p.settings.currency),
                        },
                        {
                            label: 'Due',
                            value: (i) =>
                                money(i.due ?? 0, p.settings.currency),
                        },
                    ]}
                />
            </Panel>
            <Panel title="Payment receipts">
                <DataTable
                    rows={p.payments}
                    columns={[
                        {
                            label: 'Receipt',
                            value: (v) => (
                                <a href={`/studio-document/receipt/${v.id}`}>
                                    {v.reference}
                                </a>
                            ),
                        },
                        {
                            label: 'Amount',
                            value: (v) => money(v.amount, p.settings.currency),
                        },
                        {
                            label: 'Settlement',
                            value: (v) => <Badge stage={v.settlement} />,
                        },
                    ]}
                />
            </Panel>
            <Panel title="Follow-up activities">
                <DataTable
                    rows={p.followups}
                    columns={[
                        {
                            label: 'Due',
                            value: (v) => stamp(v.due_at, p.settings.timezone),
                        },
                        { label: 'Note', value: (v) => v.note },
                        {
                            label: 'State',
                            value: (v) =>
                                v.completed_at ? 'Completed' : 'Open',
                        },
                    ]}
                />
                <Link className="inline-link" href="/studio/follow-ups">
                    Manage activities →
                </Link>
            </Panel>
            {wa && (
                <Modal
                    title="Review WhatsApp message"
                    onClose={() => setWa(false)}
                >
                    <div className="editor">
                        <p className="wide muted">
                            {s.active_orders.length} active orders. Opening
                            WhatsApp does not send the message.
                        </p>
                        <p className="wide">{text}</p>
                        <div className="form-end">
                            <a
                                className="button"
                                href={`https://wa.me/${number.replace(/\D/g, '')}?text=${encodeURIComponent(text)}`}
                                target="_blank"
                                rel="noreferrer"
                            >
                                Open WhatsApp
                            </a>
                        </div>
                    </div>
                </Modal>
            )}
        </Shell>
    );
}
