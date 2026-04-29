import { normalizeApiError } from "@/lib/api/errors";

export type SelfServiceBoundaryEntity = "reservation" | "deposit" | "bill" | "preorder" | "benefits";

export type SelfServiceAccessState =
  | {
      kind: "owner_only";
      title: string;
      description: string;
    }
  | {
      kind: "session_linked";
      title: string;
      description: string;
    };

export type SelfServiceBlockedState =
  | {
      kind: "unavailable";
      title: string;
      description: string;
    }
  | {
      kind: "forbidden";
      title: string;
      description: string;
    }
  | {
      kind: "error";
      title: string;
      error: unknown;
    };

export function getSelfServiceAccessState(accessScope?: string | null): SelfServiceAccessState | null {
  if (accessScope === "owner") {
    return {
      kind: "owner_only",
      title: "Truy cập theo tài khoản",
      description: "Bạn đang xem lịch đặt bằng tài khoản khách hàng đã đăng nhập.",
    };
  }

  if (accessScope === "session") {
    return {
      kind: "session_linked",
      title: "Phiên ghé nhà hàng đã liên kết",
      description: "Bạn đang xem lịch đặt qua phiên ghé nhà hàng trên trình duyệt này.",
    };
  }

  return null;
}

export function getSelfServiceBlockedState(
  entity: SelfServiceBoundaryEntity,
  error: unknown,
  fallbackTitle: string,
): SelfServiceBlockedState {
  const normalized = normalizeApiError(error);

  if (normalized.kind === "not_found") {
    return {
      kind: "unavailable",
      title: getUnavailableTitle(entity),
      description: getUnavailableDescription(entity),
    };
  }

  if (normalized.kind === "forbidden") {
    return {
      kind: "forbidden",
      title: getForbiddenTitle(entity),
      description: getForbiddenDescription(entity),
    };
  }

  return {
    kind: "error",
    title: fallbackTitle,
    error,
  };
}

function getUnavailableTitle(entity: SelfServiceBoundaryEntity): string {
  switch (entity) {
    case "reservation":
      return "Lịch đặt không khả dụng";
    case "deposit":
      return "Đặt cọc không khả dụng";
    case "bill":
      return "Hóa đơn không khả dụng";
    case "preorder":
      return "Món đặt trước không khả dụng";
    case "benefits":
      return "Ưu đãi không khả dụng";
  }
}

function getUnavailableDescription(entity: SelfServiceBoundaryEntity): string {
  switch (entity) {
    case "reservation":
      return "Không tìm thấy lịch đặt hoặc lịch đặt không còn mở cho khách tự thao tác.";
    case "deposit":
      return "Hiện chưa có thông tin đặt cọc cho lịch đặt này.";
    case "bill":
      return "Hiện chưa có thông tin hóa đơn cho lịch đặt này.";
    case "preorder":
      return "Hiện chưa có thông tin món đặt trước cho lịch đặt này.";
    case "benefits":
      return "Hiện chưa có thông tin ưu đãi cho lịch đặt này.";
  }
}

function getForbiddenTitle(entity: SelfServiceBoundaryEntity): string {
  switch (entity) {
    case "reservation":
      return "Không thể mở lịch đặt";
    case "deposit":
      return "Không thể mở đặt cọc";
    case "bill":
      return "Không thể mở hóa đơn";
    case "preorder":
      return "Không thể mở món đặt trước";
    case "benefits":
      return "Không thể mở ưu đãi";
  }
}

function getForbiddenDescription(entity: SelfServiceBoundaryEntity): string {
  switch (entity) {
    case "reservation":
      return "Tài khoản hoặc phiên hiện tại không có quyền mở lịch đặt này.";
    case "deposit":
      return "Tài khoản hoặc phiên hiện tại không thể tự xử lý đặt cọc cho lịch đặt này.";
    case "bill":
      return "Tài khoản hoặc phiên hiện tại không thể tự xử lý hóa đơn cho lịch đặt này.";
    case "preorder":
      return "Tài khoản hoặc phiên hiện tại không thể mở món đặt trước cho lịch đặt này.";
    case "benefits":
      return "Tài khoản hoặc phiên hiện tại không thể mở ưu đãi cho lịch đặt này.";
  }
}
