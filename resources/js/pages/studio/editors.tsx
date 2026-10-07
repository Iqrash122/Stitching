import { router, useForm } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { Plus, Trash2 } from 'lucide-react';
import type {
    Base,
    Product,
    Template,
    Field,
    Totals,
    Tax,
    Order,
    Customer,
} from './types';
import { Button, decimal, localNow, money } from './ui';
import {
    allowNextUnsavedNavigation,
    ErrorSummary,
    FieldError,
    fieldErrorProps,
    useFirstErrorFocus,
    useUnsavedChanges,
} from './form-support';
export function NewOrder({
    base,
    onDone,
    order,
    initialCustomerId,
}: {
    base: Base;
    onDone: () => void;
    order?: Order;
    initialCustomerId?: number;
}) {
    const line = () => ({
        product_id: '',
        quantity: 1,
        price: '',
        discount_type: 'fixed',
        discount_value: '0',
        measurement_mode: 'shared',
        instructions: '',
    });
    const f = useForm({
        version: order?.version ?? 1,
        reset_measurements: false,
        customer_id: order
            ? String(order.customer_id)
            : initialCustomerId
              ? String(initialCustomerId)
              : '',
        promised_at: localNow(base.settings.timezone, order?.promised_at),
        discount_type: order?.discount_type ?? 'fixed',
        discount_value: decimal(order?.discount_value ?? 0),
        notes: order?.notes ?? '',
        internal_notes: order?.internal_notes ?? '',
        lines: order
            ? order.lines.map((l) => ({
                  product_id: String(l.product_id),
                  quantity: l.quantity,
                  price: decimal(l.unit_price),
                  discount_type: l.discount_type,
                  discount_value: decimal(l.discount_value),
                  measurement_mode: l.measurement_mode,
                  instructions: l.instructions ?? '',
              }))
            : [line()],
    });
    const submissionLock = useRef(false);
    const [quickName, setQuickName] = useState('');
    const [quickPhone, setQuickPhone] = useState('');
    const [quickBusy, setQuickBusy] = useState(false);
    const quickBusyRef = useRef(false);
    const [quickError, setQuickError] = useState('');
    const [quickErrors, setQuickErrors] = useState<Record<string, string>>({});
    const [extraCustomers, setExtraCustomers] = useState<
        Pick<Customer, 'id' | 'name' | 'phone'>[]
    >([]);
    async function quickCreate() {
        if (quickBusy || quickBusyRef.current) return;
        quickBusyRef.current = true;
        setQuickBusy(true);
        setQuickError('');
        setQuickErrors({});
        try {
            const token = document.cookie
                .split('; ')
                .find((x) => x.startsWith('XSRF-TOKEN='))
                ?.split('=')
                .slice(1)
                .join('=');
            const response = await fetch('/studio/customers', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': decodeURIComponent(token ?? ''),
                },
                body: JSON.stringify({
                    name: quickName,
                    phone: quickPhone,
                    active: true,
                }),
            });
            const c = (await response.json()) as Pick<
                Customer,
                'id' | 'name' | 'phone'
            > & { message?: string; errors?: Record<string, string[]> };
            if (!response.ok) {
                if (c.errors)
                    setQuickErrors(
                        Object.fromEntries(
                            Object.entries(c.errors).map(([key, messages]) => [
                                key,
                                messages.join(' '),
                            ]),
                        ),
                    );
                throw new Error(c.message ?? 'Unable to create customer.');
            }
            setExtraCustomers((v) => [...v, c]);
            f.setData('customer_id', String(c.id));
            setQuickName('');
            setQuickPhone('');
        } catch (e) {
            setQuickError(
                e instanceof Error ? e.message : 'Unable to create customer.',
            );
        } finally {
            quickBusyRef.current = false;
            setQuickBusy(false);
        }
    }
    const [preview, setPreview] = useState<Totals | null>(null);
    const [previewError, setPreviewError] = useState('');
    const [previewBusy, setPreviewBusy] = useState(false);
    const previewRequest = useRef(0);
    useEffect(() => {
        const controller = new AbortController();
        const requestId = ++previewRequest.current;
        const timer = setTimeout(() => {
            if (
                !f.data.customer_id ||
                f.data.lines.some((l) => !l.product_id)
            ) {
                setPreview(null);
                setPreviewError('');
                setPreviewBusy(false);
                return;
            }
            setPreviewBusy(true);
            const token = document.cookie
                .split('; ')
                .find((x) => x.startsWith('XSRF-TOKEN='))
                ?.split('=')
                .slice(1)
                .join('=');
            fetch('/studio/preview', {
                method: 'POST',
                credentials: 'same-origin',
                signal: controller.signal,
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': decodeURIComponent(token ?? ''),
                },
                body: JSON.stringify(f.data),
            })
                .then(async (r) => {
                    const data = (await r.json()) as Totals & {
                        message?: string;
                    };
                    if (!r.ok)
                        throw new Error(
                            data.message ?? 'Could not calculate totals.',
                        );
                    if (requestId !== previewRequest.current) return;
                    setPreview(data);
                    setPreviewError('');
                })
                .catch((e: unknown) => {
                    if (
                        !controller.signal.aborted &&
                        requestId === previewRequest.current
                    ) {
                        setPreview(null);
                        setPreviewError(
                            e instanceof Error
                                ? e.message
                                : 'Preview unavailable',
                        );
                    }
                })
                .finally(() => {
                    if (
                        !controller.signal.aborted &&
                        requestId === previewRequest.current
                    )
                        setPreviewBusy(false);
                });
        }, 350);
        return () => {
            clearTimeout(timer);
            controller.abort();
        };
    }, [f.data]);
    const formRef = useFirstErrorFocus(f.errors);
    useUnsavedChanges(
        f.isDirty || quickName.trim() !== '' || quickPhone.trim() !== '',
    );
    const update = (i: number, key: string, value: string | number) =>
        f.setData(
            'lines',
            f.data.lines.map((l, n) => (n === i ? { ...l, [key]: value } : l)),
        );
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
                f.post(
                    order
                        ? `/studio/orders/${order.id}/edit`
                        : '/studio/orders',
                    {
                        onSuccess: onDone,
                        onFinish: () => {
                            submissionLock.current = false;
                        },
                    },
                );
            }}
        >
            <div className="form-field">
                <label htmlFor="order-customer">Customer</label>
                <select
                    id="order-customer"
                    name="customer_id"
                    required
                    value={f.data.customer_id}
                    onChange={(e) => f.setData('customer_id', e.target.value)}
                    {...fieldErrorProps(f.errors, 'customer_id')}
                >
                    <option value="">Choose customer</option>
                    {[...base.customers, ...extraCustomers].map((c) => (
                        <option key={c.id} value={c.id}>
                            {c.name} · {c.phone}
                        </option>
                    ))}
                </select>
                <details>
                    <summary className="inline-link">
                        Quick-create customer
                    </summary>
                    <div className="garment-card">
                        <input
                            aria-label="New customer name"
                            placeholder="Customer name"
                            value={quickName}
                            aria-invalid={
                                Boolean(quickErrors.name) || undefined
                            }
                            aria-describedby={
                                quickErrors.name
                                    ? 'quick-name-error'
                                    : undefined
                            }
                            onChange={(e) => {
                                setQuickName(e.target.value);
                                setQuickErrors((current) => ({
                                    ...current,
                                    name: '',
                                }));
                            }}
                        />
                        {quickErrors.name && (
                            <em className="field-error" id="quick-name-error">
                                {quickErrors.name}
                            </em>
                        )}
                        <input
                            aria-label="New customer phone"
                            placeholder="Phone"
                            value={quickPhone}
                            aria-invalid={
                                Boolean(quickErrors.phone) || undefined
                            }
                            aria-describedby={
                                quickErrors.phone
                                    ? 'quick-phone-error'
                                    : undefined
                            }
                            onChange={(e) => {
                                setQuickPhone(e.target.value);
                                setQuickErrors((current) => ({
                                    ...current,
                                    phone: '',
                                }));
                            }}
                        />
                        {quickErrors.phone && (
                            <em className="field-error" id="quick-phone-error">
                                {quickErrors.phone}
                            </em>
                        )}
                        <Button
                            secondary
                            disabled={quickBusy}
                            onClick={() => {
                                void quickCreate();
                            }}
                        >
                            {quickBusy
                                ? 'Saving customer…'
                                : 'Save customer & select'}
                        </Button>
                        {quickError && <p className="error">{quickError}</p>}
                    </div>
                </details>
                <FieldError errors={f.errors} name="customer_id" />
            </div>
            <label htmlFor="order-promised-at">
                Promised delivery ({base.settings.timezone})
                <input
                    id="order-promised-at"
                    name="promised_at"
                    required
                    type="datetime-local"
                    step="1"
                    value={f.data.promised_at}
                    onChange={(e) => f.setData('promised_at', e.target.value)}
                    {...fieldErrorProps(f.errors, 'promised_at')}
                />
                <FieldError errors={f.errors} name="promised_at" />
            </label>
            {order && (
                <label className="wide check-label">
                    <input
                        name="reset_measurements"
                        required
                        type="checkbox"
                        checked={f.data.reset_measurements}
                        {...fieldErrorProps(f.errors, 'reset_measurements')}
                        onChange={(e) =>
                            f.setData('reset_measurements', e.target.checked)
                        }
                    />
                    I understand this draft edit resets all garment measurements
                    for re-entry.
                    <FieldError errors={f.errors} name="reset_measurements" />
                </label>
            )}
            <div className="wide">
                {f.data.lines.map((l, i) => (
                    <div className="garment-card" key={i}>
                        <header>
                            <h3>Garment {i + 1}</h3>
                            {f.data.lines.length > 1 && (
                                <button
                                    type="button"
                                    aria-label={`Remove garment ${i + 1}`}
                                    onClick={() =>
                                        f.setData(
                                            'lines',
                                            f.data.lines.filter(
                                                (_, n) => n !== i,
                                            ),
                                        )
                                    }
                                >
                                    <Trash2 size={16} />
                                </button>
                            )}
                        </header>
                        <div className="field-grid">
                            <label>
                                Stitching service
                                <select
                                    name={`lines.${i}.product_id`}
                                    required
                                    value={l.product_id}
                                    {...fieldErrorProps(
                                        f.errors,
                                        `lines.${i}.product_id`,
                                    )}
                                    onChange={(e) =>
                                        update(i, 'product_id', e.target.value)
                                    }
                                >
                                    <option value="">Choose service</option>
                                    {base.products
                                        .filter((p) => p.active)
                                        .map((p) => (
                                            <option key={p.id} value={p.id}>
                                                {p.name} ·{' '}
                                                {money(
                                                    p.price,
                                                    base.settings.currency,
                                                )}
                                            </option>
                                        ))}
                                </select>
                                <FieldError
                                    errors={f.errors}
                                    name={`lines.${i}.product_id`}
                                />
                            </label>
                            <label>
                                Quantity
                                <input
                                    name={`lines.${i}.quantity`}
                                    type="number"
                                    min="1"
                                    max="1000"
                                    required
                                    value={l.quantity}
                                    {...fieldErrorProps(
                                        f.errors,
                                        `lines.${i}.quantity`,
                                    )}
                                    onChange={(e) =>
                                        update(
                                            i,
                                            'quantity',
                                            Number(e.target.value),
                                        )
                                    }
                                />
                                <FieldError
                                    errors={f.errors}
                                    name={`lines.${i}.quantity`}
                                />
                            </label>
                            <label>
                                Manual unit price (optional)
                                <input
                                    name={`lines.${i}.price`}
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    placeholder="Use current customer/default price"
                                    value={l.price}
                                    {...fieldErrorProps(
                                        f.errors,
                                        `lines.${i}.price`,
                                    )}
                                    onChange={(e) =>
                                        update(i, 'price', e.target.value)
                                    }
                                />
                                <FieldError
                                    errors={f.errors}
                                    name={`lines.${i}.price`}
                                />
                            </label>
                            <label>
                                Measurement mode
                                <select
                                    name={`lines.${i}.measurement_mode`}
                                    value={l.measurement_mode}
                                    {...fieldErrorProps(
                                        f.errors,
                                        `lines.${i}.measurement_mode`,
                                    )}
                                    onChange={(e) =>
                                        update(
                                            i,
                                            'measurement_mode',
                                            e.target.value,
                                        )
                                    }
                                >
                                    <option value="shared">
                                        Same measurements for all pieces
                                    </option>
                                    <option value="per_piece">
                                        Separate measurements per piece
                                    </option>
                                </select>
                                <FieldError
                                    errors={f.errors}
                                    name={`lines.${i}.measurement_mode`}
                                />
                            </label>
                            <label>
                                Line discount
                                <select
                                    name={`lines.${i}.discount_type`}
                                    value={l.discount_type}
                                    {...fieldErrorProps(
                                        f.errors,
                                        `lines.${i}.discount_type`,
                                    )}
                                    onChange={(e) =>
                                        update(
                                            i,
                                            'discount_type',
                                            e.target.value,
                                        )
                                    }
                                >
                                    <option value="fixed">
                                        Fixed amount (whole line)
                                    </option>
                                    <option value="percent">Percentage</option>
                                </select>
                                <FieldError
                                    errors={f.errors}
                                    name={`lines.${i}.discount_type`}
                                />
                            </label>
                            <label>
                                Discount value
                                <input
                                    name={`lines.${i}.discount_value`}
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    value={l.discount_value}
                                    {...fieldErrorProps(
                                        f.errors,
                                        `lines.${i}.discount_value`,
                                    )}
                                    onChange={(e) =>
                                        update(
                                            i,
                                            'discount_value',
                                            e.target.value,
                                        )
                                    }
                                />
                                <FieldError
                                    errors={f.errors}
                                    name={`lines.${i}.discount_value`}
                                />
                            </label>
                            <label>
                                Garment instructions
                                <textarea
                                    name={`lines.${i}.instructions`}
                                    value={l.instructions}
                                    {...fieldErrorProps(
                                        f.errors,
                                        `lines.${i}.instructions`,
                                    )}
                                    onChange={(e) =>
                                        update(
                                            i,
                                            'instructions',
                                            e.target.value,
                                        )
                                    }
                                />
                                <FieldError
                                    errors={f.errors}
                                    name={`lines.${i}.instructions`}
                                />
                            </label>
                        </div>
                    </div>
                ))}
                <Button
                    secondary
                    onClick={() =>
                        f.setData('lines', [...f.data.lines, line()])
                    }
                >
                    <Plus size={15} /> Add another garment / stitching item
                </Button>
                <p className="muted">
                    Add Shalwar Kameez, pant, shirt, coat, waistcoat or any
                    other configured stitching service to the same order. All
                    lines will be billed together on the same invoice.
                </p>
            </div>
            <label>
                Order discount
                <select
                    name="discount_type"
                    value={f.data.discount_type}
                    {...fieldErrorProps(f.errors, 'discount_type')}
                    onChange={(e) => f.setData('discount_type', e.target.value)}
                >
                    <option value="fixed">Fixed amount</option>
                    <option value="percent">Percentage</option>
                </select>
                <FieldError errors={f.errors} name="discount_type" />
            </label>
            <label>
                Order discount value
                <input
                    name="discount_value"
                    type="number"
                    min="0"
                    step="0.01"
                    value={f.data.discount_value}
                    {...fieldErrorProps(f.errors, 'discount_value')}
                    onChange={(e) =>
                        f.setData('discount_value', e.target.value)
                    }
                />
                <FieldError errors={f.errors} name="discount_value" />
            </label>
            <label>
                Customer-visible notes
                <textarea
                    name="notes"
                    value={f.data.notes}
                    onChange={(e) => f.setData('notes', e.target.value)}
                    {...fieldErrorProps(f.errors, 'notes')}
                />
                <FieldError errors={f.errors} name="notes" />
            </label>
            <label>
                Private internal notes
                <textarea
                    name="internal_notes"
                    value={f.data.internal_notes}
                    onChange={(e) =>
                        f.setData('internal_notes', e.target.value)
                    }
                    {...fieldErrorProps(f.errors, 'internal_notes')}
                />
                <FieldError errors={f.errors} name="internal_notes" />
            </label>
            <div className="wide">
                {preview && (
                    <div className="summary-list">
                        <div>
                            <span>After discounts, excluding tax</span>
                            <strong>
                                {money(preview.net, base.settings.currency)}
                            </strong>
                        </div>
                        <div>
                            <span>Tax</span>
                            <strong>
                                {money(preview.tax, base.settings.currency)}
                            </strong>
                        </div>
                        <div className="total">
                            <span>Total</span>
                            <strong>
                                {money(preview.total, base.settings.currency)}
                            </strong>
                        </div>
                    </div>
                )}
                {previewBusy && (
                    <p className="muted" role="status" aria-live="polite">
                        Recalculating price…
                    </p>
                )}
                {previewError && (
                    <p className="error" role="alert">
                        {previewError}
                    </p>
                )}
            </div>
            <ErrorSummary errors={f.errors} />
            <div className="form-end">
                <Button disabled={f.processing} type="submit">
                    {f.processing
                        ? order
                            ? 'Saving…'
                            : 'Creating…'
                        : order
                          ? 'Save draft order changes'
                          : 'Create order & enter measurements'}
                </Button>
            </div>
        </form>
    );
}
export function ProductEditor({
    base,
    product,
    onDone,
}: {
    base: Base;
    product?: Product;
    onDone: () => void;
}) {
    const f = useForm({
        name: product?.name ?? '',
        category: product?.category ?? '',
        template_id: product?.template_id ?? '',
        price: decimal(product?.price ?? 0),
        cost: decimal(product?.cost ?? 0),
        cost_known: product?.cost_known ?? false,
        active: product?.active ?? true,
        version: product?.version ?? 1,
        taxes: (product?.taxes ?? []) as Tax[],
    });
    const tax = (i: number, k: string, v: string | number | boolean) =>
        f.setData(
            'taxes',
            f.data.taxes.map((t, n) => (i === n ? { ...t, [k]: v } : t)),
        );
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
                f.post(`/studio/config/products/${product?.id ?? ''}`, {
                    onSuccess: onDone,
                    onFinish: () => {
                        submissionLock.current = false;
                    },
                });
            }}
        >
            <label>
                Service name
                <input
                    name="name"
                    required
                    value={f.data.name}
                    onChange={(e) => f.setData('name', e.target.value)}
                    {...fieldErrorProps(f.errors, 'name')}
                />
                <FieldError errors={f.errors} name="name" />
            </label>
            <label>
                Category
                <input
                    name="category"
                    value={f.data.category}
                    onChange={(e) => f.setData('category', e.target.value)}
                    {...fieldErrorProps(f.errors, 'category')}
                />
                <FieldError errors={f.errors} name="category" />
            </label>
            <label>
                Default stitching price
                <input
                    name="price"
                    type="number"
                    step="0.01"
                    min="0"
                    required
                    value={f.data.price}
                    onChange={(e) => f.setData('price', e.target.value)}
                    {...fieldErrorProps(f.errors, 'price')}
                />
                <FieldError errors={f.errors} name="price" />
            </label>
            <label>
                Measurement template
                <select
                    name="template_id"
                    required
                    value={f.data.template_id}
                    onChange={(e) => f.setData('template_id', e.target.value)}
                    {...fieldErrorProps(f.errors, 'template_id')}
                >
                    <option value="">Choose template</option>
                    {base.templates
                        .filter((t) => t.active)
                        .map((t) => (
                            <option key={t.id} value={t.id}>
                                {t.name}
                            </option>
                        ))}
                </select>
                <FieldError errors={f.errors} name="template_id" />
            </label>
            <label className="check-label">
                Direct cost is known
                <input
                    type="checkbox"
                    checked={f.data.cost_known}
                    onChange={(e) => f.setData('cost_known', e.target.checked)}
                />
            </label>
            <label>
                Direct unit cost
                <input
                    name="cost"
                    type="number"
                    min="0"
                    step="0.01"
                    value={f.data.cost}
                    onChange={(e) => f.setData('cost', e.target.value)}
                    {...fieldErrorProps(f.errors, 'cost')}
                />
                <FieldError errors={f.errors} name="cost" />
            </label>
            <label className="check-label">
                Active service
                <input
                    type="checkbox"
                    checked={f.data.active}
                    onChange={(e) => f.setData('active', e.target.checked)}
                />
            </label>
            <div className="wide">
                <h3>Taxes · calculated in this order</h3>
                {f.data.taxes.map((t, i) => (
                    <div className="template-field" key={i}>
                        <label>
                            Tax name
                            <input
                                name={`taxes.${i}.name`}
                                required
                                value={t.name}
                                onChange={(e) => tax(i, 'name', e.target.value)}
                                {...fieldErrorProps(
                                    f.errors,
                                    `taxes.${i}.name`,
                                )}
                            />
                            <FieldError
                                errors={f.errors}
                                name={`taxes.${i}.name`}
                            />
                        </label>
                        <label>
                            Type
                            <select
                                name={`taxes.${i}.type`}
                                value={t.type}
                                onChange={(e) => tax(i, 'type', e.target.value)}
                                {...fieldErrorProps(
                                    f.errors,
                                    `taxes.${i}.type`,
                                )}
                            >
                                <option value="percent">Percentage</option>
                                <option value="fixed">Fixed per piece</option>
                            </select>
                            <FieldError
                                errors={f.errors}
                                name={`taxes.${i}.type`}
                            />
                        </label>
                        <label>
                            {t.type === 'percent' ? 'Percentage' : 'Amount'}
                            <input
                                name={`taxes.${i}.rate`}
                                min="0"
                                type="number"
                                step="0.01"
                                value={t.rate / 100}
                                {...fieldErrorProps(
                                    f.errors,
                                    `taxes.${i}.rate`,
                                )}
                                onChange={(e) =>
                                    tax(
                                        i,
                                        'rate',
                                        Math.round(
                                            Number(e.target.value) * 100,
                                        ),
                                    )
                                }
                            />
                            <FieldError
                                errors={f.errors}
                                name={`taxes.${i}.rate`}
                            />
                        </label>
                        <label>
                            <span>Included in price</span>
                            <input
                                type="checkbox"
                                checked={t.included}
                                onChange={(e) =>
                                    tax(i, 'included', e.target.checked)
                                }
                            />
                        </label>
                        <label>
                            <span>Apply on previous taxes too</span>
                            <input
                                type="checkbox"
                                checked={t.compound}
                                onChange={(e) =>
                                    tax(i, 'compound', e.target.checked)
                                }
                            />
                        </label>
                        <Button
                            secondary
                            onClick={() =>
                                f.setData(
                                    'taxes',
                                    f.data.taxes.filter((_, n) => n !== i),
                                )
                            }
                        >
                            Remove tax
                        </Button>
                    </div>
                ))}
                <Button
                    secondary
                    onClick={() =>
                        f.setData('taxes', [
                            ...f.data.taxes,
                            {
                                name: '',
                                type: 'percent',
                                rate: 0,
                                included: false,
                                compound: false,
                            },
                        ])
                    }
                >
                    Add tax
                </Button>
            </div>
            <ErrorSummary errors={f.errors} />
            <div className="form-end">
                <Button type="submit" disabled={f.processing}>
                    {f.processing ? 'Saving…' : 'Save service'}
                </Button>
            </div>
        </form>
    );
}
export function TemplateEditor({
    template,
    onDone,
}: {
    template?: Template;
    onDone: () => void;
}) {
    const [data, set] = useState({
        name: template?.name ?? '',
        code: template?.code ?? '',
        unit: template?.unit ?? 'inches',
        active: template?.active ?? true,
        notes: template?.notes ?? '',
        version: template?.version ?? 1,
        fields: template?.fields ?? [],
    });
    const [busy, setBusy] = useState(false);
    const submissionLock = useRef(false);
    const initialData = useState(() => JSON.stringify(data))[0];
    const [errors, setErrors] = useState<Record<string, string>>({});
    const formRef = useFirstErrorFocus(errors);
    useUnsavedChanges(JSON.stringify(data) !== initialData);
    const update = (i: number, patch: Partial<Field>) =>
        set({
            ...data,
            fields: data.fields.map((f, n) =>
                n === i ? { ...f, ...patch } : f,
            ),
        });
    return (
        <form
            ref={formRef}
            className="editor"
            aria-busy={busy}
            onSubmit={(e) => {
                e.preventDefault();
                if (busy || submissionLock.current) return;
                submissionLock.current = true;
                setBusy(true);
                setErrors({});
                allowNextUnsavedNavigation();
                router.post(
                    `/studio/config/templates/${template?.id ?? ''}`,
                    JSON.parse(JSON.stringify(data)),
                    {
                        onSuccess: onDone,
                        onError: (e) => setErrors(e),
                        onFinish: () => {
                            submissionLock.current = false;
                            setBusy(false);
                        },
                    },
                );
            }}
        >
            <label>
                Template name
                <input
                    name="name"
                    required
                    value={data.name}
                    onChange={(e) => set({ ...data, name: e.target.value })}
                    {...fieldErrorProps(errors, 'name')}
                />
                <FieldError errors={errors} name="name" />
            </label>
            <label>
                Stable template code
                <input
                    name="code"
                    required
                    pattern="[a-z][a-z0-9_]*"
                    value={data.code}
                    onChange={(e) => set({ ...data, code: e.target.value })}
                    {...fieldErrorProps(errors, 'code')}
                />
                <FieldError errors={errors} name="code" />
            </label>
            <label>
                Default unit
                <select
                    name="unit"
                    value={data.unit}
                    onChange={(e) => set({ ...data, unit: e.target.value })}
                    {...fieldErrorProps(errors, 'unit')}
                >
                    <option value="inches">Inches</option>
                    <option value="centimeters">Centimeters</option>
                </select>
                <FieldError errors={errors} name="unit" />
                <small>
                    Each existing field keeps its own unit; change it
                    deliberately below.
                </small>
            </label>
            <label className="check-label">
                Active
                <input
                    type="checkbox"
                    checked={data.active}
                    onChange={(e) => set({ ...data, active: e.target.checked })}
                />
            </label>
            <div className="wide">
                {data.fields.map((f, i) => (
                    <div className="template-field" key={i}>
                        <label>
                            Label
                            <input
                                name={`fields.${i}.label`}
                                required
                                value={f.label}
                                {...fieldErrorProps(
                                    errors,
                                    `fields.${i}.label`,
                                )}
                                onChange={(e) =>
                                    update(i, { label: e.target.value })
                                }
                            />
                            <FieldError
                                errors={errors}
                                name={`fields.${i}.label`}
                            />
                        </label>
                        <label>
                            Stable field code
                            <input
                                name={`fields.${i}.code`}
                                required
                                value={f.code}
                                {...fieldErrorProps(errors, `fields.${i}.code`)}
                                onChange={(e) =>
                                    update(i, { code: e.target.value })
                                }
                            />
                            <FieldError
                                errors={errors}
                                name={`fields.${i}.code`}
                            />
                        </label>
                        <label>
                            Section
                            <input
                                name={`fields.${i}.section`}
                                required
                                value={f.section}
                                {...fieldErrorProps(
                                    errors,
                                    `fields.${i}.section`,
                                )}
                                onChange={(e) =>
                                    update(i, { section: e.target.value })
                                }
                            />
                            <FieldError
                                errors={errors}
                                name={`fields.${i}.section`}
                            />
                        </label>
                        <label>
                            Type
                            <select
                                name={`fields.${i}.type`}
                                value={f.type}
                                {...fieldErrorProps(errors, `fields.${i}.type`)}
                                onChange={(e) =>
                                    update(i, {
                                        type: e.target.value as Field['type'],
                                    })
                                }
                            >
                                <option value="numeric">
                                    Measurement / number
                                </option>
                                <option value="text">Text / notes</option>
                                <option value="choice">Choice</option>
                            </select>
                            <FieldError
                                errors={errors}
                                name={`fields.${i}.type`}
                            />
                        </label>
                        <label>
                            Unit
                            <select
                                name={`fields.${i}.unit`}
                                value={f.unit}
                                {...fieldErrorProps(errors, `fields.${i}.unit`)}
                                onChange={(e) =>
                                    update(i, { unit: e.target.value })
                                }
                            >
                                <option value="inches">Inches</option>
                                <option value="centimeters">Centimeters</option>
                                <option value="none">None</option>
                            </select>
                            <FieldError
                                errors={errors}
                                name={`fields.${i}.unit`}
                            />
                        </label>
                        <label>
                            Sequence
                            <input
                                name={`fields.${i}.sequence`}
                                type="number"
                                min="0"
                                value={f.sequence}
                                {...fieldErrorProps(
                                    errors,
                                    `fields.${i}.sequence`,
                                )}
                                onChange={(e) =>
                                    update(i, {
                                        sequence: Number(e.target.value),
                                    })
                                }
                            />
                            <FieldError
                                errors={errors}
                                name={`fields.${i}.sequence`}
                            />
                        </label>
                        <label>
                            Optional minimum
                            <input
                                name={`fields.${i}.min`}
                                type="number"
                                step="any"
                                value={f.min ?? ''}
                                {...fieldErrorProps(errors, `fields.${i}.min`)}
                                onChange={(e) =>
                                    update(i, { min: e.target.value || null })
                                }
                            />
                            <FieldError
                                errors={errors}
                                name={`fields.${i}.min`}
                            />
                        </label>
                        <label>
                            Optional maximum
                            <input
                                name={`fields.${i}.max`}
                                type="number"
                                step="any"
                                value={f.max ?? ''}
                                {...fieldErrorProps(errors, `fields.${i}.max`)}
                                onChange={(e) =>
                                    update(i, { max: e.target.value || null })
                                }
                            />
                            <FieldError
                                errors={errors}
                                name={`fields.${i}.max`}
                            />
                        </label>
                        <label>
                            Required
                            <input
                                name={`fields.${i}.required`}
                                type="checkbox"
                                checked={f.required}
                                {...fieldErrorProps(
                                    errors,
                                    `fields.${i}.required`,
                                )}
                                onChange={(e) =>
                                    update(i, { required: e.target.checked })
                                }
                            />
                            <FieldError
                                errors={errors}
                                name={`fields.${i}.required`}
                            />
                        </label>
                        {f.type === 'choice' && (
                            <div className="full">
                                <p className="muted">Choice options</p>
                                {f.options.map((o, n) => (
                                    <div className="actions" key={n}>
                                        <input
                                            name={`fields.${i}.options.${n}.code`}
                                            aria-label="Choice code"
                                            value={o.code}
                                            {...fieldErrorProps(
                                                errors,
                                                `fields.${i}.options.${n}.code`,
                                            )}
                                            onChange={(e) =>
                                                update(i, {
                                                    options: f.options.map(
                                                        (v, k) =>
                                                            k === n
                                                                ? {
                                                                      ...v,
                                                                      code: e
                                                                          .target
                                                                          .value,
                                                                  }
                                                                : v,
                                                    ),
                                                })
                                            }
                                        />
                                        <FieldError
                                            errors={errors}
                                            name={`fields.${i}.options.${n}.code`}
                                        />
                                        <input
                                            name={`fields.${i}.options.${n}.label`}
                                            aria-label="Choice label"
                                            value={o.label}
                                            {...fieldErrorProps(
                                                errors,
                                                `fields.${i}.options.${n}.label`,
                                            )}
                                            onChange={(e) =>
                                                update(i, {
                                                    options: f.options.map(
                                                        (v, k) =>
                                                            k === n
                                                                ? {
                                                                      ...v,
                                                                      label: e
                                                                          .target
                                                                          .value,
                                                                  }
                                                                : v,
                                                    ),
                                                })
                                            }
                                        />
                                        <FieldError
                                            errors={errors}
                                            name={`fields.${i}.options.${n}.label`}
                                        />
                                        <button
                                            type="button"
                                            onClick={() =>
                                                update(i, {
                                                    options: f.options.filter(
                                                        (_, k) => k !== n,
                                                    ),
                                                })
                                            }
                                        >
                                            Remove
                                        </button>
                                    </div>
                                ))}
                                <Button
                                    secondary
                                    onClick={() =>
                                        update(i, {
                                            options: [
                                                ...f.options,
                                                { code: '', label: '' },
                                            ],
                                        })
                                    }
                                >
                                    Add option
                                </Button>
                            </div>
                        )}
                        <label className="full">
                            Help text
                            <input
                                name={`fields.${i}.help`}
                                value={f.help ?? ''}
                                {...fieldErrorProps(errors, `fields.${i}.help`)}
                                onChange={(e) =>
                                    update(i, { help: e.target.value })
                                }
                            />
                            <FieldError
                                errors={errors}
                                name={`fields.${i}.help`}
                            />
                        </label>
                        <Button
                            secondary
                            onClick={() =>
                                set({
                                    ...data,
                                    fields: data.fields.filter(
                                        (_, n) => n !== i,
                                    ),
                                })
                            }
                        >
                            Remove field
                        </Button>
                    </div>
                ))}
                <Button
                    secondary
                    onClick={() =>
                        set({
                            ...data,
                            fields: [
                                ...data.fields,
                                {
                                    code: '',
                                    label: '',
                                    section: 'Measurements',
                                    sequence: data.fields.length,
                                    type: 'numeric',
                                    unit: data.unit,
                                    required: false,
                                    min: null,
                                    max: null,
                                    help: '',
                                    options: [],
                                },
                            ],
                        })
                    }
                >
                    Add measurement field
                </Button>
            </div>
            <label>
                Notes
                <textarea
                    name="notes"
                    value={data.notes}
                    onChange={(e) => set({ ...data, notes: e.target.value })}
                    {...fieldErrorProps(errors, 'notes')}
                />
                <FieldError errors={errors} name="notes" />
            </label>
            <ErrorSummary errors={errors} />
            <div className="form-end">
                <Button type="submit" disabled={busy}>
                    {busy ? 'Saving…' : 'Save template'}
                </Button>
            </div>
        </form>
    );
}
