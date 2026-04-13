import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { buildStaffSession } from '../test/fixtures';

const staffApiMocks = vi.hoisted(() => ({
  loginStaff: vi.fn(),
}));

vi.mock('../core/api/staff-api', () => staffApiMocks);

import { Login } from './Login';

describe('Login auth contract', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('uses the shared staff auth API contract when no custom submit handler is provided', async () => {
    const onSuccess = vi.fn();
    staffApiMocks.loginStaff.mockResolvedValue(buildStaffSession());

    const { container } = render(<Login onSuccess={onSuccess} />);
    const [identifierInput, deviceInput] = screen.getAllByRole('textbox');
    const passwordInput = container.querySelector('input[type="password"]');

    if (!passwordInput) {
      throw new Error('Expected password input to exist.');
    }

    fireEvent.change(identifierInput, { target: { value: 'cashier-a' } });
    fireEvent.change(passwordInput, { target: { value: 'secret-123' } });
    fireEvent.change(deviceInput, { target: { value: 'POS 01' } });
    fireEvent.click(screen.getByRole('button', { name: 'Đăng nhập' }));

    await waitFor(() => expect(staffApiMocks.loginStaff).toHaveBeenCalledWith({
      identifier: 'cashier-a',
      password: 'secret-123',
      device_name: 'POS 01',
    }));
    expect(onSuccess).toHaveBeenCalled();
  });
});
