import { Head, Link, router, usePage } from '@inertiajs/react';
import type { FormDataConvertible } from '@inertiajs/core';
import { useEffect, useId, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import {
    Scissors,
    LayoutDashboard,
    Users,
    Shirt,
    Wallet,
    ClipboardList,
    Settings2,
    Ruler,
    Bell,
    Search,
    Menu,
    X,
    LogOut,
    Plus,
    ArrowRight,
} from 'lucide-react';
import type { Base, Page } from './types';
import {
    allowNextUnsavedNavigation,
    confirmDirtyScopeClose,
    DirtyScope,
    ErrorSummary,
    FieldError,
    fieldErrorProps,
    useFirstErrorFocus,
    useUnsavedChanges,
} from './form-support';
import './studio.css';
export const money = (n: number, currency = 'PKR') =>
    `${currency} ${(n / 100).toLocaleString('en-PK', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
export const decimal = (n: number) =>
    `${Math.trunc(n / 100)}.${String(Math.abs(n % 100)).padStart(2, '0')}`;
export const stamp = (s: string | null | undefined, tz = 'Asia/Karachi') =>
    s
        ? new Date(s).toLocaleString('en-GB', {
              timeZone: tz,
              dateStyle: 'medium',
              timeStyle: 'short',
          })
        : '—';
export function localNow(tz = 'Asia/Karachi', source?: string) {
    const p = new Intl.DateTimeFormat('en-CA', {
        timeZone: tz,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hourCycle: 'h23',
    }).formatToParts(source ? new Date(source) : new Date());
    const get = (t: string) => p.find((x) => x.type === t)?.value;
    return `${get('year')}-${get('month')}-${get('day')}T${get('hour')}:${get('minute')}:${get('second')}`;
}
export function Badge({ stage }: { stage: string }) {
    return (
        <span
            className={`badge status-${stage.toLowerCase().replaceAll(' ', '-')}`}
        >
            {stage}
        </span>
    );
}
const nav = [
    ['dashboard', 'Overview', LayoutDashboard],
    ['orders', 'Stitching orders', Shirt],
    ['customers', 'Customers', Users],
    ['measurement-search', 'Customer Measurement Search', Search],
    ['billing', 'Billing & payments', Wallet],
    ['follow-ups', 'Follow-up', Bell],
    ['reports', 'Reports', ClipboardList],
    ['products', 'Services & prices', Scissors],
    ['templates', 'Measurements', Ruler],
    ['prices', 'Customer prices', Users],
    ['configuration', 'Configuration', Settings2],
    ['exports', 'Private exports', ClipboardList],
    ['audit', 'Audit history', ClipboardList],
] as const;
export function Shell({
    base,
    section,
    children,
    action,
    title,
}: {
    base: Base;
    section: string;
    children: ReactNode;
    action?: ReactNode;
    title?: string;
}) {
    const [open, setOpen] = useState(false);
    const [requestFailure, setRequestFailure] = useState('');
    const menuButtonRef = useRef<HTMLButtonElement>(null);
    const page = usePage();
    const flash = page.props.success as string | undefined;
    const errors = page.props.errors as Record<string, string>;
    useEffect(() => {
        const removeStart = router.on('start', () => setRequestFailure(''));
        const removeNetworkError = router.on('networkError', (event) => {
            event.preventDefault();
            setRequestFailure(
                'The request could not be completed. Check your connection and try again.',
            );
        });
        return () => {
            removeStart();
            removeNetworkError();
        };
    }, []);
    useEffect(() => {
        if (!open) return;
        const onKeyDown = (event: KeyboardEvent) => {
            if (event.key !== 'Escape') return;
            setOpen(false);
            menuButtonRef.current?.focus();
        };
        window.addEventListener('keydown', onKeyDown);
        return () => window.removeEventListener('keydown', onKeyDown);
    }, [open]);
    return (
        <div className="studio">
            <Head title={title ?? section} />
            <a className="skip-link" href="#studio-main">
                Skip to main content
            </a>
            <aside
                id="studio-navigation"
                className={open ? 'side open' : 'side'}
            >
                <button
                    type="button"
                    className="side-close"
                    aria-label="Close navigation"
                    onClick={() => {
                        setOpen(false);
                        menuButtonRef.current?.focus();
                    }}
                >
                    <X size={20} />
                </button>
                <Link href="/studio" className="brand">
                    <span>
                        <Scissors size={25} />
                    </span>
                    <div>
                        {base.settings.name}
                        <small>STITCHING MANAGEMENT</small>
                    </div>
                </Link>
                <div className="nav-label">YOUR WORKSPACE</div>
                <nav>
                    {nav.map(([key, label, Icon]) => (
                        <Link
                            key={key}
                            className={section === key ? 'selected' : ''}
                            href={
                                key === 'dashboard'
                                    ? '/studio'
                                    : `/studio/${key}`
                            }
                            onClick={() => setOpen(false)}
                        >
                            <Icon size={18} />
                            {label}
                        </Link>
                    ))}
                </nav>
                <div className="side-bottom">
                    <Link href="/settings/security">Password & security</Link>
                    <button
                        type="button"
                        onClick={() => router.post('/logout')}
                    >
                        <LogOut size={17} /> Sign out
                    </button>
                    <small>One business. Every detail in place.</small>
                </div>
            </aside>
            {open && (
                <button
                    type="button"
                    className="nav-backdrop"
                    aria-label="Close navigation"
                    onClick={() => {
                        setOpen(false);
                        menuButtonRef.current?.focus();
                    }}
                />
            )}
            <div className="workspace">
                <header className="topbar">
                    <button
                        ref={menuButtonRef}
                        type="button"
                        className="mobile-menu"
                        aria-label={
                            open ? 'Close navigation' : 'Open navigation'
                        }
                        aria-expanded={open}
                        aria-controls="studio-navigation"
                        onClick={() => setOpen(!open)}
                    >
                        {open ? <X /> : <Menu />}
                    </button>
                    <form action="/studio/orders" className="global-search">
                        <Search size={18} />
                        <input
                            aria-label="Search orders or customers"
                            name="q"
                            placeholder="Search order, customer or phone…"
                        />
                    </form>
                    <span className="owner-label">
                        OWNER WORKSPACE <span className="avatar">O</span>
                    </span>
                </header>
                <main id="studio-main" tabIndex={-1}>
                    <div className="page-heading">
                        <div>
                            <p className="eyebrow">
                                {section === 'dashboard'
                                    ? 'YOUR BUSINESS AT A GLANCE'
                                    : 'STITCHING STUDIO'}
                            </p>
                            <h1>
                                {title ??
                                    nav.find((n) => n[0] === section)?.[1] ??
                                    section}
                            </h1>
                        </div>
                        {action}
                    </div>
                    {flash && (
                        <div className="notice" role="status">
                            {flash}
                        </div>
                    )}
                    {requestFailure && (
                        <div className="error" role="alert">
                            {requestFailure}
                        </div>
                    )}
                    {Object.keys(errors ?? {}).length > 0 && (
                        <div className="error" role="alert">
                            {Object.values(errors).join(' ')}
                        </div>
                    )}
                    {children}
                </main>
                <footer>
                    Made for careful craft.{' '}
                    <span>
                        {base.settings.currency} · {base.settings.timezone}
                    </span>
                </footer>
            </div>
        </div>
    );
}
export function Panel({
    title,
    children,
    aside,
}: {
    title?: string;
    children: ReactNode;
    aside?: ReactNode;
}) {
    return (
        <section className="panel">
            {title && (
                <div className="panel-heading">
                    <h2>{title}</h2>
                    {aside}
                </div>
            )}
            {children}
        </section>
    );
}
export function Button({
    children,
    onClick,
    secondary = false,
    disabled = false,
    type = 'button',
}: {
    children: ReactNode;
    onClick?: () => void;
    secondary?: boolean;
    disabled?: boolean;
    type?: 'button' | 'submit';
}) {
    return (
        <button
            type={type}
            className={secondary ? 'button secondary' : 'button'}
            onClick={onClick}
            disabled={disabled}
        >
            {children}
        </button>
    );
}
export function NewButton({
    onClick,
    label = 'Create new',
}: {
    onClick: () => void;
    label?: string;
}) {
    return (
        <Button onClick={onClick}>
            <Plus size={17} />
            {label}
        </Button>
    );
}
export function Empty({
    label = 'No records yet. Add your first record to get started.',
}: {
    label?: string;
}) {
    return (
        <div className="empty">
            <Scissors size={32} />
            <h3>Everything starts with a first stitch.</h3>
            <p>{label}</p>
        </div>
    );
}
export function Pagination<T>({ page }: { page: Page<T> }) {
    return (
        <div className="pagination">
            <span>
                {page.total} records · Page {page.current_page} of{' '}
                {page.last_page}
            </span>
            <div>
                {page.prev_page_url && (
                    <Link href={page.prev_page_url}>Previous</Link>
                )}
                {page.next_page_url && (
                    <Link href={page.next_page_url}>
                        Next <ArrowRight size={14} />
                    </Link>
                )}
            </div>
        </div>
    );
}
export function DataTable<T extends object>({
    rows,
    columns,
}: {
    rows: T[];
    columns: { label: string; value: (row: T) => ReactNode }[];
}) {
    if (!rows.length) return <Empty />;
    return (
        <div
            className="table-wrap"
            role="region"
            aria-label="Data table"
            tabIndex={0}
        >
            <table>
                <thead>
                    <tr>
                        {columns.map((c) => (
                            <th key={c.label} scope="col">
                                {c.label}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {rows.map((r, i) => (
                        <tr key={i}>
                            {columns.map((c) => (
                                <td key={c.label}>{c.value(r)}</td>
                            ))}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
export function Modal({
    title,
    children,
    onClose,
}: {
    title: string;
    children: ReactNode;
    onClose: () => void;
}) {
    const ref = useRef<HTMLDialogElement>(null);
    const titleId = useId();
    const scopeId = useId();
    const previousFocus = useRef<HTMLElement | null>(null);

    const requestClose = () => {
        if (confirmDirtyScopeClose(scopeId)) onClose();
    };

    useEffect(() => {
        previousFocus.current = document.activeElement as HTMLElement | null;
        ref.current?.showModal();
        const dialog = ref.current;
        return () => {
            dialog?.close();
            previousFocus.current?.focus?.();
        };
    }, []);

    return (
        <dialog
            ref={ref}
            className="modal"
            aria-labelledby={titleId}
            onCancel={(event) => {
                event.preventDefault();
                requestClose();
            }}
            onClick={(event) => {
                if (event.target === ref.current) requestClose();
            }}
        >
            <DirtyScope scope={scopeId}>
                <div className="modal-head">
                    <h2 id={titleId}>{title}</h2>
                    <button
                        type="button"
                        aria-label="Close dialog"
                        onClick={requestClose}
                    >
                        <X />
                    </button>
                </div>
                {children}
            </DirtyScope>
        </dialog>
    );
}
export interface InputSpec {
    name: string;
    label: string;
    type?:
        | 'text'
        | 'number'
        | 'textarea'
        | 'checkbox'
        | 'select'
        | 'datetime-local'
        | 'hidden';
    options?: { value: string | number; label: string }[];
    required?: boolean;
    help?: string;
}
export type Payload = Record<string, FormDataConvertible>;
export function Form({
    url,
    initial,
    fields,
    submit = 'Save',
    onDone,
    children,
}: {
    url: string;
    initial: Payload;
    fields: InputSpec[];
    submit?: string;
    onDone?: () => void;
    children?: ReactNode;
}) {
    const [values, setValues] = useState<Payload>(initial);
    const initialRef = useRef<Payload>(initial);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);
    const submittingRef = useRef(false);
    const errorPrefix = useId();
    const isDirty =
        JSON.stringify(values) !== JSON.stringify(initialRef.current);
    const formRef = useFirstErrorFocus(errors);
    useUnsavedChanges(isDirty);

    const setData = (key: string, value: FormDataConvertible) => {
        setValues((current) => ({ ...current, [key]: value }));
        setErrors((current) => {
            if (!current[key]) return current;
            const next = { ...current };
            delete next[key];
            return next;
        });
    };

    return (
        <form
            ref={formRef}
            className="editor"
            aria-busy={processing}
            onSubmit={(event) => {
                event.preventDefault();
                if (processing || submittingRef.current) return;
                submittingRef.current = true;
                setProcessing(true);
                setErrors({});
                allowNextUnsavedNavigation();
                router.post(url, values, {
                    preserveScroll: true,
                    onSuccess: () => {
                        initialRef.current = { ...values };
                        onDone?.();
                    },
                    onError: (nextErrors) => setErrors(nextErrors),
                    onFinish: () => {
                        submittingRef.current = false;
                        setProcessing(false);
                    },
                });
            }}
        >
            {fields.map((field) => {
                const id = `field-${field.name.replace(/[^a-zA-Z0-9_-]+/g, '-')}`;
                const errorProps = fieldErrorProps(
                    errors,
                    field.name,
                    errorPrefix,
                );
                return (
                    <label
                        key={field.name}
                        htmlFor={id}
                        className={
                            field.type === 'checkbox'
                                ? 'check-label'
                                : field.type === 'hidden'
                                  ? 'hidden'
                                  : ''
                        }
                    >
                        {field.type !== 'hidden' && (
                            <span>
                                {field.label}
                                {field.required ? ' *' : ''}
                            </span>
                        )}
                        {field.type === 'textarea' ? (
                            <textarea
                                id={id}
                                name={field.name}
                                required={field.required}
                                value={inputText(values[field.name])}
                                onChange={(e) =>
                                    setData(field.name, e.target.value)
                                }
                                {...errorProps}
                            />
                        ) : field.type === 'select' ? (
                            <select
                                id={id}
                                name={field.name}
                                required={field.required}
                                value={inputText(values[field.name])}
                                onChange={(e) =>
                                    setData(field.name, e.target.value)
                                }
                                {...errorProps}
                            >
                                <option value="">Select…</option>
                                {field.options?.map((option) => (
                                    <option
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </option>
                                ))}
                            </select>
                        ) : field.type === 'checkbox' ? (
                            <input
                                id={id}
                                name={field.name}
                                type="checkbox"
                                checked={Boolean(values[field.name])}
                                onChange={(e) =>
                                    setData(field.name, e.target.checked)
                                }
                                {...errorProps}
                            />
                        ) : (
                            <input
                                id={id}
                                name={field.name}
                                type={field.type ?? 'text'}
                                step={
                                    field.type === 'datetime-local' ? 1 : 'any'
                                }
                                required={field.required}
                                value={inputText(values[field.name])}
                                onChange={(e) =>
                                    setData(field.name, e.target.value)
                                }
                                {...errorProps}
                            />
                        )}
                        {field.help && <small>{field.help}</small>}
                        <FieldError
                            errors={errors}
                            name={field.name}
                            prefix={errorPrefix}
                        />
                    </label>
                );
            })}
            {children}
            <ErrorSummary errors={errors} />
            <div className="form-end">
                <Button type="submit" disabled={processing}>
                    {processing ? 'Saving…' : submit}
                </Button>
                {processing && (
                    <span
                        className="saving-status"
                        role="status"
                        aria-live="polite"
                    >
                        Saving changes…
                    </span>
                )}
            </div>
        </form>
    );
}
export function Action({
    label,
    url,
    initial,
    fields = [],
    secondary = true,
    children,
}: {
    label: string;
    url: string;
    initial: Payload;
    fields?: InputSpec[];
    secondary?: boolean;
    children?: ReactNode;
}) {
    const [open, setOpen] = useState(false);
    return (
        <>
            <Button secondary={secondary} onClick={() => setOpen(true)}>
                {label}
            </Button>
            {open && (
                <Modal title={label} onClose={() => setOpen(false)}>
                    <Form
                        url={url}
                        initial={{ ...initial, key: crypto.randomUUID() }}
                        fields={fields}
                        submit={label}
                        onDone={() => setOpen(false)}
                    >
                        {children}
                    </Form>
                </Modal>
            )}
        </>
    );
}
export function Filters({
    section,
    filters,
    children,
}: {
    section: string;
    filters: Record<string, string>;
    children?: ReactNode;
}) {
    return (
        <form className="filter-bar" action={`/studio/${section}`}>
            <input
                name="q"
                aria-label="Search"
                defaultValue={filters.q ?? ''}
                placeholder="Search…"
            />
            {children}
            <Button type="submit" secondary>
                Apply filters
            </Button>
            <Link href={`/studio/${section}`}>Reset</Link>
        </form>
    );
}

function inputText(value: unknown): string {
    return typeof value === 'string' || typeof value === 'number'
        ? String(value)
        : '';
}
