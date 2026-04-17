import { App as AntdApp } from 'antd';
import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { buildApiError } from '../../../test/fixtures';
import {
  ApiStateBlock,
  PermissionDeniedState,
} from './StateBlocks';

describe('StateBlocks', () => {
  it('maps stale write api errors into a conflict state with a retry action', () => {
    const handleRetry = vi.fn();

    render(
      <AntdApp>
        <ApiStateBlock
          error={buildApiError(409, {
            error_code: 'stale_write',
            message: 'State conflict detected.',
            request_id: 'req-stale-001',
          })}
          fallback="Không thể tải dữ liệu."
          onRetry={handleRetry}
        />
      </AntdApp>,
    );

    expect(screen.getByText('Dữ liệu đang dùng đã cũ')).toBeInTheDocument();
    expect(screen.getByText('Mã truy vết: req-stale-001')).toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: 'Tải lại' }));
    expect(handleRetry).toHaveBeenCalledTimes(1);
  });

  it('maps owner-scope denied errors into a branch and policy state', () => {
    render(
      <AntdApp>
        <ApiStateBlock
          error={buildApiError(403, {
            error_code: 'owner_scope_denied',
            message: 'Forbidden.',
            request_id: 'req-branch-001',
          })}
          fallback="Không thể tải dữ liệu."
        />
      </AntdApp>,
    );

    expect(screen.getByText('Ngữ cảnh branch hoặc policy đang chặn thao tác này')).toBeInTheDocument();
    expect(screen.getByText('Mã truy vết: req-branch-001')).toBeInTheDocument();
  });

  it('renders page permission states with both recovery actions', () => {
    render(
      <AntdApp>
        <PermissionDeniedState
          variant="page"
          title="Phiên hiện tại chưa có quyền"
          description="Hãy quay về hub truy cập hoặc dùng màn hình đã được cấp."
          primaryAction={<button type="button">Mở access hub</button>}
          secondaryAction={<button type="button">Mở màn hình được cấp</button>}
        />
      </AntdApp>,
    );

    expect(screen.getByText('Phiên hiện tại chưa có quyền')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Mở access hub' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Mở màn hình được cấp' })).toBeInTheDocument();
  });
});
