import { Link, useForm } from '@inertiajs/react';
import { useRef, useState } from 'react';
import { NewOrder } from './editors';
import type {
    Base,
    Order,
    Snapshot,
    Allocation,
    Credit,
    Event,
    Field,
    Payment,
} from './types';
import {
    Shell,
    Panel,
    Button,
    Action,
    Badge,
    Modal,
    money,
    decimal,
    stamp,
    localNow,
    DataTable,
} from './ui';
import type { InputSpec } from './ui';
import {
    allowNextUnsavedNavigation,
    ErrorSummary,
    FieldError,
    fieldErrorProps,
    useFirstErrorFocus,
    useUnsavedChanges,
} from './form-support';
interface Props extends Base {
    order: Order;
    previous: Record<string, Snapshot[]>;
    due: number | null;
    allocations: Allocation[];
    credits: Credit[];
    refunds: {
        id: number;
        reference: string;
        amount: number;
        effective_at: string;
    }[];
    events: Event[];
    available_payments: Payment[];
}
export default function OrderPage(p: Props) {
    const o = p.order;
    const invoice = o.invoice;
    const closed = ['Delivered', 'Cancelled'].includes(o.stage);
    const [snapshot, setSnapshot] = useState<Snapshot | null>(null);
    const [creditOpen, setCreditOpen] = useState(false);
    const [editDraft, setEditDraft] = useState(false);
    const next: Record<string, string> = {
        New: 'Cutting',
        Cutting: 'Stitching',
        Stitching: 'Ready',
        Ready: 'Delivered',
    };
    const when = () => localNow(p.settings.timezone);
    const date: InputSpec = {
        name: 'effective_at',
        label: 'Financial date and time',
        type: 'datetime-local',
        required: true,
    };
    const reason: InputSpec = {
        name: 'reason',
        label: 'Reason',
        type: 'textarea',
        required: true,
    };
    const paymentFields: InputSpec[] = [
        {
            name: 'amount',
            label: `Amount (${p.settings.currency})`,
            type: 'number',
            required: true,
        },
        {
            name: 'payment_method_id',
            label: 'Payment method',
            type: 'select',
            required: true,
            options: p.methods.map((m) => ({ value: m.id, label: m.name })),
        },
        date,
        { name: 'external_reference', label: 'Reference (optional)' },
    ];
    return (
        <Shell
            base={p}
            section="orders"
            title={o.reference}
            action={
                <div className="actions">
                    <a
                        className="button secondary"
                        href={`/studio-document/order/${o.id}`}
                    >
                        Order PDF
                    </a>
                    <a
                        className="button secondary"
                        href={`/studio-document/measurements/${o.id}`}
                    >
                        Measurement PDF
                    </a>
                    <a
                        className="button secondary"
                        href={`/studio-document/tailor/${o.id}`}
                    >
                        Tailor Job Sheet
                    </a>
                    {!o.confirmed_at && !closed && (
                        <Button secondary onClick={() => setEditDraft(true)}>
                            Edit draft
                        </Button>
                    )}
                    <Action
                        label="Repeat order"
                        url={`/studio/orders/${o.id}/repeat`}
                        initial={{ pricing: 'current', promised_at: when() }}
                        fields={[
                            {
                                name: 'pricing',
                                label: 'Pricing choice',
                                type: 'select',
                                required: true,
                                options: [
                                    {
                                        value: 'current',
                                        label: 'Current service / customer pricing',
                                    },
                                    {
                                        value: 'previous',
                                        label: 'Previous order prices',
                                    },
                                ],
                            },
                            {
                                name: 'promised_at',
                                label: 'New promised delivery',
                                type: 'datetime-local',
                                required: true,
                            },
                        ]}
                    >
                        <p className="wide muted">
                            Creates an independent draft with copied compatible
                            measurements. Original history stays intact.
                        </p>
                    </Action>
                </div>
            }
        >
            <Panel>
                <div className="panel-heading">
                    <div>
                        <Badge stage={o.confirmed_at ? o.stage : 'Draft'} />
                        {closed && (
                            <p className="muted">
                                This production order is closed. Billing history
                                remains available.
                            </p>
                        )}
                    </div>
                    <Link
                        href={`/studio/customers/${o.customer_id}`}
                        className="inline-link"
                    >
                        Customer history →
                    </Link>
                </div>
                <div className="flow">
                    {['New', 'Cutting', 'Stitching', 'Ready', 'Delivered'].map(
                        (s) => (
                            <span
                                key={s}
                                className={`step ${s === o.stage ? 'current' : ''}`}
                            >
                                {s}
                            </span>
                        ),
                    )}
                </div>
                <div className="actions" style={{ marginTop: 20 }}>
                    {!closed &&
                        (!o.confirmed_at ? (
                            <Action
                                label="Confirm order"
                                secondary={false}
                                url={`/studio/orders/${o.id}/transition`}
                                initial={{
                                    version: o.version,
                                    action: 'confirm',
                                }}
                            >
                                <p className="wide muted">
                                    Confirmation freezes the customer,
                                    commercial details, and original
                                    measurements. All required measurements must
                                    be complete.
                                </p>
                            </Action>
                        ) : (
                            <Action
                                label={
                                    next[o.stage] === 'Delivered'
                                        ? 'Deliver order'
                                        : `Move to ${next[o.stage]}`
                                }
                                secondary={false}
                                url={`/studio/orders/${o.id}/transition`}
                                initial={{
                                    version: o.version,
                                    action: next[o.stage],
                                    reason: '',
                                }}
                                fields={
                                    next[o.stage] === 'Delivered'
                                        ? [
                                              {
                                                  ...reason,
                                                  required: false,
                                                  label: 'Reason if delivering on credit',
                                              },
                                          ]
                                        : []
                                }
                            />
                        ))}
                    {!closed && o.confirmed_at && (
                        <Action
                            label="Reschedule"
                            url={`/studio/orders/${o.id}/transition`}
                            initial={{
                                version: o.version,
                                action: 'reschedule',
                                promised_at: when(),
                                reason: '',
                            }}
                            fields={[
                                {
                                    name: 'promised_at',
                                    label: 'New delivery promise',
                                    type: 'datetime-local',
                                    required: true,
                                },
                                reason,
                            ]}
                        />
                    )}{' '}
                    {!closed && (
                        <Action
                            label="Cancel production"
                            url={`/studio/orders/${o.id}/transition`}
                            initial={{
                                version: o.version,
                                action: 'cancel',
                                reason: '',
                            }}
                            fields={[reason]}
                        >
                            <p className="wide muted">
                                Cancellation preserves all invoices, payments
                                and debt. Credit/refund decisions must be
                                recorded separately.
                            </p>
                        </Action>
                    )}
                </div>
            </Panel>
            <div className="two-columns">
                <Panel title="Customer & delivery">
                    <div className="summary-list">
                        <div>
                            <strong>{o.contact.name}</strong>
                            <span>{o.contact.phone}</span>
                        </div>
                        <div>
                            <span>Promised delivery</span>
                            <strong>
                                {stamp(o.promised_at, p.settings.timezone)}
                            </strong>
                        </div>
                        <div>
                            <span>Original promise</span>
                            <span>
                                {stamp(
                                    o.original_promised_at,
                                    p.settings.timezone,
                                )}
                            </span>
                        </div>
                        <div>
                            <span>Delivered</span>
                            <span>
                                {stamp(o.delivered_at, p.settings.timezone)}
                            </span>
                        </div>
                    </div>
                    <p>{o.contact.address}</p>
                    {o.notes && <p>Customer instructions: {o.notes}</p>}
                </Panel>
                <Panel title="Commercial summary">
                    <div className="summary-list">
                        <div>
                            <span>Gross stitching</span>
                            <span>
                                {money(o.totals.gross, p.settings.currency)}
                            </span>
                        </div>
                        <div>
                            <span>Discounts</span>
                            <span>
                                −{' '}
                                {money(o.totals.discount, p.settings.currency)}
                            </span>
                        </div>
                        <div>
                            <span>Net before tax</span>
                            <span>
                                {money(o.totals.net, p.settings.currency)}
                            </span>
                        </div>
                        <div>
                            <span>Tax</span>
                            <span>
                                {money(o.totals.tax, p.settings.currency)}
                            </span>
                        </div>
                        <div className="total">
                            <span>Order total</span>
                            <span>
                                {money(o.totals.total, p.settings.currency)}
                            </span>
                        </div>
                    </div>
                </Panel>
            </div>
            <Panel title="Garments & measurement history">
                {o.lines.map((l) => (
                    <div className="garment-card" key={l.id}>
                        <header>
                            <div>
                                <h3>
                                    {l.description} × {l.quantity}
                                </h3>
                                <p className="muted">
                                    {money(l.unit_price, p.settings.currency)}{' '}
                                    per piece · {l.price_source} price ·{' '}
                                    {l.measurement_mode === 'shared'
                                        ? 'Shared measurements'
                                        : 'Per-piece measurements'}
                                </p>
                            </div>
                            <strong>
                                {money(l.totals.total, p.settings.currency)}
                            </strong>
                        </header>
                        {l.instructions && <p>{l.instructions}</p>}
                        <div className="actions">
                            {l.snapshots
                                .filter((s) => s.current)
                                .map((s) => (
                                    <Button
                                        key={s.id}
                                        secondary
                                        onClick={() => setSnapshot(s)}
                                    >
                                        {s.piece
                                            ? `Piece ${s.piece}`
                                            : 'Measurements'}{' '}
                                        ·{' '}
                                        {s.fields.every(
                                            (f) => !f.required || f.is_set,
                                        )
                                            ? 'Complete'
                                            : 'Incomplete'}{' '}
                                        · v{s.revision}
                                    </Button>
                                ))}
                        </div>
                        {!closed &&
                            l.measurement_mode === 'per_piece' &&
                            l.snapshots.filter((s) => s.current).length > 1 && (
                                <Action
                                    label="Use first piece measurements for every piece"
                                    url={`/studio/orders/${o.id}/snapshot/${l.snapshots.find((s) => s.current && s.piece === 1)?.id}/copy`}
                                    initial={{ version: o.version, reason: '' }}
                                    fields={o.confirmed_at ? [reason] : []}
                                >
                                    <p className="wide muted">
                                        Copies the saved first-piece values into
                                        independent snapshots for all other
                                        pieces. Unsaved editor changes are not
                                        included.
                                    </p>
                                </Action>
                            )}
                        {l.snapshots.some((s) => !s.current) && (
                            <details>
                                <summary>Previous frozen revisions</summary>
                                {l.snapshots
                                    .filter((s) => !s.current)
                                    .map((s) => (
                                        <div key={s.id}>
                                            <h3>
                                                Piece {s.piece} · revision{' '}
                                                {s.revision}
                                            </h3>
                                            <p>{s.reason}</p>
                                            <DataTable
                                                rows={s.fields}
                                                columns={[
                                                    {
                                                        label: 'Measurement',
                                                        value: (f) =>
                                                            `${f.section} / ${f.label}`,
                                                    },
                                                    {
                                                        label: 'Value',
                                                        value: (f) =>
                                                            f.is_set
                                                                ? `${f.value} ${f.unit === 'none' ? '' : f.unit}`
                                                                : 'Not set',
                                                    },
                                                ]}
                                            />
                                        </div>
                                    ))}
                            </details>
                        )}
                    </div>
                ))}
            </Panel>
            <Panel
                title="Invoice & settlement"
                aside={
                    invoice && (
                        <a
                            className="inline-link"
                            href={`/studio-document/invoice/${invoice.id}`}
                        >
                            Invoice PDF →
                        </a>
                    )
                }
            >
                {o.stage === 'Cancelled' && invoice && (
                    <div className="error">
                        Production is cancelled. Review this invoice and any
                        required credit/refund decision; cancellation does not
                        erase debt.
                    </div>
                )}
                {!invoice ? (
                    <>
                        <p className="muted">
                            Create the primary invoice after confirming the
                            order.
                        </p>
                        {o.confirmed_at && !closed && (
                            <Action
                                label="Create draft invoice"
                                url={`/studio/finance/invoice/${o.id}`}
                                initial={{}}
                            />
                        )}
                    </>
                ) : (
                    <>
                        <div className="stats">
                            <div className="stat">
                                <small>{invoice.reference}</small>
                                <strong>
                                    {money(
                                        invoice.totals.total,
                                        p.settings.currency,
                                    )}
                                </strong>
                                <Badge stage={invoice.state} />
                            </div>
                            <div className="stat">
                                <small>Allocated payment money</small>
                                <strong>
                                    {money(
                                        p.allocations.reduce(
                                            (n, a) => n + a.amount,
                                            0,
                                        ),
                                        p.settings.currency,
                                    )}
                                </strong>
                            </div>
                            <div className="stat">
                                <small>Outstanding receivable</small>
                                <strong>
                                    {money(p.due ?? 0, p.settings.currency)}
                                </strong>
                            </div>
                            <div className="stat">
                                <small>Remaining refund obligation</small>
                                <strong>
                                    {money(
                                        p.credits.reduce(
                                            (n, c) => n + c.refundable,
                                            0,
                                        ),
                                        p.settings.currency,
                                    )}
                                </strong>
                            </div>
                        </div>
                        <div className="actions">
                            {invoice.state === 'draft' ? (
                                <Action
                                    label="Post invoice"
                                    secondary={false}
                                    url={`/studio/finance/post/${invoice.id}`}
                                    initial={{
                                        version: invoice.version,
                                        effective_at: when(),
                                    }}
                                    fields={[date]}
                                >
                                    <p className="wide muted">
                                        Posting makes financial details
                                        immutable. Corrections use credit notes
                                        and linked repeat/correction orders.
                                    </p>
                                </Action>
                            ) : (
                                <>
                                    {(p.due ?? 0) > 0 && (
                                        <Action
                                            label="Receive & allocate payment"
                                            secondary={false}
                                            url="/studio/finance/payment"
                                            initial={{
                                                customer_id: o.customer_id,
                                                invoice_id: invoice.id,
                                                invoice_version:
                                                    invoice.version,
                                                amount: decimal(p.due ?? 0),
                                                payment_method_id:
                                                    p.methods[0]?.id ?? '',
                                                settlement: 'settled',
                                                effective_at: when(),
                                                external_reference: '',
                                            }}
                                            fields={paymentFields}
                                        />
                                    )}
                                    <Button
                                        secondary
                                        onClick={() => setCreditOpen(true)}
                                    >
                                        Create credit note
                                    </Button>
                                </>
                            )}
                        </div>
                        {invoice.state === 'posted' &&
                            p.available_payments?.filter(
                                (a) =>
                                    a.available > 0 &&
                                    a.settlement === 'settled',
                            ).length > 0 && (
                                <div className="garment-card">
                                    <h3>Available settled advances</h3>
                                    {p.available_payments
                                        .filter(
                                            (a) =>
                                                a.available > 0 &&
                                                a.settlement === 'settled',
                                        )
                                        .map((a) => (
                                            <div className="actions" key={a.id}>
                                                <p>
                                                    {a.reference} ·{' '}
                                                    {money(
                                                        a.available,
                                                        p.settings.currency,
                                                    )}
                                                </p>
                                                <Action
                                                    label="Allocate advance"
                                                    url={`/studio/finance/allocate/${a.id}`}
                                                    initial={{
                                                        version: a.version,
                                                        invoice_id: invoice.id,
                                                        invoice_version:
                                                            invoice.version,
                                                        amount: decimal(
                                                            Math.min(
                                                                a.available,
                                                                p.due ?? 0,
                                                            ),
                                                        ),
                                                        effective_at: when(),
                                                    }}
                                                    fields={[
                                                        {
                                                            name: 'amount',
                                                            label: 'Amount to allocate',
                                                            type: 'number',
                                                            required: true,
                                                        },
                                                        date,
                                                    ]}
                                                />
                                            </div>
                                        ))}
                                </div>
                            )}
                        <h3 style={{ marginTop: 25 }}>Payment allocations</h3>
                        <DataTable
                            rows={p.allocations}
                            columns={[
                                {
                                    label: 'Receipt',
                                    value: (a) => (
                                        <a
                                            href={`/studio-document/receipt/${a.payment_id}`}
                                        >
                                            Payment #{a.payment_id} PDF
                                        </a>
                                    ),
                                },
                                {
                                    label: 'Amount',
                                    value: (a) =>
                                        money(a.amount, p.settings.currency),
                                },
                                {
                                    label: 'Effective date',
                                    value: (a) =>
                                        stamp(
                                            a.effective_at,
                                            p.settings.timezone,
                                        ),
                                },
                                {
                                    label: 'Correction',
                                    value: (a) =>
                                        a.amount > 0 &&
                                        !p.allocations.some(
                                            (x) => x.reverses_id === a.id,
                                        ) ? (
                                            <Action
                                                label="Reverse allocation"
                                                url={`/studio/finance/reverse/${a.id}`}
                                                initial={{
                                                    version: invoice.version,
                                                    effective_at: when(),
                                                    reason: '',
                                                }}
                                                fields={[date, reason]}
                                            />
                                        ) : (
                                            <span>Reversal recorded</span>
                                        ),
                                },
                            ]}
                        />
                        <h3 style={{ marginTop: 25 }}>Credits & refunds</h3>
                        <DataTable
                            rows={p.credits}
                            columns={[
                                { label: 'Credit', value: (c) => c.reference },
                                {
                                    label: 'Credit amount',
                                    value: (c) =>
                                        money(c.amount, p.settings.currency),
                                },
                                {
                                    label: 'Refundable',
                                    value: (c) =>
                                        money(
                                            c.refundable,
                                            p.settings.currency,
                                        ),
                                },
                                { label: 'Reason', value: (c) => c.reason },
                                {
                                    label: 'Action',
                                    value: (c) =>
                                        c.refundable > 0 ? (
                                            <Action
                                                label="Record refund"
                                                url={`/studio/finance/refund/${c.id}`}
                                                initial={{
                                                    version: invoice.version,
                                                    amount: decimal(
                                                        c.refundable,
                                                    ),
                                                    payment_method_id:
                                                        p.methods[0]?.id ?? '',
                                                    effective_at: when(),
                                                    reason: '',
                                                }}
                                                fields={[
                                                    ...paymentFields.filter(
                                                        (f) =>
                                                            f.name !==
                                                            'external_reference',
                                                    ),
                                                    reason,
                                                ]}
                                            />
                                        ) : (
                                            <span>Settled</span>
                                        ),
                                },
                            ]}
                        />
                        <DataTable
                            rows={p.refunds}
                            columns={[
                                {
                                    label: 'Refund receipt',
                                    value: (r) => r.reference,
                                },
                                {
                                    label: 'Amount',
                                    value: (r) =>
                                        money(r.amount, p.settings.currency),
                                },
                                {
                                    label: 'Effective date',
                                    value: (r) =>
                                        stamp(
                                            r.effective_at,
                                            p.settings.timezone,
                                        ),
                                },
                            ]}
                        />
                    </>
                )}
            </Panel>
            <div className="two-columns">
                <Panel title="Order & financial audit timeline">
                    <ul className="timeline">
                        {p.events.map((e) => (
                            <li key={e.id}>
                                <strong>{e.action.replaceAll('_', ' ')}</strong>
                                <small>
                                    {stamp(e.created_at, p.settings.timezone)}
                                </small>
                                <details>
                                    <summary>Details</summary>
                                    <pre>
                                        {JSON.stringify(e.changes, null, 2)}
                                    </pre>
                                </details>
                            </li>
                        ))}
                    </ul>
                </Panel>
                <Panel title="Private internal notes">
                    <p>{o.internal_notes || 'No internal notes.'}</p>
                    <p className="metric-note">
                        Excluded from customer documents.
                    </p>
                </Panel>
            </div>
            {editDraft && (
                <Modal
                    title="Edit draft — reset measurements"
                    onClose={() => setEditDraft(false)}
                >
                    <NewOrder
                        base={p}
                        order={o}
                        onDone={() => setEditDraft(false)}
                    />
                </Modal>
            )}
            {snapshot && (
                <Modal
                    title={`${snapshot.template_name} · ${snapshot.piece ? `Piece ${snapshot.piece}` : 'Shared'} · revision ${snapshot.revision}`}
                    onClose={() => setSnapshot(null)}
                >
                    <MeasurementEditor
                        order={o}
                        snapshot={snapshot}
                        sources={p.previous[snapshot.template_id] ?? []}
                        closed={closed}
                        onDone={() => setSnapshot(null)}
                    />
                </Modal>
            )}
            {creditOpen && invoice && (
                <Modal
                    title="Credit original garment quantities"
                    onClose={() => setCreditOpen(false)}
                >
                    <CreditEditor p={p} onDone={() => setCreditOpen(false)} />
                </Modal>
            )}
        </Shell>
    );
}
function MeasurementEditor({
    order,
    snapshot: s,
    sources,
    closed,
    onDone,
}: {
    order: Order;
    snapshot: Snapshot;
    sources: Snapshot[];
    closed: boolean;
    onDone: () => void;
}) {
    const values: Record<string, string> = {};
    s.fields.forEach((f) => {
        if (f.is_set) {
            values[f.code] = String(f.value);
        } else if (f.type === 'choice') {
            values[f.code] = 'no';
        } else {
            values[f.code] = '';
        }
    });

    const handleChoiceChange = (field: Field, val: string) => {
        const nextValues = { ...form.data.values, [field.code]: val };
        if (val !== 'no' && val !== '' && field.group) {
            s.fields.forEach((other) => {
                if (other.code !== field.code && other.group === field.group) {
                    nextValues[other.code] = 'no';
                }
            });
        }
        form.setData('values', nextValues);
    };
    const form = useForm({
        version: order.version,
        values,
        reason: '',
        source_snapshot_id: null as number | null,
    });
    const [selected, setSelected] = useState<string>(
        sources.length > 0 ? String(sources[0].id) : '',
    );
    const [message, setMessage] = useState('');
    const source = sources.find((x) => x.id === Number(selected));
    const sections = [...new Set(s.fields.map((f) => f.section))];
    function load() {
        if (!source) return;
        if (source.unit !== s.unit) {
            setMessage(
                'Units differ. Values were not loaded; enter a deliberate converted measurement instead.',
            );
            return;
        }
        const out = { ...form.data.values };
        let skipped = 0;
        for (const f of s.fields) {
            const previous = source.fields.find(
                (x) =>
                    x.code === f.code && x.unit === f.unit && x.type === f.type,
            );
            if (
                previous?.is_set &&
                (f.type !== 'choice' ||
                    f.options.some((x) => x.code === previous.value))
            )
                out[f.code] = String(previous.value);
            else skipped++;
        }
        form.setData({
            ...form.data,
            values: out,
            source_snapshot_id: source.id,
        });
        setMessage(
            `Values copied for review. ${skipped} fields were left unchanged because they are missing or incompatible.`,
        );
    }
    const submissionLock = useRef(false);
    const formRef = useFirstErrorFocus(form.errors);
    useUnsavedChanges(form.isDirty);
    return (
        <form
            ref={formRef}
            className="editor"
            aria-busy={form.processing}
            onSubmit={(e) => {
                e.preventDefault();
                if (form.processing || submissionLock.current) return;
                submissionLock.current = true;
                allowNextUnsavedNavigation();
                form.post(`/studio/orders/${order.id}/snapshot/${s.id}`, {
                    preserveScroll: true,
                    onSuccess: onDone,
                    onFinish: () => {
                        submissionLock.current = false;
                    },
                });
            }}
        >
            {!closed && sources.length > 0 && (
                <div className="wide">
                    <label>
                        Previous order measurements ({sources[0].source_order})
                        {sources.length > 1 && (
                            <select
                                value={selected}
                                onChange={(e) => setSelected(e.target.value)}
                            >
                                <option value="">Choose previous order</option>
                                {sources.map((v) => (
                                    <option value={v.id} key={v.id}>
                                        {v.source_order} · {v.unit} · revision{' '}
                                        {v.revision} · piece {v.piece}
                                    </option>
                                ))}
                            </select>
                        )}
                    </label>
                    {source && (
                        <div className="garment-card">
                            <p>
                                {source.source_order} ·{' '}
                                {stamp(source.source_date)} · {source.unit}
                            </p>
                            <DataTable
                                rows={source.fields.filter((f) => f.is_set)}
                                columns={[
                                    {
                                        label: 'Measurement',
                                        value: (f) => f.label,
                                    },
                                    {
                                        label: 'Value',
                                        value: (f) => String(f.value),
                                    },
                                ]}
                            />
                            <Button secondary onClick={load}>
                                Copy {source.source_order} values for review
                            </Button>
                        </div>
                    )}
                    {message && <p className="notice">{message}</p>}
                </div>
            )}
            <div className="field-grid wide">
                {sections.map((section) => (
                    <div key={section} style={{ display: 'contents' }}>
                        <h3>{section}</h3>
                        {s.fields
                            .filter((f) => f.section === section)
                            .sort((a, b) => a.sequence - b.sequence)
                            .map((f) => {
                                if (f.depends_on) {
                                    const parentVal =
                                        form.data.values[f.depends_on];
                                    const isParentActive =
                                        parentVal &&
                                        parentVal !== 'no' &&
                                        parentVal !== '';
                                    if (!isParentActive) {
                                        return null;
                                    }
                                }

                                return (
                                    <label
                                        key={f.code}
                                        className={
                                            f.checkbox ? 'check-label' : ''
                                        }
                                    >
                                        <span>
                                            {f.label}
                                            {f.required ? ' *' : ''}
                                            {f.unit !== 'none'
                                                ? ` (${f.unit === 'inches' ? 'انچ' : f.unit})`
                                                : ''}
                                        </span>
                                        {f.checkbox ? (
                                            <input
                                                type="checkbox"
                                                disabled={closed}
                                                checked={
                                                    form.data.values[f.code] ===
                                                    'yes'
                                                }
                                                {...fieldErrorProps(
                                                    form.errors,
                                                    `values.${f.code}`,
                                                )}
                                                onChange={(e) =>
                                                    handleChoiceChange(
                                                        f,
                                                        e.target.checked
                                                            ? 'yes'
                                                            : 'no',
                                                    )
                                                }
                                            />
                                        ) : f.type === 'choice' ? (
                                            <select
                                                disabled={closed}
                                                value={
                                                    form.data.values[f.code] ||
                                                    'no'
                                                }
                                                {...fieldErrorProps(
                                                    form.errors,
                                                    `values.${f.code}`,
                                                )}
                                                onChange={(e) =>
                                                    handleChoiceChange(
                                                        f,
                                                        e.target.value,
                                                    )
                                                }
                                            >
                                                {f.options.map((v) => (
                                                    <option
                                                        key={v.code}
                                                        value={v.code}
                                                    >
                                                        {v.label}
                                                    </option>
                                                ))}
                                            </select>
                                        ) : f.type === 'text' ? (
                                            <textarea
                                                disabled={closed}
                                                value={
                                                    form.data.values[f.code] ||
                                                    ''
                                                }
                                                rows={f.depends_on ? 2 : 4}
                                                placeholder={f.label}
                                                {...fieldErrorProps(
                                                    form.errors,
                                                    `values.${f.code}`,
                                                )}
                                                onChange={(e) =>
                                                    form.setData('values', {
                                                        ...form.data.values,
                                                        [f.code]:
                                                            e.target.value,
                                                    })
                                                }
                                            />
                                        ) : (
                                            <input
                                                disabled={closed}
                                                type="number"
                                                step="any"
                                                placeholder="انچ میں عدد"
                                                value={
                                                    form.data.values[f.code] ||
                                                    ''
                                                }
                                                {...fieldErrorProps(
                                                    form.errors,
                                                    `values.${f.code}`,
                                                )}
                                                onChange={(e) =>
                                                    form.setData('values', {
                                                        ...form.data.values,
                                                        [f.code]:
                                                            e.target.value,
                                                    })
                                                }
                                            />
                                        )}
                                        <small className="muted">
                                            {f.help}
                                        </small>
                                        <FieldError
                                            errors={form.errors}
                                            name={`values.${f.code}`}
                                        />
                                    </label>
                                );
                            })}
                    </div>
                ))}
            </div>
            {order.confirmed_at && !closed && (
                <label className="wide">
                    Reason for a new measurement revision
                    <textarea
                        name="reason"
                        required
                        value={form.data.reason}
                        onChange={(e) => form.setData('reason', e.target.value)}
                        {...fieldErrorProps(form.errors, 'reason')}
                    />
                    <FieldError errors={form.errors} name="reason" />
                </label>
            )}
            <ErrorSummary errors={form.errors} />
            {!closed && (
                <div className="form-end">
                    <Button type="submit" disabled={form.processing}>
                        {form.processing ? 'Saving…' : 'Save measurements'}
                    </Button>
                </div>
            )}
        </form>
    );
}
function CreditEditor({ p, onDone }: { p: Props; onDone: () => void }) {
    const i = p.order.invoice!;
    const quantities: Record<string, number> = {};
    p.order.lines.forEach((l) => (quantities[l.id] = 0));
    const f = useForm({
        key: crypto.randomUUID(),
        version: i.version,
        effective_at: localNow(p.settings.timezone),
        reason: '',
        quantities,
    });
    const submissionLock = useRef(false);
    const formRef = useFirstErrorFocus(f.errors);
    useUnsavedChanges(f.isDirty);
    return (
        <form
            ref={formRef}
            className="editor"
            aria-busy={f.processing}
            onSubmit={(e) => {
                e.preventDefault();
                if (f.processing || submissionLock.current) return;
                submissionLock.current = true;
                allowNextUnsavedNavigation();
                f.post(`/studio/finance/credit/${i.id}`, {
                    onSuccess: onDone,
                    onFinish: () => {
                        submissionLock.current = false;
                    },
                });
            }}
        >
            <p className="wide muted">
                Credit uses original prices, discounts and taxes. It first
                reduces unpaid receivables; any remaining credit may be
                refunded.
            </p>
            {p.order.lines.map((l) => (
                <label key={l.id}>
                    {l.description} · original quantity {l.quantity}
                    <input
                        name={`quantities.${l.id}`}
                        type="number"
                        min="0"
                        max={l.quantity}
                        value={f.data.quantities[l.id]}
                        {...fieldErrorProps(f.errors, `quantities.${l.id}`)}
                        onChange={(e) =>
                            f.setData('quantities', {
                                ...f.data.quantities,
                                [l.id]: Number(e.target.value),
                            })
                        }
                    />
                    <FieldError errors={f.errors} name={`quantities.${l.id}`} />
                </label>
            ))}
            <label>
                Effective date
                <input
                    name="effective_at"
                    type="datetime-local"
                    step="1"
                    required
                    value={f.data.effective_at}
                    onChange={(e) => f.setData('effective_at', e.target.value)}
                    {...fieldErrorProps(f.errors, 'effective_at')}
                />
                <FieldError errors={f.errors} name="effective_at" />
            </label>
            <label>
                Credit reason
                <textarea
                    name="reason"
                    required
                    value={f.data.reason}
                    onChange={(e) => f.setData('reason', e.target.value)}
                    {...fieldErrorProps(f.errors, 'reason')}
                />
                <FieldError errors={f.errors} name="reason" />
            </label>
            <ErrorSummary errors={f.errors} />
            <div className="form-end">
                <Button type="submit" disabled={f.processing}>
                    {f.processing ? 'Posting…' : 'Post credit note'}
                </Button>
            </div>
        </form>
    );
}
