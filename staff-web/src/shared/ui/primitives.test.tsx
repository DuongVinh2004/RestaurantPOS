import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import {
  ActionBar,
  BulkActionBar,
  FiltersBar,
  FormField,
  FormSection,
  SearchInput,
} from './primitives';

describe('staff shared UI primitives', () => {
  it('renders operational bars, KPI cards, search, and form structure', () => {
    render(
      <>
        <ActionBar left={<span>Bộ lọc</span>} right={<button type="button">Làm mới</button>} />
        <FiltersBar fields={<SearchInput aria-label="Tìm bàn" />} actions={<button type="button">Đặt lại</button>} />

        <BulkActionBar selectedCount={2}>Gán</BulkActionBar>
        <FormSection title="Thông tin khách" description="Luôn giữ nhãn hiển thị rõ ràng.">
          <FormField label="Số điện thoại khách" htmlFor="guest-phone" help="Dùng số khách cung cấp.">
            <input id="guest-phone" />
          </FormField>
        </FormSection>
      </>,
    );

    expect(screen.getByText('Bộ lọc')).toBeInTheDocument();
    expect(screen.getByRole('searchbox', { name: 'Tìm bàn' })).toBeInTheDocument();

    expect(screen.getByText('2 dòng đã chọn')).toBeInTheDocument();
    expect(screen.getByLabelText('Số điện thoại khách')).toBeInTheDocument();
  });
});
