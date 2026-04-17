/* eslint-disable */
// Generated from app/Enums. Do not edit by hand.

export const conversationChannelValues = ["WebChat","Facebook","Zalo","Whatsapp","Instagram","Line","Other"] as const;
export type ConversationChannel = typeof conversationChannelValues[number];

export const conversationStatusValues = ["Open","Pending","Closed","Spam"] as const;
export type ConversationStatus = typeof conversationStatusValues[number];

export const depositStatusValues = ["NotRequired","Pending","Paid","Refunded","PartiallyRefunded","Forfeited"] as const;
export type DepositStatus = typeof depositStatusValues[number];

export const kitchenStationOutputModeValues = ["KDS","Printer","Both"] as const;
export type KitchenStationOutputMode = typeof kitchenStationOutputModeValues[number];

export const kitchenTicketStatusValues = ["Queued","Fired","Ready","Completed","Cancelled"] as const;
export type KitchenTicketStatus = typeof kitchenTicketStatusValues[number];

export const messageSenderValues = ["user","agent","system"] as const;
export type MessageSender = typeof messageSenderValues[number];

export const messageTypeValues = ["text","image","file","location","unknown"] as const;
export type MessageType = typeof messageTypeValues[number];

export const paymentProviderWebhookReceiptStatusValues = ["Received","Applied","Ignored","Failed"] as const;
export type PaymentProviderWebhookReceiptStatus = typeof paymentProviderWebhookReceiptStatusValues[number];

export const paymentSessionScopeValues = ["deposit","bill"] as const;
export type PaymentSessionScope = typeof paymentSessionScopeValues[number];

export const paymentStatusValues = ["Pending","Partial","Success","Failed","Refunded"] as const;
export type PaymentStatus = typeof paymentStatusValues[number];

export const purchaseOrderStatusValues = ["Draft","Ordered","PartiallyReceived","Received","Cancelled"] as const;
export type PurchaseOrderStatus = typeof purchaseOrderStatusValues[number];

export const purchaseReceiptStatusValues = ["Posted","Voided"] as const;
export type PurchaseReceiptStatus = typeof purchaseReceiptStatusValues[number];

export const reservationBillPaymentSessionStatusValues = ["Created","Pending","Succeeded","Failed","Cancelled","Expired"] as const;
export type ReservationBillPaymentSessionStatus = typeof reservationBillPaymentSessionStatusValues[number];

export const reservationBillPaymentSettlementStatusValues = ["NotApplied","Applied","Skipped"] as const;
export type ReservationBillPaymentSettlementStatus = typeof reservationBillPaymentSettlementStatusValues[number];

export const reservationDepositIntentStatusValues = ["None","Submitted","Revoked"] as const;
export type ReservationDepositIntentStatus = typeof reservationDepositIntentStatusValues[number];

export const reservationDepositPaymentSessionStatusValues = ["Created","Pending","Succeeded","Failed","Cancelled","Expired"] as const;
export type ReservationDepositPaymentSessionStatus = typeof reservationDepositPaymentSessionStatusValues[number];

export const reservationDepositPaymentSettlementStatusValues = ["NotApplied","Applied","Skipped"] as const;
export type ReservationDepositPaymentSettlementStatus = typeof reservationDepositPaymentSettlementStatusValues[number];

export const reservationOrderItemStatusValues = ["Ordered","InProgress","Served","Cancelled"] as const;
export type ReservationOrderItemStatus = typeof reservationOrderItemStatusValues[number];

export const reservationOrderStatusValues = ["Active","Cancelled","Completed"] as const;
export type ReservationOrderStatus = typeof reservationOrderStatusValues[number];

export const reservationOrderTypeValues = ["PreOrder","OnSpot"] as const;
export type ReservationOrderType = typeof reservationOrderTypeValues[number];

// Historical DB/API value `Reserved` means the guest is already checked in and occupying table(s).
export const reservationStatusValues = ["Confirmed","Reserved","Cancelled","Expired","Completed","NoShow"] as const;
export type ReservationStatus = typeof reservationStatusValues[number];

export const restaurantTableStatusValues = ["Available","Reserved","Occupied","Blocked","Maintenance"] as const;
export type RestaurantTableStatus = typeof restaurantTableStatusValues[number];

export const staffConversationWorkflowStateValues = ["Open","Triaged","Assigned","PendingCustomer","Resolved","Closed"] as const;
export type StaffConversationWorkflowState = typeof staffConversationWorkflowStateValues[number];

export const tableHoldStatusValues = ["Holding","Pending","Confirmed","Expired","Cancelled"] as const;
export type TableHoldStatus = typeof tableHoldStatusValues[number];

export const voucherDiscountTypeValues = ["Fixed","Percent","FreeItem"] as const;
export type VoucherDiscountType = typeof voucherDiscountTypeValues[number];

export const waitingListCustomerResponseStatusValues = ["Accepted","Declined"] as const;
export type WaitingListCustomerResponseStatus = typeof waitingListCustomerResponseStatusValues[number];

export const waitingListStatusValues = ["Waiting","Notified","Seated","Cancelled"] as const;
export type WaitingListStatus = typeof waitingListStatusValues[number];

export const restaurantPosEnumStateMap = {
  ConversationChannel: {
    values: conversationChannelValues,
    cases: {"WebChat":"WebChat","Facebook":"Facebook","Zalo":"Zalo","Whatsapp":"Whatsapp","Instagram":"Instagram","Line":"Line","Other":"Other"} as const
  },
  ConversationStatus: {
    values: conversationStatusValues,
    cases: {"Open":"Open","Pending":"Pending","Closed":"Closed","Spam":"Spam"} as const
  },
  DepositStatus: {
    values: depositStatusValues,
    cases: {"NotRequired":"NotRequired","Pending":"Pending","Paid":"Paid","Refunded":"Refunded","PartiallyRefunded":"PartiallyRefunded","Forfeited":"Forfeited"} as const
  },
  KitchenStationOutputMode: {
    values: kitchenStationOutputModeValues,
    cases: {"KDS":"KDS","Printer":"Printer","Both":"Both"} as const
  },
  KitchenTicketStatus: {
    values: kitchenTicketStatusValues,
    cases: {"Queued":"Queued","Fired":"Fired","Ready":"Ready","Completed":"Completed","Cancelled":"Cancelled"} as const
  },
  MessageSender: {
    values: messageSenderValues,
    cases: {"User":"user","Agent":"agent","System":"system"} as const
  },
  MessageType: {
    values: messageTypeValues,
    cases: {"Text":"text","Image":"image","File":"file","Location":"location","Unknown":"unknown"} as const
  },
  PaymentProviderWebhookReceiptStatus: {
    values: paymentProviderWebhookReceiptStatusValues,
    cases: {"Received":"Received","Applied":"Applied","Ignored":"Ignored","Failed":"Failed"} as const
  },
  PaymentSessionScope: {
    values: paymentSessionScopeValues,
    cases: {"Deposit":"deposit","Bill":"bill"} as const
  },
  PaymentStatus: {
    values: paymentStatusValues,
    cases: {"Pending":"Pending","Partial":"Partial","Success":"Success","Failed":"Failed","Refunded":"Refunded"} as const
  },
  PurchaseOrderStatus: {
    values: purchaseOrderStatusValues,
    cases: {"Draft":"Draft","Ordered":"Ordered","PartiallyReceived":"PartiallyReceived","Received":"Received","Cancelled":"Cancelled"} as const
  },
  PurchaseReceiptStatus: {
    values: purchaseReceiptStatusValues,
    cases: {"Posted":"Posted","Voided":"Voided"} as const
  },
  ReservationBillPaymentSessionStatus: {
    values: reservationBillPaymentSessionStatusValues,
    cases: {"Created":"Created","Pending":"Pending","Succeeded":"Succeeded","Failed":"Failed","Cancelled":"Cancelled","Expired":"Expired"} as const
  },
  ReservationBillPaymentSettlementStatus: {
    values: reservationBillPaymentSettlementStatusValues,
    cases: {"NotApplied":"NotApplied","Applied":"Applied","Skipped":"Skipped"} as const
  },
  ReservationDepositIntentStatus: {
    values: reservationDepositIntentStatusValues,
    cases: {"None":"None","Submitted":"Submitted","Revoked":"Revoked"} as const
  },
  ReservationDepositPaymentSessionStatus: {
    values: reservationDepositPaymentSessionStatusValues,
    cases: {"Created":"Created","Pending":"Pending","Succeeded":"Succeeded","Failed":"Failed","Cancelled":"Cancelled","Expired":"Expired"} as const
  },
  ReservationDepositPaymentSettlementStatus: {
    values: reservationDepositPaymentSettlementStatusValues,
    cases: {"NotApplied":"NotApplied","Applied":"Applied","Skipped":"Skipped"} as const
  },
  ReservationOrderItemStatus: {
    values: reservationOrderItemStatusValues,
    cases: {"Ordered":"Ordered","InProgress":"InProgress","Served":"Served","Cancelled":"Cancelled"} as const
  },
  ReservationOrderStatus: {
    values: reservationOrderStatusValues,
    cases: {"Active":"Active","Cancelled":"Cancelled","Completed":"Completed"} as const
  },
  ReservationOrderType: {
    values: reservationOrderTypeValues,
    cases: {"PreOrder":"PreOrder","OnSpot":"OnSpot"} as const
  },
  ReservationStatus: {
    values: reservationStatusValues,
    cases: {"Confirmed":"Confirmed","Reserved":"Reserved","Cancelled":"Cancelled","Expired":"Expired","Completed":"Completed","NoShow":"NoShow"} as const,
    semanticAliases: {"checked_in":"Reserved"} as const,
    stateHints: {"checked_in_db_value":"Reserved","active_db_values":["Confirmed","Reserved"],"cancellable_db_values":["Confirmed","Reserved"]} as const,
    notes: ["Historical DB/API value `Reserved` means the guest is already checked in and occupying table(s)."] as const
  },
  RestaurantTableStatus: {
    values: restaurantTableStatusValues,
    cases: {"Available":"Available","Reserved":"Reserved","Occupied":"Occupied","Blocked":"Blocked","Maintenance":"Maintenance"} as const
  },
  StaffConversationWorkflowState: {
    values: staffConversationWorkflowStateValues,
    cases: {"Open":"Open","Triaged":"Triaged","Assigned":"Assigned","PendingCustomer":"PendingCustomer","Resolved":"Resolved","Closed":"Closed"} as const
  },
  TableHoldStatus: {
    values: tableHoldStatusValues,
    cases: {"Holding":"Holding","Pending":"Pending","Confirmed":"Confirmed","Expired":"Expired","Cancelled":"Cancelled"} as const
  },
  VoucherDiscountType: {
    values: voucherDiscountTypeValues,
    cases: {"Fixed":"Fixed","Percent":"Percent","FreeItem":"FreeItem"} as const
  },
  WaitingListCustomerResponseStatus: {
    values: waitingListCustomerResponseStatusValues,
    cases: {"Accepted":"Accepted","Declined":"Declined"} as const
  },
  WaitingListStatus: {
    values: waitingListStatusValues,
    cases: {"Waiting":"Waiting","Notified":"Notified","Seated":"Seated","Cancelled":"Cancelled"} as const
  }
} as const;
