import type { StaffWorkspaceNavigationDefinition } from './types';
import { staffRoutePaths } from '../../app/router/workspace-paths';

export const kitchenNavigation: StaffWorkspaceNavigationDefinition = {
  workspace: 'kitchen',
  label: 'Kitchen',
  description: 'Station-first ticket operations without floor or back-office noise.',
  landingPath: staffRoutePaths.kitchen.landing,
  groups: [
    {
      key: 'kitchen-line',
      label: 'Line',
      items: [
        {
          key: 'kitchen-landing',
          label: 'Line control',
          path: staffRoutePaths.kitchen.landing,
          iconKey: 'kitchen',
          workspace: 'kitchen',
          capability: 'kitchen.manage',
          description: 'Choose station context and open the operational line.',
          exact: true,
        },
        {
          key: 'kitchen-board',
          label: 'Ticket queue',
          path: staffRoutePaths.kitchen.board,
          iconKey: 'kitchen',
          workspace: 'kitchen',
          capability: 'kitchen.manage',
          description: 'Work queued, in-prep, and ready tickets with kitchen fast actions.',
        },
      ],
    },
  ],
};
