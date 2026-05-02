import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import {
  ActionBar,
  BulkActionBar,
  FiltersBar,
  FormField,
  FormSection,
  KPIGrid,
  SearchInput,
  SummaryCard,
} from './primitives';

describe('staff shared UI primitives', () => {
  it('renders operational bars, KPI cards, search, and form structure', () => {
    render(
      <>
        <ActionBar left={<span>Filters</span>} right={<button type="button">Refresh</button>} />
        <FiltersBar fields={<SearchInput aria-label="Search tables" placeholder="Search" />} actions={<button type="button">Reset</button>} />
        <KPIGrid>
          <SummaryCard label="Open orders" value="12" hint="Live" tone="processing" />
        </KPIGrid>
        <BulkActionBar selectedCount={2}>Assign</BulkActionBar>
        <FormSection title="Guest details" description="Visible labels are required.">
          <FormField label="Guest phone" htmlFor="guest-phone" help="Use the customer phone number.">
            <input id="guest-phone" />
          </FormField>
        </FormSection>
      </>,
    );

    expect(screen.getByText('Filters')).toBeInTheDocument();
    expect(screen.getByRole('searchbox', { name: 'Search tables' })).toBeInTheDocument();
    expect(screen.getByText('Open orders')).toBeInTheDocument();
    expect(screen.getByText('2 selected')).toBeInTheDocument();
    expect(screen.getByLabelText('Guest phone')).toBeInTheDocument();
  });
});
