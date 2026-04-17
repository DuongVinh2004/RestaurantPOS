import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { StatusChip } from './StatusChip';

describe('StatusChip', () => {
  it('renders a semantic status class stack for tone and variant', () => {
    render(
      <StatusChip
        label="Ready"
        tone="success"
        variant="severity"
        appearance="filled"
      />,
    );

    const chip = screen.getByText('Sẵn sàng').closest('.staff-status-chip');
    expect(chip).toHaveClass('staff-status-chip-success');
    expect(chip).toHaveClass('staff-status-chip-severity');
    expect(chip).toHaveClass('staff-status-chip-filled');
  });
});
