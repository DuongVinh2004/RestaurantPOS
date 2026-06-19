export type CommandArgumentDefinition = {
  name: string;
  type: 'string' | 'number' | 'boolean';
};

export type CommandDefinition = {
  name: string;
  description: string;
  expectedArgs: CommandArgumentDefinition[];
  isDestructive: boolean;
  requiredCapability?: string;
};

export const COMMAND_REGISTRY: Record<string, CommandDefinition> = {
  '/move-table': {
    name: '/move-table',
    description: 'Chuyển bàn (Move table)',
    expectedArgs: [
      { name: 'from_table_id', type: 'number' },
      { name: 'to_table_id', type: 'number' },
    ],
    isDestructive: true,
  },
  '/add-item': {
    name: '/add-item',
    description: 'Thêm món (Add item)',
    expectedArgs: [
      { name: 'item_code', type: 'string' },
      { name: 'quantity', type: 'number' },
    ],
    isDestructive: false,
  },
  '/open-table': {
    name: '/open-table',
    description: 'Mở chi tiết bàn (Open table)',
    expectedArgs: [{ name: 'table_id', type: 'number' }],
    isDestructive: false,
  },
  '/open-reservation': {
    name: '/open-reservation',
    description: 'Mở thông tin đặt bàn (Open reservation)',
    expectedArgs: [{ name: 'reservation_code', type: 'string' }],
    isDestructive: false,
  },
  '/open-bill': {
    name: '/open-bill',
    description: 'Mở hóa đơn (Open bill)',
    expectedArgs: [{ name: 'bill_code', type: 'string' }],
    isDestructive: false,
  },
  '/open-kds': {
    name: '/open-kds',
    description: 'Mở KDS (Open KDS)',
    expectedArgs: [],
    isDestructive: false,
  },
  '/open-shift': {
    name: '/open-shift',
    description: 'Mở ca làm việc (Open shift)',
    expectedArgs: [],
    isDestructive: false,
  },
};
