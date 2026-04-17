import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import { staffRoutePaths } from '../router/workspace-paths';
import { StaffWorkspaceSwitcher } from './StaffWorkspaceSwitcher';

describe('StaffWorkspaceSwitcher', () => {
  it('does not render when the session has only one workspace', () => {
    render(
      <StaffWorkspaceSwitcher
        activeWorkspace="ops"
        options={[
          {
            workspace: 'ops',
            label: 'Ops',
            description: 'Operations workspace',
            landingPath: staffRoutePaths.ops.dashboard,
          },
        ]}
        onSwitchWorkspace={() => undefined}
      />,
    );

    expect(screen.queryByRole('combobox', { name: /switch workspace/i })).not.toBeInTheDocument();
  });

  it('renders a native select and forwards safe workspace switches', () => {
    const onSwitchWorkspace = vi.fn();

    render(
      <StaffWorkspaceSwitcher
        activeWorkspace="ops"
        options={[
          {
            workspace: 'ops',
            label: 'Ops',
            description: 'Operations workspace',
            landingPath: staffRoutePaths.ops.dashboard,
          },
          {
            workspace: 'kitchen',
            label: 'Kitchen',
            description: 'Kitchen workspace',
            landingPath: staffRoutePaths.kitchen.landing,
          },
        ]}
        onSwitchWorkspace={onSwitchWorkspace}
      />,
    );

    fireEvent.change(screen.getByRole('combobox', { name: /switch workspace/i }), {
      target: { value: 'kitchen' },
    });

    expect(onSwitchWorkspace).toHaveBeenCalledWith('kitchen');
  });
});
