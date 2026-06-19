import { Button, Card, Col, Input, Row, Select, Space, Statistic, Switch, Typography } from 'antd';
import { useMemo, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { benefitsImportDomains } from '../../../../domains/admin/admin-master-data';
import {
  createAdminBenefitVoucher,
  createAdminLoyaltyTier,
  exportAdminMasterData,
  getAdminBenefitSettings,
  listAdminBenefitVouchers,
  listAdminLoyaltyTiers,
  updateAdminBenefitVoucher,
  updateAdminLoyaltyTier,
  upsertAdminBenefitSetting,
  type AdminBenefitSettingPayload,
} from '../../../../shared/api/staff-api';
import { formatApiError } from '../../../../shared/api/errors';
import { formatDateTime, humanizeCode } from '../../../../shared/utils/format';
import { PageHeader } from '../../../../shared/ui/layout/PageHeader';
import { SplitWorkspace } from '../../../../shared/ui/layout/SplitWorkspace';
import { ApiStateBlock, EmptyBlock, InlineLoading } from '../../../../shared/ui/states/StateBlocks';
import { StatusChip } from '../../../../shared/ui/status/StatusChip';
import { toast } from '../../../../shared/ui/feedback/toast';
import { AdminMasterDataImportPanel } from '../components/AdminMasterDataImportPanel';

type BenefitSettingKey = AdminBenefitSettingPayload['setting_key'];
type DiscountType = 'Fixed' | 'Percent' | 'FreeItem';
type RecordRow = Record<string, unknown>;

const discountTypeOptions = [
  { value: 'Fixed', label: 'Giảm số tiền' },
  { value: 'Percent', label: 'Giảm phần trăm' },
  { value: 'FreeItem', label: 'Tặng món' },
] satisfies Array<{ value: DiscountType; label: string }>;

const benefitSettingOptions = [
  { value: 'loyalty.enabled', label: 'Bật điểm thưởng' },
  { value: 'loyalty.earn_amount_per_point', label: 'Số tiền tích 1 điểm' },
  { value: 'loyalty.redeem_amount_per_point', label: 'Giá trị quy đổi 1 điểm' },
  { value: 'loyalty.min_redeem_points', label: 'Điểm tối thiểu để redeem' },
  { value: 'voucher.lock_minutes', label: 'Thời gian khóa voucher' },
] satisfies Array<{ value: BenefitSettingKey; label: string }>;

export function AdminBenefitsPage() {
  const queryClient = useQueryClient();
  const [voucherQuery, setVoucherQuery] = useState('');
  const [tierQuery, setTierQuery] = useState('');
  const [selectedVoucherId, setSelectedVoucherId] = useState<number | null>(null);
  const [selectedTierId, setSelectedTierId] = useState<number | null>(null);
  const [voucherForm, setVoucherForm] = useState({
    code: '',
    discountType: 'Fixed' as DiscountType,
    discountValue: '',
    description: '',
    active: true,
  });
  const [tierForm, setTierForm] = useState({
    code: '',
    name: '',
    minPoints: '',
    active: true,
  });
  const [settingForm, setSettingForm] = useState({
    key: 'loyalty.enabled' as BenefitSettingKey,
    value: 'true',
  });
  const [lastExport, setLastExport] = useState<RecordRow | Array<RecordRow> | null>(null);

  const vouchersQuery = useQuery({
    queryKey: ['admin-benefits-vouchers', voucherQuery],
    queryFn: () => listAdminBenefitVouchers({ q: voucherQuery || undefined, per_page: 25 }),
  });
  const tiersQuery = useQuery({
    queryKey: ['admin-benefits-loyalty-tiers', tierQuery],
    queryFn: () => listAdminLoyaltyTiers({ q: tierQuery || undefined, per_page: 25 }),
  });
  const settingsQuery = useQuery({
    queryKey: ['admin-benefits-settings'],
    queryFn: () => getAdminBenefitSettings(),
  });

  const vouchers = useMemo(() => recordsFromPayload(vouchersQuery.data?.data), [vouchersQuery.data?.data]);
  const tiers = useMemo(() => recordsFromPayload(tiersQuery.data?.data), [tiersQuery.data?.data]);
  const settings = useMemo(() => recordsFromPayload(settingsQuery.data?.data), [settingsQuery.data?.data]);
  const selectedVoucher = useMemo(
    () => vouchers.find((voucher) => rowId(voucher, ['voucher_id', 'id']) === selectedVoucherId) ?? null,
    [selectedVoucherId, vouchers],
  );
  const selectedTier = useMemo(
    () => tiers.find((tier) => rowId(tier, ['tier_id', 'loyalty_tier_id', 'id']) === selectedTierId) ?? null,
    [selectedTierId, tiers],
  );

  const createVoucherMutation = useMutation({
    mutationFn: () => {
      if (voucherForm.code.trim() === '') {
        throw new Error('Hãy nhập mã voucher.');
      }

      return createAdminBenefitVoucher({
        code: voucherForm.code.trim(),
        discount_type: voucherForm.discountType,
        discount_value: numericOrNull(voucherForm.discountValue),
        description: emptyToNull(voucherForm.description),
        is_active: voucherForm.active,
      });
    },
    onSuccess: async () => {
      setVoucherForm((current) => ({ ...current, code: '', discountValue: '', description: '' }));
      await queryClient.invalidateQueries({ queryKey: ['admin-benefits-vouchers'] });
      toast.success('Đã tạo voucher.');
    },
    onError: (error) => toast.error(formatApiError(error, 'Chưa tạo được voucher.')),
  });

  const updateVoucherMutation = useMutation({
    mutationFn: () => {
      if (!selectedVoucherId || !selectedVoucher) {
        throw new Error('Hãy chọn voucher cần cập nhật.');
      }

      const rowVersion = rowNumber(selectedVoucher, 'row_version');
      if (!rowVersion) {
        throw new Error('Voucher đang chọn không có row_version từ backend.');
      }

      return updateAdminBenefitVoucher(selectedVoucherId, {
        row_version: rowVersion,
        description: emptyToNull(voucherForm.description),
        discount_value: numericOrNull(voucherForm.discountValue),
        is_active: voucherForm.active,
      });
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['admin-benefits-vouchers'] });
      toast.success('Đã cập nhật voucher.');
    },
    onError: (error) => toast.error(formatApiError(error, 'Chưa cập nhật được voucher.')),
  });

  const toggleVoucherMutation = useMutation({
    mutationFn: (variables: { id: number, rowVersion: number, isActive: boolean }) => updateAdminBenefitVoucher(variables.id, {
      row_version: variables.rowVersion,
      is_active: variables.isActive,
    } as any),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['admin-benefits-vouchers'] });
      toast.success('Đã cập nhật trạng thái voucher.');
    },
    onError: (error) => toast.error(formatApiError(error, 'Chưa cập nhật được voucher.')),
  });

  const createTierMutation = useMutation({
    mutationFn: () => {
      const minPoints = Number(tierForm.minPoints);
      if (tierForm.code.trim() === '' || tierForm.name.trim() === '' || !Number.isFinite(minPoints) || minPoints < 0) {
        throw new Error('Hãy nhập mã, tên hạng và điểm tối thiểu hợp lệ.');
      }

      return createAdminLoyaltyTier({
        tier_code: tierForm.code.trim(),
        tier_name: tierForm.name.trim(),
        min_points: minPoints,
        benefits_json: null,
        is_active: tierForm.active,
      });
    },
    onSuccess: async () => {
      setTierForm((current) => ({ ...current, code: '', name: '', minPoints: '' }));
      await queryClient.invalidateQueries({ queryKey: ['admin-benefits-loyalty-tiers'] });
      toast.success('Đã tạo hạng thân thiết.');
    },
    onError: (error) => toast.error(formatApiError(error, 'Chưa tạo được hạng thân thiết.')),
  });

  const updateTierMutation = useMutation({
    mutationFn: () => {
      if (!selectedTierId || !selectedTier) {
        throw new Error('Hãy chọn hạng thân thiết cần cập nhật.');
      }

      const rowVersion = rowNumber(selectedTier, 'row_version');
      const minPoints = Number(tierForm.minPoints);
      if (!rowVersion) {
        throw new Error('Hạng đang chọn không có row_version từ backend.');
      }

      return updateAdminLoyaltyTier(selectedTierId, {
        row_version: rowVersion,
        tier_name: emptyToNull(tierForm.name) ?? undefined,
        min_points: Number.isFinite(minPoints) && minPoints >= 0 ? minPoints : undefined,
        is_active: tierForm.active,
      });
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['admin-benefits-loyalty-tiers'] });
      toast.success('Đã cập nhật hạng thân thiết.');
    },
    onError: (error) => toast.error(formatApiError(error, 'Chưa cập nhật được hạng thân thiết.')),
  });

  const toggleTierMutation = useMutation({
    mutationFn: (variables: { id: number, rowVersion: number, isActive: boolean }) => updateAdminLoyaltyTier(variables.id, {
      row_version: variables.rowVersion,
      is_active: variables.isActive,
    } as any),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['admin-benefits-loyalty-tiers'] });
      toast.success('Đã cập nhật trạng thái hạng thân thiết.');
    },
    onError: (error) => toast.error(formatApiError(error, 'Chưa cập nhật được hạng thân thiết.')),
  });

  const upsertSettingMutation = useMutation({
    mutationFn: () => upsertAdminBenefitSetting({
      setting_key: settingForm.key,
      value: settingForm.value.trim(),
    }),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['admin-benefits-settings'] });
      toast.success('Đã lưu cấu hình ưu đãi.');
    },
    onError: (error) => toast.error(formatApiError(error, 'Chưa lưu được cấu hình ưu đãi.')),
  });

  const exportMutation = useMutation({
    mutationFn: (domain: 'benefit-vouchers' | 'loyalty-tiers') => exportAdminMasterData(domain),
    onSuccess: (envelope) => {
      setLastExport(envelope.data);
      toast.success('Đã tải dữ liệu export từ backend.');
    },
    onError: (error) => toast.error(formatApiError(error, 'Chưa export được dữ liệu ưu đãi.')),
  });

  const main = (
    <Space orientation="vertical" size={16} style={{ width: '100%' }}>
      <PageHeader
        eyebrow="Admin benefits"
        title="Voucher và khách thân thiết"
        description="Quản lý master data ưu đãi bằng contract admin hiện có, bao gồm CRUD tối thiểu, import/export và cấu hình điểm thưởng."
        extra={(
          <Space wrap>
            <Button onClick={() => vouchersQuery.refetch()} loading={vouchersQuery.isFetching}>Làm mới voucher</Button>
            <Button onClick={() => tiersQuery.refetch()} loading={tiersQuery.isFetching}>Làm mới hạng</Button>
          </Space>
        )}
      />

      <Row gutter={[16, 16]}>
        <Col xs={24} md={8}><Card><Statistic title="Voucher" value={vouchers.length} /></Card></Col>
        <Col xs={24} md={8}><Card><Statistic title="Hạng thân thiết" value={tiers.length} /></Card></Col>
        <Col xs={24} md={8}><Card><Statistic title="Cấu hình" value={settings.length} /></Card></Col>
      </Row>

      <Row gutter={[16, 16]}>
        <Col xs={24} lg={12}>
          <Card title="Voucher">
            <Space orientation="vertical" size={12} style={{ width: '100%' }}>
              <Input.Search
                aria-label="Tìm voucher"
                allowClear
                value={voucherQuery}
                placeholder="Mã voucher hoặc mô tả"
                onChange={(event) => setVoucherQuery(event.target.value)}
                onSearch={(value) => setVoucherQuery(value.trim())}
              />
              {vouchersQuery.isLoading ? <InlineLoading tip="Đang tải voucher..." /> : null}
              {vouchersQuery.error ? <ApiStateBlock error={vouchersQuery.error} fallback="Không thể tải voucher." onRetry={() => vouchersQuery.refetch()} /> : null}
              {!vouchersQuery.isLoading && !vouchersQuery.error && vouchers.length === 0 ? <EmptyBlock title="Chưa có voucher" description="Backend không trả về voucher nào theo bộ lọc hiện tại." /> : null}
              <div className="staff-admin-surface-list">
                {vouchers.map((voucher) => {
                  const id = rowId(voucher, ['voucher_id', 'id']);
                  return (
                    <button
                      key={id ?? rowString(voucher, 'code') ?? JSON.stringify(voucher)}
                      type="button"
                      className={`staff-admin-surface-item staff-clickable-surface ${selectedVoucherId === id ? 'staff-row-selected' : ''}`}
                      onClick={() => {
                        setSelectedVoucherId(id);
                        setVoucherForm((current) => ({
                          ...current,
                          description: rowString(voucher, 'description') ?? '',
                          discountValue: stringValue(voucher.discount_value),
                          active: rowBoolean(voucher, 'is_active') ?? current.active,
                        }));
                      }}
                    >
                      <strong>{rowString(voucher, 'code') ?? `Voucher #${id ?? 'n/a'}`}</strong>
                      <Typography.Text type="secondary">
                        {humanizeCode(rowString(voucher, 'discount_type') ?? 'unknown')} / {rowString(voucher, 'expiry_date') ? `Hết hạn ${formatDateTime(rowString(voucher, 'expiry_date'))}` : 'Không có hạn hiển thị'}
                      </Typography.Text>
                      <Space wrap>
                        <StatusChip label={rowBoolean(voucher, 'is_active') === false ? 'Tắt' : 'Đang bật'} tone={rowBoolean(voucher, 'is_active') === false ? 'warning' : 'success'} />
                        {rowNumber(voucher, 'row_version') ? <StatusChip label={`rv ${rowNumber(voucher, 'row_version')}`} tone="default" /> : null}
                        {id && rowNumber(voucher, 'row_version') && (
                          <Button
                            size="small"
                            danger
                            onClick={(e) => {
                              e.stopPropagation();
                              if (confirm(`Bạn có chắc muốn ${rowBoolean(voucher, 'is_active') === false ? 'mở lại' : 'tạm tắt'} voucher này?`)) {
                                toggleVoucherMutation.mutate({
                                  id: id as number,
                                  rowVersion: rowNumber(voucher, 'row_version') as number,
                                  isActive: rowBoolean(voucher, 'is_active') === false,
                                });
                              }
                            }}
                          >
                            {rowBoolean(voucher, 'is_active') === false ? 'Mở lại' : 'Tạm tắt'}
                          </Button>
                        )}
                      </Space>
                    </button>
                  );
                })}
              </div>
            </Space>
          </Card>
        </Col>
        <Col xs={24} lg={12}>
          <Card title="Hạng khách thân thiết">
            <Space orientation="vertical" size={12} style={{ width: '100%' }}>
              <Input.Search
                aria-label="Tìm hạng thân thiết"
                allowClear
                value={tierQuery}
                placeholder="Mã hoặc tên hạng"
                onChange={(event) => setTierQuery(event.target.value)}
                onSearch={(value) => setTierQuery(value.trim())}
              />
              {tiersQuery.isLoading ? <InlineLoading tip="Đang tải hạng thân thiết..." /> : null}
              {tiersQuery.error ? <ApiStateBlock error={tiersQuery.error} fallback="Không thể tải hạng thân thiết." onRetry={() => tiersQuery.refetch()} /> : null}
              {!tiersQuery.isLoading && !tiersQuery.error && tiers.length === 0 ? <EmptyBlock title="Chưa có hạng thân thiết" description="Backend không trả về hạng nào theo bộ lọc hiện tại." /> : null}
              <div className="staff-admin-surface-list">
                {tiers.map((tier) => {
                  const id = rowId(tier, ['tier_id', 'loyalty_tier_id', 'id']);
                  return (
                    <button
                      key={id ?? rowString(tier, 'tier_code') ?? JSON.stringify(tier)}
                      type="button"
                      className={`staff-admin-surface-item staff-clickable-surface ${selectedTierId === id ? 'staff-row-selected' : ''}`}
                      onClick={() => {
                        setSelectedTierId(id);
                        setTierForm((current) => ({
                          ...current,
                          name: rowString(tier, 'tier_name') ?? '',
                          minPoints: stringValue(tier.min_points),
                          active: rowBoolean(tier, 'is_active') ?? current.active,
                        }));
                      }}
                    >
                      <strong>{rowString(tier, 'tier_name') ?? rowString(tier, 'tier_code') ?? `Hạng #${id ?? 'n/a'}`}</strong>
                      <Typography.Text type="secondary">Từ {rowNumber(tier, 'min_points') ?? 0} điểm</Typography.Text>
                      <Space wrap>
                        <StatusChip label={rowBoolean(tier, 'is_active') === false ? 'Tắt' : 'Đang bật'} tone={rowBoolean(tier, 'is_active') === false ? 'warning' : 'success'} />
                        {rowNumber(tier, 'row_version') ? <StatusChip label={`rv ${rowNumber(tier, 'row_version')}`} tone="default" /> : null}
                        {id && rowNumber(tier, 'row_version') && (
                          <Button
                            size="small"
                            danger
                            onClick={(e) => {
                              e.stopPropagation();
                              if (confirm(`Bạn có chắc muốn ${rowBoolean(tier, 'is_active') === false ? 'mở lại' : 'tạm tắt'} hạng này?`)) {
                                toggleTierMutation.mutate({
                                  id: id as number,
                                  rowVersion: rowNumber(tier, 'row_version') as number,
                                  isActive: rowBoolean(tier, 'is_active') === false,
                                });
                              }
                            }}
                          >
                            {rowBoolean(tier, 'is_active') === false ? 'Mở lại' : 'Tạm tắt'}
                          </Button>
                        )}
                      </Space>
                    </button>
                  );
                })}
              </div>
            </Space>
          </Card>
        </Col>
      </Row>
    </Space>
  );

  const side = (
    <Space orientation="vertical" size={16} style={{ width: '100%' }}>
      <Card title="Tạo/cập nhật voucher">
        <Space orientation="vertical" size={12} style={{ width: '100%' }}>
          <Input aria-label="Mã voucher" placeholder="Mã voucher" value={voucherForm.code} onChange={(event) => setVoucherForm((current) => ({ ...current, code: event.target.value }))} />
          <Select<DiscountType> aria-label="Loại giảm giá" value={voucherForm.discountType} options={discountTypeOptions} onChange={(value) => setVoucherForm((current) => ({ ...current, discountType: value }))} />
          <Input aria-label="Giá trị giảm" inputMode="decimal" placeholder="Giá trị giảm" value={voucherForm.discountValue} onChange={(event) => setVoucherForm((current) => ({ ...current, discountValue: event.target.value }))} />
          <Input.TextArea aria-label="Mô tả voucher" rows={3} placeholder="Mô tả" value={voucherForm.description} onChange={(event) => setVoucherForm((current) => ({ ...current, description: event.target.value }))} />
          <Switch checked={voucherForm.active} checkedChildren="Bật" unCheckedChildren="Tắt" onChange={(checked) => setVoucherForm((current) => ({ ...current, active: checked }))} />
          <Space wrap>
            <Button type="primary" onClick={() => createVoucherMutation.mutate()} loading={createVoucherMutation.isPending}>Tạo voucher</Button>
            <Button onClick={() => updateVoucherMutation.mutate()} loading={updateVoucherMutation.isPending} disabled={!selectedVoucher}>Cập nhật voucher đang chọn</Button>
          </Space>
        </Space>
      </Card>

      <Card title="Tạo/cập nhật hạng">
        <Space orientation="vertical" size={12} style={{ width: '100%' }}>
          <Input aria-label="Mã hạng" placeholder="Mã hạng" value={tierForm.code} onChange={(event) => setTierForm((current) => ({ ...current, code: event.target.value }))} />
          <Input aria-label="Tên hạng" placeholder="Tên hạng" value={tierForm.name} onChange={(event) => setTierForm((current) => ({ ...current, name: event.target.value }))} />
          <Input aria-label="Điểm tối thiểu" inputMode="numeric" placeholder="Điểm tối thiểu" value={tierForm.minPoints} onChange={(event) => setTierForm((current) => ({ ...current, minPoints: event.target.value }))} />
          <Switch checked={tierForm.active} checkedChildren="Bật" unCheckedChildren="Tắt" onChange={(checked) => setTierForm((current) => ({ ...current, active: checked }))} />
          <Space wrap>
            <Button type="primary" onClick={() => createTierMutation.mutate()} loading={createTierMutation.isPending}>Tạo hạng</Button>
            <Button onClick={() => updateTierMutation.mutate()} loading={updateTierMutation.isPending} disabled={!selectedTier}>Cập nhật hạng đang chọn</Button>
          </Space>
        </Space>
      </Card>

      <Card title="Cấu hình ưu đãi">
        <Space orientation="vertical" size={12} style={{ width: '100%' }}>
          <Select<BenefitSettingKey> aria-label="Khóa cấu hình ưu đãi" value={settingForm.key} options={benefitSettingOptions} onChange={(value) => setSettingForm((current) => ({ ...current, key: value }))} />
          <Input aria-label="Giá trị cấu hình ưu đãi" value={settingForm.value} onChange={(event) => setSettingForm((current) => ({ ...current, value: event.target.value }))} />
          <Button type="primary" onClick={() => upsertSettingMutation.mutate()} loading={upsertSettingMutation.isPending}>Lưu cấu hình</Button>
          {settings.length > 0 ? (
            <div className="staff-admin-surface-list">
              {settings.slice(0, 5).map((setting, index) => (
                <div key={`${rowString(setting, 'setting_key') ?? index}`} className="staff-admin-surface-item">
                  <strong>{rowString(setting, 'setting_key') ?? `Cấu hình #${index + 1}`}</strong>
                  <Typography.Text type="secondary">{stringValue(setting.value)}</Typography.Text>
                </div>
              ))}
            </div>
          ) : <EmptyBlock title="Chưa có cấu hình" description="Backend chưa trả về cấu hình ưu đãi." />}
        </Space>
      </Card>

      <AdminMasterDataImportPanel
        domains={benefitsImportDomains}
        title="Nhập voucher / hạng"
        description="Dry-run trước khi commit. Commit luôn đi với Idempotency-Key do adapter tạo."
        onCommitted={() => {
          void queryClient.invalidateQueries({ queryKey: ['admin-benefits-vouchers'] });
          void queryClient.invalidateQueries({ queryKey: ['admin-benefits-loyalty-tiers'] });
        }}
      />

      <Card title="Export ưu đãi">
        <Space orientation="vertical" size={12} style={{ width: '100%' }}>
          <Space wrap>
            <Button onClick={() => exportMutation.mutate('benefit-vouchers')} loading={exportMutation.isPending}>Export voucher</Button>
            <Button onClick={() => exportMutation.mutate('loyalty-tiers')} loading={exportMutation.isPending}>Export hạng</Button>
          </Space>
          {lastExport ? <pre className="staff-json-preview">{JSON.stringify(lastExport, null, 2)}</pre> : <Typography.Text type="secondary">Chưa có payload export trong phiên này.</Typography.Text>}
        </Space>
      </Card>
    </Space>
  );

  return <SplitWorkspace main={main} side={side} variant="balanced" />;
}

function recordsFromPayload(payload: unknown): Array<RecordRow> {
  if (Array.isArray(payload)) {
    return payload.filter(isRecord);
  }

  if (!isRecord(payload)) {
    return [];
  }

  for (const key of ['data', 'items', 'rows', 'settings', 'vouchers', 'loyalty_tiers']) {
    const value = payload[key];
    if (Array.isArray(value)) {
      return value.filter(isRecord);
    }
  }

  return Object.values(payload).every((value) => isRecord(value))
    ? Object.values(payload).filter(isRecord)
    : [];
}

function rowId(row: RecordRow, keys: Array<string>): number | null {
  for (const key of keys) {
    const value = rowNumber(row, key);
    if (value) {
      return value;
    }
  }

  return null;
}

function rowNumber(row: RecordRow, key: string): number | null {
  const value = row[key];
  if (typeof value === 'number' && Number.isFinite(value)) {
    return value;
  }

  if (typeof value === 'string' && value.trim() !== '') {
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : null;
  }

  return null;
}

function rowString(row: RecordRow, key: string): string | null {
  const value = row[key];
  return typeof value === 'string' && value.trim() !== '' ? value : null;
}

function rowBoolean(row: RecordRow, key: string): boolean | null {
  const value = row[key];
  return typeof value === 'boolean' ? value : null;
}

function numericOrNull(value: string): number | null {
  const parsed = Number(value);
  return value.trim() !== '' && Number.isFinite(parsed) ? parsed : null;
}

function emptyToNull(value: string): string | null {
  const trimmed = value.trim();
  return trimmed === '' ? null : trimmed;
}

function stringValue(value: unknown): string {
  if (typeof value === 'string') {
    return value;
  }

  if (typeof value === 'number' || typeof value === 'boolean') {
    return String(value);
  }

  return '';
}

function isRecord(value: unknown): value is RecordRow {
  return value !== null && typeof value === 'object' && !Array.isArray(value);
}
