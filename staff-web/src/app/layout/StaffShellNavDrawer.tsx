import type { ReactNode } from 'react';
import { Drawer } from 'antd';

export function StaffShellNavDrawer({
  content,
  open,
  onClose,
}: {
  content: ReactNode;
  open: boolean;
  onClose: () => void;
}) {
  return (
    <Drawer
      open={open}
      placement="left"
      styles={{ wrapper: { width: 280, maxWidth: '100vw' } }}
      title="Điều hướng ca làm"
      onClose={onClose}
      className="staff-shell-nav-drawer"
    >
      {content}
    </Drawer>
  );
}
