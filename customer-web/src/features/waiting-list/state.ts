import type { CustomerWaitingListCollectionEnvelope, CustomerWaitingListEntry } from "@/lib/contracts/generated/restaurantpos-sdk";

export type WaitingListOwnerAction = "accept" | "arrival" | "decline" | "cancel";

type WaitingListResponseState = "none" | "accepted" | "arrival_confirmed" | "declined";

export type WaitingListJourneyState = {
  state: "waiting" | "invite_pending" | "accepted" | "arrival_confirmed" | "declined" | "expired" | "seated" | "cancelled" | "unknown";
  title: string;
  description: string;
  nextStep: string;
};

export type WaitingListOwnerActionPolicy = {
  availableActions: WaitingListOwnerAction[];
  title: string;
  description: string;
};

export type WaitingListSeatResultState = {
  state: "unavailable" | "waiting_for_staff" | "seated" | "cancelled";
  title: string;
  description: string;
  reservationId: number | null;
  tableLabel: string | null;
};

export type WaitingListRefreshPolicy = {
  mode: "manual";
  title: string;
  description: string;
};

type WaitingListEntryLike = CustomerWaitingListEntry | CustomerWaitingListCollectionEnvelope["data"][number];

export const waitingListActionLabels: Record<WaitingListOwnerAction, string> = {
  accept: "Nhận lời mời",
  arrival: "Xác nhận đã đến",
  decline: "Từ chối lời mời",
  cancel: "Hủy đăng ký chờ",
};

export function sortWaitingListEntries<T extends WaitingListEntryLike>(entries: T[]): T[] {
  return [...entries].sort((left, right) => {
    const requestedDelta = entryTimestamp(right.requested_at) - entryTimestamp(left.requested_at);

    return requestedDelta !== 0 ? requestedDelta : right.waiting_id - left.waiting_id;
  });
}

export function getWaitingListJourneyState(entry: WaitingListEntryLike): WaitingListJourneyState {
  const responseState = responseStateFromPayload(entry);

  if (entry.status === "Cancelled") {
    return {
      state: "cancelled",
      title: "Đã hủy đăng ký chờ",
      description: "Đăng ký chờ này không còn hiệu lực với tài khoản khách hàng.",
      nextStep: "Đăng ký lại sau nếu bạn vẫn cần bàn.",
    };
  }

  if (responseState === "declined") {
    return {
      state: "declined",
      title: "Đã từ chối lời mời",
      description: "Nhà hàng đã ghi nhận bạn từ chối lời mời này.",
      nextStep: "Chờ lời mời mới nếu nhà hàng mở thêm.",
    };
  }

  if (entry.status === "Seated" || Boolean(entry.seated_at)) {
    return {
      state: "seated",
      title: "Đã có bàn",
      description: "Nhà hàng đã ghi nhận bạn được xếp bàn.",
      nextStep: "Bạn không cần thao tác thêm trong danh sách chờ.",
    };
  }

  if (entry.status === "Waiting") {
    return {
      state: "waiting",
      title: "Đang chờ lời mời",
      description: "Nhà hàng đã nhận yêu cầu, nhưng chưa mở lời mời cho đăng ký này.",
      nextStep: "Chờ nhà hàng liên hệ, rồi bấm cập nhật khi nhân viên yêu cầu kiểm tra.",
    };
  }

  if (entry.status !== "Notified") {
    return {
      state: "unknown",
      title: "Trạng thái danh sách chờ đã thay đổi",
      description: "Đăng ký này đã chuyển sang trạng thái khác.",
      nextStep: "Cập nhật chi tiết trước khi thao tác tiếp.",
    };
  }

  if (responseState === "arrival_confirmed") {
    return {
      state: "arrival_confirmed",
      title: "Đã xác nhận đến nơi",
      description: "Bạn đã xác nhận có mặt. Nhân viên sẽ xếp bàn sau.",
      nextStep: "Chờ nhân viên xếp bàn, rồi cập nhật để xem kết quả mới nhất.",
    };
  }

  if (!isNotifyWindowOpen(entry)) {
    return {
      state: "expired",
      title: "Lời mời đã hết hạn",
      description: "Khung phản hồi lời mời hiện tại không còn hiệu lực.",
      nextStep: "Chờ lời mời mới hoặc liên hệ nhà hàng nếu nhân viên yêu cầu phản hồi lại.",
    };
  }

  if (responseState === "accepted") {
    return {
      state: "accepted",
      title: "Đã nhận lời mời",
      description: "Bạn đã nhận lời mời hiện tại. Nhà hàng vẫn cần bạn xác nhận khi đến hoặc nhân viên xếp bàn.",
      nextStep: "Xác nhận đã đến khi bạn tới nhà hàng.",
    };
  }

  return {
    state: "invite_pending",
    title: "Có lời mời cần phản hồi",
    description: "Nhà hàng đã mở lời mời cho đăng ký chờ này.",
    nextStep: formatNextStep(entry.next_step) ?? "Chọn thao tác phù hợp trước khi lời mời hết hạn.",
  };
}

export function getWaitingListOwnerActionPolicy(entry: WaitingListEntryLike): WaitingListOwnerActionPolicy {
  const availableActions = ownerActionsForResponseState(entry);

  if (entry.status === "Waiting") {
    return {
      availableActions,
      title: availableActions.includes("cancel") ? "Có thể hủy đăng ký chờ" : "Chưa có phản hồi trực tuyến",
      description: availableActions.includes("cancel")
        ? "Đăng ký này đang chờ lời mời, nên bạn chỉ có thể hủy nếu không còn cần bàn."
        : "Đăng ký chờ này hiện chỉ để xem.",
    };
  }

  if (entry.status !== "Notified") {
    return {
      availableActions,
      title: "Chưa có phản hồi trực tuyến",
      description: "Trạng thái này hiện chỉ để xem.",
    };
  }

  if (responseStateFromPayload(entry) === "declined") {
    return {
      availableActions: [],
      title: "Lời mời đã bị từ chối",
      description: "Bạn đã từ chối lời mời này, nên không còn phản hồi thêm.",
    };
  }

  if (!isNotifyWindowOpen(entry)) {
    return {
      availableActions: [],
      title: "Lời mời đã đóng",
      description: "Khung phản hồi hiện không còn hiệu lực. Hãy chờ nhà hàng mở lời mời mới.",
    };
  }

  if (responseStateFromPayload(entry) === "accepted") {
    return {
      availableActions,
      title: "Có thể xác nhận đã đến",
      description: "Bạn đã nhận lời mời. Hãy xác nhận khi tới nhà hàng, hoặc hủy nếu không còn cần bàn.",
    };
  }

  if (availableActions.length === 0 && entry.arrival_confirmation.staff_seat_required) {
    return {
      availableActions,
      title: "Đang chờ nhân viên xếp bàn",
      description: "Bạn đã xác nhận đến nơi hoặc nhân viên đang xử lý bước tiếp theo.",
    };
  }

  return {
    availableActions,
    title: availableActions.length > 0 ? "Có lời mời cần phản hồi" : "Chưa có phản hồi trực tuyến",
    description:
      availableActions.length > 0
        ? "Lời mời đang hiệu lực. Chọn phản hồi phù hợp trước khi hết hạn."
        : "Lời mời đang hiệu lực nhưng hiện chưa có thao tác khách hàng.",
  };
}

export function getWaitingListSeatResultState(entry: WaitingListEntryLike): WaitingListSeatResultState {
  const responseState = responseStateFromPayload(entry);

  if (entry.status === "Cancelled") {
    return {
      state: "cancelled",
      title: "Chưa có kết quả xếp bàn",
      description: "Đăng ký này đã hủy trước khi nhà hàng ghi nhận kết quả xếp bàn.",
      reservationId: null,
      tableLabel: null,
    };
  }

  if (entry.status === "Seated" || Boolean(entry.seated_at)) {
    return {
      state: "seated",
      title: "Đã ghi nhận xếp bàn",
      description: "Nhà hàng đã ghi nhận bạn được xếp bàn.",
      reservationId: null,
      tableLabel: null,
    };
  }

  if (entry.status === "Notified" && responseState === "arrival_confirmed" && entry.arrival_confirmation.staff_seat_required) {
    return {
      state: "waiting_for_staff",
      title: "Đang chờ kết quả xếp bàn",
      description: "Bạn đã xác nhận đến nơi. Hãy cập nhật khi nhân viên yêu cầu kiểm tra lại.",
      reservationId: null,
      tableLabel: null,
    };
  }

  return {
    state: "unavailable",
    title: "Chưa có kết quả xếp bàn",
    description:
      "Kết quả xếp bàn sẽ hiển thị khi nhà hàng cập nhật ổn định cho tài khoản của bạn.",
    reservationId: null,
    tableLabel: null,
  };
}

export function getWaitingListRefreshPolicy(): WaitingListRefreshPolicy {
  return {
    mode: "manual",
    title: "Cập nhật thủ công",
    description:
      "Danh sách chờ không tự đẩy thông báo vào trình duyệt. Bấm cập nhật khi nhân viên yêu cầu kiểm tra hoặc sau khi bạn phản hồi.",
  };
}

function ownerActionsFromPayload(entry: WaitingListEntryLike): WaitingListOwnerAction[] {
  const actions: WaitingListOwnerAction[] = [];

  if (entry.available_actions.accept) actions.push("accept");
  if (entry.available_actions.confirm_arrival) actions.push("arrival");
  if (entry.available_actions.decline) actions.push("decline");
  if (entry.available_actions.cancel) actions.push("cancel");

  return actions;
}

function ownerActionsForResponseState(entry: WaitingListEntryLike): WaitingListOwnerAction[] {
  const actions = ownerActionsFromPayload(entry);
  const responseState = responseStateFromPayload(entry);

  if (responseState === "arrival_confirmed" || responseState === "declined") {
    return [];
  }

  if (responseState === "accepted") {
    return actions.filter((action) => action !== "accept");
  }

  return actions;
}

function responseStateFromPayload(entry: WaitingListEntryLike): WaitingListResponseState {
  const value = "response_state" in entry ? entry.response_state : null;

  switch (value) {
    case "accepted":
    case "arrival_confirmed":
    case "declined":
      return value;
    default:
      return "none";
  }
}

function isNotifyWindowOpen(entry: WaitingListEntryLike): boolean {
  return Boolean(entry.notify_window.is_open && entry.window.is_notified_window_open);
}

function entryTimestamp(value: string | null): number {
  const timestamp = value ? Date.parse(value) : Number.NaN;

  return Number.isFinite(timestamp) ? timestamp : 0;
}

function formatNextStep(value: string | null | undefined): string | null {
  if (!value) {
    return null;
  }

  return value
    .replace(/[_-]+/g, " ")
    .replace(/\b\w/g, (letter) => letter.toUpperCase());
}
