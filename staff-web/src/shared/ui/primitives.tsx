import type { ReactNode } from 'react';
import {
  Button,
  DatePicker,
  Drawer,
  Input,
  Modal,
  Pagination as AntPagination,
  Segmented,
  Table,
  type ButtonProps,
  type DrawerProps,
  type InputProps,
  type ModalProps,
  type PaginationProps,
  type SegmentedProps,
  type TableProps,
} from 'antd';
import { Search } from 'lucide-react';

function joinClasses(...classes: Array<string | false | null | undefined>) {
  return classes.filter(Boolean).join(' ');
}

export function AppShell({
  sidebar,
  topbar,
  alerts,
  children,
  className,
}: {
  sidebar: ReactNode;
  topbar: ReactNode;
  alerts?: ReactNode;
  children: ReactNode;
  className?: string;
}) {
  return (
    <div className={joinClasses('staff-shell-layout', className)}>
      <aside className="staff-shell-sider">{sidebar}</aside>
      <div className="staff-shell-main">
        {topbar}
        <main className="staff-shell-content">
          {alerts}
          {children}
        </main>
      </div>
    </div>
  );
}

export function ActionBar({
  left,
  right,
  className,
}: {
  left?: ReactNode;
  right?: ReactNode;
  className?: string;
}) {
  return (
    <div className={joinClasses('staff-action-bar', className)}>
      <div className="staff-action-bar-left">{left}</div>
      <div className="staff-action-bar-right">{right}</div>
    </div>
  );
}

export function FiltersBar({
  fields,
  actions,
  className,
}: {
  fields: ReactNode;
  actions?: ReactNode;
  className?: string;
}) {
  return (
    <div className={joinClasses('staff-filters-bar', className)}>
      <div className="staff-filters-bar-fields">{fields}</div>
      {actions ? <div className="staff-filters-bar-actions">{actions}</div> : null}
    </div>
  );
}

export function SearchInput({
  value,
  onChange,
  onSearch,
  placeholder = 'Tìm kiếm',
  className,
  ...props
}: InputProps & {
  value?: string;
  onSearch?: (value: string) => void;
}) {
  return (
    <Input.Search
      allowClear
      prefix={<Search size={15} aria-hidden="true" />}
      value={value}
      onChange={onChange}
      onSearch={onSearch}
      placeholder={placeholder}
      className={joinClasses('staff-search-input', className)}
      {...props}
    />
  );
}

export function SegmentedTabs<T extends string | number = string>({
  className,
  ...props
}: SegmentedProps<T> & {
  className?: string;
}) {
  return <Segmented<T> className={joinClasses('staff-segmented-tabs', className)} {...props} />;
}

export function DataTable<T extends object>({
  className,
  errorState,
  emptyState,
  pagination,
  ...props
}: TableProps<T> & {
  errorState?: ReactNode;
  emptyState?: ReactNode;
}) {
  if (errorState) {
    return <div className="staff-data-table-state">{errorState}</div>;
  }

  if (!props.loading && emptyState && (props.dataSource?.length ?? 0) === 0) {
    return <div className="staff-data-table-state">{emptyState}</div>;
  }

  return (
    <Table<T>
      size="small"
      sticky
      className={joinClasses('staff-data-table', className)}
      pagination={pagination === undefined ? false : pagination}
      {...props}
    />
  );
}

export function SummaryCard({
  label,
  value,
  hint,
  delta,
  tone = 'default',
  className,
}: {
  label: string;
  value: ReactNode;
  hint?: ReactNode;
  delta?: ReactNode;
  tone?: 'default' | 'processing' | 'success' | 'warning' | 'error';
  className?: string;
}) {
  return (
    <div className={joinClasses('staff-summary-card', `staff-summary-card-${tone}`, className)}>
      <span className="staff-summary-card-label">{label}</span>
      <strong className="staff-summary-card-value">{value}</strong>
      {delta ? <span className="staff-summary-card-delta">{delta}</span> : null}
      {hint ? <span className="staff-summary-card-hint">{hint}</span> : null}
    </div>
  );
}

export function KPIGrid({
  children,
  className,
}: {
  children: ReactNode;
  className?: string;
}) {
  return <div className={joinClasses('staff-kpi-grid', className)}>{children}</div>;
}

export function BulkActionBar({
  selectedCount,
  children,
  onClear,
  clearLabel = 'Bỏ chọn',
  className,
}: {
  selectedCount: number;
  children?: ReactNode;
  onClear?: () => void;
  clearLabel?: string;
  className?: string;
}) {
  if (selectedCount <= 0) {
    return null;
  }

  return (
    <div className={joinClasses('staff-bulk-action-bar', className)}>
      <strong>{selectedCount} dòng đã chọn</strong>
      {children}
      {onClear ? (
        <Button type="text" onClick={onClear}>
          {clearLabel}
        </Button>
      ) : null}
    </div>
  );
}

export function SideDrawer({
  title,
  children,
  footer,
  className,
  ...props
}: DrawerProps & {
  title: ReactNode;
  footer?: ReactNode;
}) {
  return (
    <Drawer
      title={title}
      className={joinClasses('staff-side-drawer', className)}
      styles={{ body: { padding: 16 }, footer: { padding: 16 } }}
      footer={footer}
      {...props}
    >
      {children}
    </Drawer>
  );
}

export function ConfirmDialog({
  open,
  title,
  message,
  confirmLabel,
  cancelLabel = 'Đóng',
  destructive = false,
  confirming = false,
  onConfirm,
  onCancel,
  ...props
}: Omit<ModalProps, 'open' | 'title' | 'onCancel'> & {
  open: boolean;
  title: ReactNode;
  message: ReactNode;
  confirmLabel: string;
  cancelLabel?: string;
  destructive?: boolean;
  confirming?: boolean;
  onConfirm: () => void;
  onCancel: () => void;
}) {
  return (
    <Modal
      open={open}
      title={title}
      onOk={onConfirm}
      onCancel={onCancel}
      okText={confirmLabel}
      cancelText={cancelLabel}
      confirmLoading={confirming}
      okButtonProps={{ danger: destructive }}
      {...props}
    >
      <div className="staff-confirm-dialog-message">{message}</div>
    </Modal>
  );
}

export function FormSection({
  title,
  description,
  children,
  className,
}: {
  title: string;
  description?: string;
  children: ReactNode;
  className?: string;
}) {
  return (
    <section className={joinClasses('staff-form-section', className)}>
      <div className="staff-form-section-header">
        <span className="staff-form-section-title">{title}</span>
        {description ? <p className="staff-form-section-description">{description}</p> : null}
      </div>
      {children}
    </section>
  );
}

export function FormField({
  label,
  htmlFor,
  help,
  error,
  children,
  className,
}: {
  label: string;
  htmlFor?: string;
  help?: ReactNode;
  error?: ReactNode;
  children: ReactNode;
  className?: string;
}) {
  const errorId = htmlFor && error ? `${htmlFor}-error` : undefined;

  return (
    <div className={joinClasses('staff-form-field', className)}>
      <label className="staff-form-field-label" htmlFor={htmlFor}>
        {label}
      </label>
      {children}
      {help ? <span className="staff-form-field-help">{help}</span> : null}
      {error ? (
        <span id={errorId} className="staff-form-field-error">
          {error}
        </span>
      ) : null}
    </div>
  );
}

export function DateRangePicker({
  className,
  ...props
}: React.ComponentProps<typeof DatePicker.RangePicker> & {
  className?: string;
}) {
  return <DatePicker.RangePicker className={joinClasses('staff-date-range-picker', className)} {...props} />;
}

export function Pagination({
  className,
  ...props
}: PaginationProps & {
  className?: string;
}) {
  return <AntPagination className={joinClasses('staff-pagination', className)} size="small" {...props} />;
}

export function PrimaryActionButton(props: ButtonProps) {
  return <Button type="primary" {...props} />;
}

export { StatusChip as StatusBadge } from './status/StatusChip';
