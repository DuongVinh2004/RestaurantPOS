import { useStaffSession } from '../../app/session-context';
import { EmptyState, Panel, StatusPill } from '../../components/ui';

export function AccessPage() {
  const { session } = useStaffSession();

  if (!session) {
    return null;
  }

  const readiness = session.startup.readiness;
  const defaultBranch = session.startup.default_branch;
  const activeShift = session.startup.active_cashier_shift;
  const moneyPathsBlocked = readiness.requires_cashier_shift && readiness.cashier_shift === 'action_required';
  const blockers = [
    readiness.access !== 'ready'
      ? 'Tài khoản này chưa được cấp quyền để vào màn hình làm việc.'
      : null,
    readiness.branch !== 'ready'
      ? 'Chưa xác định được chi nhánh mặc định.'
      : null,
    moneyPathsBlocked
      ? 'Hãy mở ca thu ngân để dùng thanh toán, hoàn tiền và màn hình thu ngân.'
      : null,
  ].filter((value): value is string => value !== null);
  const title = readiness.access !== 'ready'
    ? 'Tài khoản chưa đủ quyền'
    : moneyPathsBlocked
      ? 'Đã vào ca, nhưng chưa mở ca thu ngân'
      : readiness.operator_ready
        ? 'Mọi thứ đã sẵn sàng'
        : 'Thiếu thông tin để bắt đầu ca';
  const description = readiness.access !== 'ready'
    ? 'Hãy dùng tài khoản đã được phân quyền cho màn hình nhân viên.'
    : moneyPathsBlocked
      ? 'Bạn vẫn có thể xem các mục khác nếu đã được cấp quyền, nhưng các thao tác thu tiền sẽ chỉ mở khi ca thu ngân đang hoạt động.'
      : 'Hệ thống cần đủ thông tin về chi nhánh và ca làm trước khi vào màn hình vận hành.';

  return (
    <div className="space-y-6">
      <Panel>
        <p className="eyebrow">Trạng thái truy cập</p>
        <h2 className="mt-2 text-2xl font-semibold tracking-tight text-slate-950">{title}</h2>
        <p className="mt-3 max-w-3xl text-sm leading-7 text-slate-600">{description}</p>
        <div className="mt-4 flex flex-wrap gap-2">
          <StatusPill value={`Quyền ${translateReadiness(readiness.access)}`} tone={readiness.access === 'ready' ? 'success' : 'warning'} />
          <StatusPill value={`Chi nhánh ${translateReadiness(readiness.branch)}`} tone={readiness.branch === 'ready' ? 'success' : 'warning'} />
          <StatusPill
            value={`Ca ${translateReadiness(readiness.cashier_shift)}`}
            tone={readiness.cashier_shift === 'ready' ? 'success' : readiness.cashier_shift === 'action_required' ? 'warning' : 'neutral'}
          />
          <StatusPill value={readiness.operator_ready ? 'Sẵn sàng làm việc' : 'Cần chuẩn bị thêm'} tone={readiness.operator_ready ? 'success' : 'warning'} />
        </div>
      </Panel>

      <Panel>
        <p className="eyebrow">Thông tin đã nhận</p>
        <div className="mt-4 grid gap-4 lg:grid-cols-2">
          <div className="rounded-[24px] border border-slate-200 bg-slate-50 px-4 py-4">
            <p className="text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-500">Chi nhánh mặc định</p>
            <p className="mt-3 text-base font-semibold text-slate-950">
              {defaultBranch ? `${defaultBranch.branch_name} (${defaultBranch.branch_code})` : 'Chưa có chi nhánh mặc định'}
            </p>
            <p className="mt-2 text-sm text-slate-600">
              {defaultBranch
                ? `Múi giờ ${defaultBranch.timezone ?? 'không rõ'} · Tiền tệ ${defaultBranch.currency ?? 'không rõ'}`
                : 'Cần xác định chi nhánh mặc định để vào màn hình làm việc đúng bối cảnh.'}
            </p>
          </div>
          <div className="rounded-[24px] border border-slate-200 bg-slate-50 px-4 py-4">
            <p className="text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-500">Ca thu ngân</p>
            <p className="mt-3 text-base font-semibold text-slate-950">
              {activeShift ? `${activeShift.shift_code} (${translateReadiness(activeShift.status)})` : 'Chưa có ca đang mở'}
            </p>
            <p className="mt-2 text-sm text-slate-600">
              {activeShift
                ? `Thiết bị ${activeShift.terminal_code ?? 'không rõ'} · Chi nhánh ${activeShift.branch?.branch_code ?? activeShift.branch_id}`
                : readiness.cashier_shift === 'not_applicable'
                  ? 'Phiên này không bắt buộc phải có ca thu ngân.'
                  : 'Cần mở ca thu ngân trước khi dùng các thao tác thu tiền.'}
            </p>
          </div>
        </div>
      </Panel>

      <EmptyState
        title={blockers.length > 0 ? 'Việc cần xử lý trước khi vào ca' : 'Trang dự phòng đang hoạt động'}
        description={
          blockers.length > 0
            ? blockers.join(' ')
            : 'Phiên đã sẵn sàng. Hãy làm mới phiên hoặc mở lại mục bạn muốn dùng.'
        }
      />
    </div>
  );
}

function translateReadiness(value: string) {
  switch (value) {
    case 'ready':
      return 'sẵn sàng';
    case 'action_required':
      return 'cần xử lý';
    case 'not_applicable':
      return 'không áp dụng';
    case 'missing':
      return 'còn thiếu';
    case 'capability_missing':
      return 'thiếu quyền';
    case 'open':
      return 'đang mở';
    default:
      return value;
  }
}
