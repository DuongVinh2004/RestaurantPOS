import type { StaffWorkspaceRouteDefinition } from '../routes';
import { staffRoutePaths } from '../../app/router/workspace-paths';

export const kitchenWorkspaceRoutes: Array<StaffWorkspaceRouteDefinition> = [
  {
    key: 'landing',
    path: '',
    absolutePath: staffRoutePaths.kitchen.landing,
    page: 'kitchen-landing',
    capability: 'kitchen.manage',
  },
  {
    key: 'board',
    path: 'board',
    absolutePath: staffRoutePaths.kitchen.board,
    page: 'kitchen-board',
    capability: 'kitchen.manage',
  },
];
