import { Button, Card, Input, Select, Space, Statistic, Typography } from 'antd';
import { useMemo, useState } from 'react';
import { useMutation } from '@tanstack/react-query';
import type {
  AdminMasterDataCommitPayload,
  AdminMasterDataImportDomain,
  AdminMasterDataImportResult,
  AdminMasterDataImportRow,
} from '../../../../shared/api/staff-api';
import { importAdminMasterData } from '../../../../shared/api/staff-api';
import { formatApiError } from '../../../../shared/api/errors';
import type { AdminImportDomainOption } from '../../../../domains/admin/admin-master-data';
import {
  createAdminImportCommitPayload,
  parseAdminImportRows,
  summarizeAdminImportResult,
} from '../../../../domains/admin/admin-master-data';
import { EmptyBlock, InlineWarning, TransientFailureState } from '../../../../shared/ui/states/StateBlocks';
import { StatusChip } from '../../../../shared/ui/status/StatusChip';
import { toast } from '../../../../shared/ui/feedback/toast';

export function AdminMasterDataImportPanel({
  domains,
  title,
  description,
  onCommitted,
}: {
  domains: Array<AdminImportDomainOption>;
  title: string;
  description: string;
  onCommitted?: () => void;
}) {
  const [selectedDomain, setSelectedDomain] = useState<AdminMasterDataImportDomain>(domains[0]?.domain ?? 'branches');
  const [rowsJson, setRowsJson] = useState('');
  const [parseError, setParseError] = useState<string | null>(null);
  const [result, setResult] = useState<AdminMasterDataImportResult | null>(null);
  const [commitPayload, setCommitPayload] = useState<AdminMasterDataCommitPayload | null>(null);
  const selectedDefinition = domains.find((domain) => domain.domain === selectedDomain) ?? domains[0];
  const summary = useMemo(() => summarizeAdminImportResult(result), [result]);

  const dryRunMutation = useMutation({
    mutationFn: async (rows: Array<AdminMasterDataImportRow>) => importAdminMasterData(selectedDomain, {
      mode: 'dry_run',
      format: 'json',
      rows,
    }),
    onSuccess: (envelope, rows) => {
      setResult(envelope.data);
      setCommitPayload(envelope.data.can_commit ? createAdminImportCommitPayload(selectedDomain, rows) : null);
      toast.success('Đã chạy thử dữ liệu nhập.');
    },
    onError: (error) => {
      setCommitPayload(null);
      toast.error(formatApiError(error, 'Chạy thử dữ liệu nhập chưa thành công.'));
    },
  });

  const commitMutation = useMutation({
    mutationFn: async () => {
      if (!commitPayload) {
        throw new Error('Hãy chạy thử dữ liệu hợp lệ trước khi ghi nhận.');
      }

      return importAdminMasterData(selectedDomain, commitPayload);
    },
    onSuccess: (envelope) => {
      setResult(envelope.data);
      setCommitPayload(null);
      toast.success('Đã ghi nhận dữ liệu nhập.');
      onCommitted?.();
    },
    onError: (error) => {
      toast.error(formatApiError(error, 'Ghi nhận dữ liệu nhập chưa thành công.'));
    },
  });

  function handlePreview() {
    const parsed = parseAdminImportRows(rowsJson);
    if (!parsed.ok) {
      setParseError(parsed.error);
      setResult(null);
      setCommitPayload(null);
      return;
    }

    setParseError(null);
    setCommitPayload(null);
    void dryRunMutation.mutateAsync(parsed.rows);
  }

  function handleCommit() {
    if (!commitPayload || commitMutation.isPending) {
      return;
    }

    void commitMutation.mutateAsync();
  }

  return (
    <Card className="staff-workspace-detail-card" title={title}>
      <Space orientation="vertical" size={12} style={{ width: '100%' }}>
        <Typography.Paragraph type="secondary">{description}</Typography.Paragraph>

        <Select
          aria-label="Nhóm dữ liệu nhập quản trị"
          style={{ width: '100%' }}
          value={selectedDomain}
          options={domains.map((domain) => ({
            value: domain.domain,
            label: domain.label,
          }))}
          onChange={(value) => {
            setSelectedDomain(value);
            setResult(null);
            setCommitPayload(null);
            setParseError(null);
          }}
        />

        {selectedDefinition ? (
          <div className="staff-admin-note-item">
            <span />
            <Typography.Text>
              Cột bắt buộc: {selectedDefinition.requiredColumns.join(', ')}
            </Typography.Text>
          </div>
        ) : null}

        <Input.TextArea
          aria-label="Dòng JSON nhập liệu quản trị"
          rows={6}
          value={rowsJson}
          placeholder='[{"name":"Bữa trưa","sort_order":10}]'
          onChange={(event) => setRowsJson(event.target.value)}
        />

        {parseError ? <InlineWarning title="Dữ liệu nhập chưa sẵn sàng" description={parseError} /> : null}

        <Space wrap>
          <Button
            type="primary"
            onClick={handlePreview}
            loading={dryRunMutation.isPending}
            disabled={commitMutation.isPending}
          >
            Chạy thử
          </Button>
          <Button
            danger
            onClick={handleCommit}
            loading={commitMutation.isPending}
            disabled={!commitPayload || dryRunMutation.isPending}
          >
            Ghi nhận dữ liệu
          </Button>
        </Space>

        {commitPayload ? (
          <Typography.Text type="secondary">
            Khi ghi nhận, hệ thống sẽ gửi Idempotency-Key {commitPayload.idempotencyKey}.
          </Typography.Text>
        ) : null}

        {dryRunMutation.error ? (
          <TransientFailureState
            title="Chạy thử chưa thành công"
            description={formatApiError(dryRunMutation.error, 'Chạy thử dữ liệu nhập chưa thành công.')}
          />
        ) : null}
        {commitMutation.error ? (
          <TransientFailureState
            title="Ghi nhận chưa thành công"
            description={formatApiError(commitMutation.error, 'Ghi nhận dữ liệu nhập chưa thành công.')}
          />
        ) : null}

        {result ? (
          <ImportResult result={result} summary={summary} />
        ) : (
          <EmptyBlock title="Chưa có kết quả chạy thử" description="Hãy chạy thử dữ liệu trước khi bật nút ghi nhận." />
        )}
      </Space>
    </Card>
  );
}

function ImportResult({
  result,
  summary,
}: {
  result: AdminMasterDataImportResult;
  summary: ReturnType<typeof summarizeAdminImportResult>;
}) {
  return (
    <Space orientation="vertical" size={12} style={{ width: '100%' }}>
      <Space wrap>
        <StatusChip label={result.can_commit ? 'Có thể ghi nhận' : 'Đang bị chặn'} tone={result.can_commit ? 'success' : 'warning'} />
        <StatusChip label={result.mode === 'dry_run' ? 'Chạy thử' : 'Ghi nhận'} tone="default" />
        {summary.batchId ? <StatusChip label={summary.batchId} tone="processing" /> : null}
      </Space>

      <div className="staff-kitchen-sync-grid">
        <Statistic title="Dòng" value={summary.totalRows} />
        <Statistic title="Hợp lệ" value={summary.validRows} />
        <Statistic title="Lỗi" value={summary.invalidRows} />
      </div>

      {result.schema.errors.length > 0 ? (
        <InlineWarning
          title="Lỗi cấu trúc dữ liệu"
          description={result.schema.errors.map((error) => `${error.field}: ${error.message}`).join(' ')}
        />
      ) : null}

      {summary.batchId ? (
        <Typography.Text type="secondary">
          Lô {summary.batchId} đã ghi nhận {summary.committedRows} dòng.
        </Typography.Text>
      ) : null}

      <div className="staff-admin-surface-list">
        {result.rows.slice(0, 5).map((row) => (
          <div key={row.row_number} className="staff-admin-surface-item">
            <div>
              <strong>Dòng {row.row_number}</strong>
              <Typography.Paragraph type="secondary">
                {formatImportOperation(row.operation)} / {formatImportRowStatus(row.status)}
              </Typography.Paragraph>
            </div>
            <Space wrap size={6}>
              <StatusChip label={formatImportRowStatus(row.status)} tone={row.status === 'valid' ? 'success' : 'warning'} />
            </Space>
            <Typography.Text type="secondary">
              {row.errors.length > 0 ? row.errors.map((error) => `${error.field}: ${error.message}`).join(' ') : 'Dòng này không có lỗi.'}
            </Typography.Text>
          </div>
        ))}
      </div>
    </Space>
  );
}

function formatImportOperation(operation: string): string {
  switch (operation) {
    case 'create':
      return 'Tạo mới';
    case 'update':
      return 'Cập nhật';
    case 'noop':
      return 'Giữ nguyên';
    default:
      return operation || 'Không rõ thao tác';
  }
}

function formatImportRowStatus(status: string): string {
  switch (status) {
    case 'valid':
      return 'Hợp lệ';
    case 'invalid':
      return 'Có lỗi';
    default:
      return status || 'Không rõ trạng thái';
  }
}
