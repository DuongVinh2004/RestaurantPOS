import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { Button } from 'antd';
import { PageHeader } from './PageHeader';

describe('PageHeader', () => {
  it('renders the structured header regions with the provided content', () => {
    render(
      <PageHeader
        className="test-page-header"
        eyebrow="Operations"
        title="Dashboard"
        description="Read the hottest work first."
        meta={<span>Meta block</span>}
        context={<span>Context chip</span>}
        extra={<Button>Refresh</Button>}
      />,
    );

    expect(screen.getByText('Operations')).toBeInTheDocument();
    expect(screen.getByRole('heading', { name: 'Dashboard' })).toBeInTheDocument();
    expect(screen.getByText('Read the hottest work first.')).toBeInTheDocument();
    expect(screen.getByText('Meta block')).toBeInTheDocument();
    expect(screen.getByText('Context chip')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Refresh' })).toBeInTheDocument();
  });
});
