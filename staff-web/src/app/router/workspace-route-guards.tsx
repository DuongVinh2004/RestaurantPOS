import { useEffect } from 'react';
import { Button } from 'antd';
import { Navigate, Outlet } from 'react-router-dom';
import type { PropsWithChildren } from 'react';
import { PermissionDeniedState } from '../../shared/ui/states/StateBlocks';
import { can } from '../../shared/auth/capabilities';
import { isWorkspaceAvailable, type WorkspaceId } from '../../workspaces/workspaces';
import { resolveWorkspaceLandingPath } from './navigation';
import { resolveRecommendedStaffPath } from './session-paths';
import { staffRoutePaths } from './workspace-paths';
import { useAuthStore } from '../store/auth-store';
import { useWorkspaceStore } from '../store/workspace-store';

export function WorkspaceBoundary({ workspace }: { workspace: WorkspaceId }) {
  const session = useAuthStore((state) => state.session);
  const setActiveWorkspace = useWorkspaceStore((state) => state.setActiveWorkspace);

  useEffect(() => {
    if (session && isWorkspaceAvailable(session, workspace)) {
      setActiveWorkspace(workspace);
    }
  }, [session, setActiveWorkspace, workspace]);

  if (!session) {
    return <Navigate to={staffRoutePaths.login} replace />;
  }

  if (!isWorkspaceAvailable(session, workspace)) {
    return <Navigate to={resolveRecommendedStaffPath(session)} replace />;
  }

  return <Outlet />;
}

export function WorkspaceRoute({
  capability,
  children,
  workspace,
}: PropsWithChildren<{
  capability?: string;
  workspace: WorkspaceId;
}>) {
  const session = useAuthStore((state) => state.session);
  const nextPath = resolveRecommendedStaffPath(session);

  if (!session) {
    return <Navigate to={staffRoutePaths.login} replace />;
  }

  if (!isWorkspaceAvailable(session, workspace)) {
    return <Navigate to={nextPath} replace />;
  }

  if (capability && !can(session, capability)) {
    return (
      <PermissionDeniedState
        variant="page"
        title="Phiên hiện tại chưa có quyền"
        description={`Màn hình này chỉ mở khi phiên nhân viên có quyền ${capability}. Hãy quay về hub truy cập hoặc dùng màn hình đã được cấp cho phiên hiện tại.`}
        primaryAction={<Button type="primary" href={staffRoutePaths.access}>Mở access hub</Button>}
        secondaryAction={nextPath !== staffRoutePaths.access ? <Button href={nextPath}>Mở màn hình được cấp</Button> : undefined}
      />
    );
  }

  return <>{children}</>;
}

export function WorkspaceIndexRedirect({ workspace }: { workspace: WorkspaceId }) {
  const session = useAuthStore((state) => state.session);

  if (!session) {
    return <Navigate to={staffRoutePaths.login} replace />;
  }

  if (!isWorkspaceAvailable(session, workspace)) {
    return <Navigate to={resolveRecommendedStaffPath(session)} replace />;
  }

  return <Navigate to={resolveWorkspaceLandingPath(session, workspace)} replace />;
}
