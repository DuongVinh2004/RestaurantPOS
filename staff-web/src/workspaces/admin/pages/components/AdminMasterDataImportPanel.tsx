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
      toast.success('Import preview completed.');
    },
    onError: (error) => {
      setCommitPayload(null);
      toast.error(formatApiError(error, 'Import preview failed.'));
    },
  });

  const commitMutation = useMutation({
    mutationFn: async () => {
      if (!commitPayload) {
        throw new Error('Preview a valid import before committing.');
      }

      return importAdminMasterData(selectedDomain, commitPayload);
    },
    onSuccess: (envelope) => {
      setResult(envelope.data);
      setCommitPayload(null);
      toast.success('Import commit completed.');
      onCommitted?.();
    },
    onError: (error) => {
      toast.error(formatApiError(error, 'Import commit failed.'));
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
          aria-label="Admin import domain"
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
              Required columns: {selectedDefinition.requiredColumns.join(', ')}
            </Typography.Text>
          </div>
        ) : null}

        <Input.TextArea
          aria-label="Admin import JSON rows"
          rows={6}
          value={rowsJson}
          placeholder='[{"name":"Breakfast","sort_order":10}]'
          onChange={(event) => setRowsJson(event.target.value)}
        />

        {parseError ? <InlineWarning title="Import payload is not ready" description={parseError} /> : null}

        <Space wrap>
          <Button
            type="primary"
            onClick={handlePreview}
            loading={dryRunMutation.isPending}
            disabled={commitMutation.isPending}
          >
            Preview dry run
          </Button>
          <Button
            danger
            onClick={handleCommit}
            loading={commitMutation.isPending}
            disabled={!commitPayload || dryRunMutation.isPending}
          >
            Commit import
          </Button>
        </Space>

        {commitPayload ? (
          <Typography.Text type="secondary">
            Commit will send Idempotency-Key {commitPayload.idempotencyKey}.
          </Typography.Text>
        ) : null}

        {dryRunMutation.error ? (
          <TransientFailureState
            title="Preview failed"
            description={formatApiError(dryRunMutation.error, 'Import preview failed.')}
          />
        ) : null}
        {commitMutation.error ? (
          <TransientFailureState
            title="Commit failed"
            description={formatApiError(commitMutation.error, 'Import commit failed.')}
          />
        ) : null}

        {result ? (
          <ImportResult result={result} summary={summary} />
        ) : (
          <EmptyBlock title="No import preview yet" description="Dry-run rows before enabling a commit." />
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
        <StatusChip label={result.can_commit ? 'Can commit' : 'Blocked'} tone={result.can_commit ? 'success' : 'warning'} />
        <StatusChip label={result.mode} tone="default" />
        {summary.batchId ? <StatusChip label={summary.batchId} tone="processing" /> : null}
      </Space>

      <div className="staff-kitchen-sync-grid">
        <Statistic title="Rows" value={summary.totalRows} />
        <Statistic title="Valid" value={summary.validRows} />
        <Statistic title="Invalid" value={summary.invalidRows} />
      </div>

      {result.schema.errors.length > 0 ? (
        <InlineWarning
          title="Schema errors"
          description={result.schema.errors.map((error) => `${error.field}: ${error.message}`).join(' ')}
        />
      ) : null}

      {summary.batchId ? (
        <Typography.Text type="secondary">
          Batch {summary.batchId} committed {summary.committedRows} rows.
        </Typography.Text>
      ) : null}

      <div className="staff-admin-surface-list">
        {result.rows.slice(0, 5).map((row) => (
          <div key={row.row_number} className="staff-admin-surface-item">
            <div>
              <strong>Row {row.row_number}</strong>
              <Typography.Paragraph type="secondary">
                {row.operation} / {row.status}
              </Typography.Paragraph>
            </div>
            <Space wrap size={6}>
              <StatusChip label={row.status} tone={row.status === 'valid' ? 'success' : 'warning'} />
            </Space>
            <Typography.Text type="secondary">
              {row.errors.length > 0 ? row.errors.map((error) => `${error.field}: ${error.message}`).join(' ') : 'No row errors'}
            </Typography.Text>
          </div>
        ))}
      </div>
    </Space>
  );
}
