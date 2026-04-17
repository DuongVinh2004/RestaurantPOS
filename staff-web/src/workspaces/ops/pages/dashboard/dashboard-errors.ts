import { formatStaffFacingApiError } from '../../../../shared/api/errors';

export function dashboardErrorMessage(error: unknown, scope: DashboardErrorScope): string {
  return formatStaffFacingApiError(error, fallbackByScope[scope]);
}

type DashboardErrorScope =
  | 'tables'
  | 'guest-flow'
  | 'kitchen'
  | 'finance'
  | 'cashier'
  | 'conversations'
  | 'reporting';

const fallbackByScope: Record<DashboardErrorScope, string> = {
  tables: 'Sơ đồ bàn tạm thời chưa tải được. Hãy làm mới dữ liệu hoặc thử lại sau ít phút.',
  'guest-flow': 'Dữ liệu đặt bàn và chờ bàn tạm thời chưa sẵn sàng. Hãy thử lại sau ít phút.',
  kitchen: 'Hàng bếp tạm thời chưa tải được. Hãy làm mới dữ liệu hoặc kiểm tra lại sau.',
  finance: 'Dữ liệu thanh toán và đối soát tạm thời chưa sẵn sàng. Hãy thử lại sau ít phút.',
  cashier: 'Tình trạng ca thu ngân tạm thời chưa tải được. Hãy làm mới dữ liệu rồi thử lại.',
  conversations: 'Hộp thư hỗ trợ tạm thời chưa tải được. Hãy thử lại sau hoặc kiểm tra lại kết nối.',
  reporting: 'Snapshot giám sát tạm thời chưa sẵn sàng. Hãy làm mới lại hoặc kiểm tra lại sau.',
};
