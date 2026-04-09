import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { Login } from './Login';
import { buildStaffSession } from '../test/fixtures';

describe('Login', () => {
  it('renders the session notice passed from the app shell', () => {
    render(<Login notice="Phien staff da het han." noticeTone="error" onSuccess={vi.fn()} />);

    expect(screen.getByText('Phien staff da het han.')).toBeInTheDocument();
  });

  it('shows validation feedback before calling the API', () => {
    render(<Login onSuccess={vi.fn()} />);

    fireEvent.click(screen.getByRole('button', { name: 'Dang nhap staff' }));

    expect(screen.getByText('Can nhap tai khoan va mat khau staff.')).toBeInTheDocument();
  });

  it('submits credentials through the provided auth callback', async () => {
    const onSubmit = vi.fn().mockResolvedValue(buildStaffSession());
    const onSuccess = vi.fn();

    render(<Login onSubmit={onSubmit} onSuccess={onSuccess} />);

    fireEvent.change(screen.getByPlaceholderText('vd: staff-auth-http'), { target: { value: 'cashier-a' } });
    fireEvent.change(screen.getByPlaceholderText('Nhap mat khau staff'), { target: { value: 'secret-123' } });
    fireEvent.click(screen.getByRole('button', { name: 'Dang nhap staff' }));

    expect(onSubmit).toHaveBeenCalledWith('cashier-a', 'secret-123', 'staff-web');
  });
});
