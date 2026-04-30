import type { StaffWorkspaceNavigationDefinition } from './types';
import { staffRoutePaths } from '../../app/router/workspace-paths';

export const kitchenNavigation: StaffWorkspaceNavigationDefinition = {
  workspace: 'kitchen',
  label: 'Bếp',
  description: 'Điều phối phiếu bếp theo trạm, không lẫn việc sàn hay back-office.',
  landingPath: staffRoutePaths.kitchen.landing,
  groups: [
    {
      key: 'kitchen-line',
      label: 'Chuyền bếp',
      items: [
        {
          key: 'kitchen-landing',
          label: 'Trạm bếp',
          path: staffRoutePaths.kitchen.landing,
          iconKey: 'kitchen',
          workspace: 'kitchen',
          capability: 'kitchen.manage',
          description: 'Chọn trạm bếp và mở màn hình điều phối món.',
          exact: true,
        },
        {
          key: 'kitchen-board',
          label: 'Phiếu bếp',
          path: staffRoutePaths.kitchen.board,
          iconKey: 'kitchen',
          workspace: 'kitchen',
          capability: 'kitchen.manage',
          description: 'Xử lý phiếu đang chờ, đang làm và đã sẵn sàng bằng thao tác nhanh cho bếp.',
        },
      ],
    },
  ],
};
