import { apiCall, idempotentOptions } from "@/lib/api/sdk-client";
import type {
  CustomerReservationBenefitsPreviewEnvelope,
  CustomerReservationLoyaltyActionEnvelope,
  CustomerReservationVoucherActionEnvelope,
  CustomerVoucherCollectionEnvelope,
} from "@/lib/contracts/generated/restaurantpos-sdk";

export function listVouchers(): Promise<CustomerVoucherCollectionEnvelope> {
  return apiCall((client) => client.getV1MeVouchers({ bucket: "active" }));
}

export function getBenefitsPreview(reservationId: number): Promise<CustomerReservationBenefitsPreviewEnvelope> {
  return apiCall((client) => client.getV1ReservationsIdBenefitsPreview({ id: reservationId }));
}

export function applyVoucher(
  reservationId: number,
  rowVersion: number,
  voucherCode: string,
): Promise<CustomerReservationVoucherActionEnvelope> {
  return apiCall((client) =>
    client.postV1ReservationsIdVoucherApply(
      { id: reservationId },
      { row_version: rowVersion, voucher_code: voucherCode },
      idempotentOptions("voucher-apply"),
    ),
  );
}

export function removeVoucher(reservationId: number, rowVersion: number): Promise<CustomerReservationVoucherActionEnvelope> {
  return apiCall((client) =>
    client.postV1ReservationsIdVoucherRemove({ id: reservationId }, { row_version: rowVersion }, idempotentOptions("voucher-remove")),
  );
}

export function redeemLoyaltyPoints(
  reservationId: number,
  rowVersion: number,
  points: number,
): Promise<CustomerReservationLoyaltyActionEnvelope> {
  return apiCall((client) =>
    client.postV1ReservationsIdLoyaltyRedeem(
      { id: reservationId },
      { row_version: rowVersion, points },
      idempotentOptions("loyalty-redeem"),
    ),
  );
}

export function releaseLoyaltyPoints(reservationId: number, rowVersion: number): Promise<CustomerReservationLoyaltyActionEnvelope> {
  return apiCall((client) =>
    client.postV1ReservationsIdLoyaltyRedeemRelease(
      { id: reservationId },
      { row_version: rowVersion },
      idempotentOptions("loyalty-release"),
    ),
  );
}
