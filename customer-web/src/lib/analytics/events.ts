export type CustomerAnalyticsEventName =
  | "homepage_cta_clicked"
  | "branch_selected"
  | "menu_searched"
  | "dish_viewed"
  | "dish_added_to_cart"
  | "reservation_started"
  | "reservation_confirmed"
  | "waiting_list_joined"
  | "voucher_applied"
  | "payment_attempted"
  | "feedback_submitted";

export type CustomerAnalyticsPayload = {
  route?: string;
  branch_id?: number | null;
  reservation_id?: number | null;
  item_id?: number | null;
  surface?: string;
  source?: string;
  [key: string]: string | number | boolean | null | undefined;
};

export function trackCustomerEvent(_eventName: CustomerAnalyticsEventName, _payload: CustomerAnalyticsPayload = {}): void {
  void _eventName;
  void _payload;
  // No-op until customer-web is connected to the product analytics sink.
}
