import { unwrapData } from "@/lib/api/envelope";
import { apiCall, idempotentOptions } from "@/lib/api/sdk-client";
import type {
  CustomerReservationBenefitsPreviewEnvelope,
  CustomerReservationLoyaltyActionEnvelope,
  CustomerReservationVoucherActionEnvelope,
  CustomerVoucherCollectionEnvelope,
  GetV1MeVouchersQueryParams,
} from "@/lib/contracts/generated/restaurantpos-sdk";

export type VoucherWallet = CustomerVoucherCollectionEnvelope["data"];
export type ReservationBenefitsPreview = CustomerReservationBenefitsPreviewEnvelope["data"];
export type ReservationVoucherAction = CustomerReservationVoucherActionEnvelope["data"];
export type ReservationLoyaltyAction = CustomerReservationLoyaltyActionEnvelope["data"];

export function listVouchers(query: GetV1MeVouchersQueryParams = { bucket: "all", per_page: 24 }): Promise<VoucherWallet> {
  return apiCall((client) => client.getV1MeVouchers(query)).then(unwrapData);
}

export function getBenefitsPreview(reservationId: number): Promise<ReservationBenefitsPreview> {
  return apiCall((client) => client.getV1ReservationsIdBenefitsPreview({ id: reservationId })).then(unwrapData);
}

export function applyVoucher(
  reservationId: number,
  rowVersion: number,
  voucherCode: string,
): Promise<ReservationVoucherAction> {
  return apiCall((client) =>
    client.postV1ReservationsIdVoucherApply(
      { id: reservationId },
      { row_version: rowVersion, voucher_code: voucherCode },
      idempotentOptions("voucher-apply"),
    ),
  ).then(unwrapData);
}

export function removeVoucher(reservationId: number, rowVersion: number): Promise<ReservationVoucherAction> {
  return apiCall((client) =>
    client.postV1ReservationsIdVoucherRemove({ id: reservationId }, { row_version: rowVersion }, idempotentOptions("voucher-remove")),
  ).then(unwrapData);
}

export function redeemLoyaltyPoints(
  reservationId: number,
  rowVersion: number,
  points: number,
): Promise<ReservationLoyaltyAction> {
  return apiCall((client) =>
    client.postV1ReservationsIdLoyaltyRedeem(
      { id: reservationId },
      { row_version: rowVersion, points },
      idempotentOptions("loyalty-redeem"),
    ),
  ).then(unwrapData);
}

export function releaseLoyaltyPoints(reservationId: number, rowVersion: number): Promise<ReservationLoyaltyAction> {
  return apiCall((client) =>
    client.postV1ReservationsIdLoyaltyRedeemRelease(
      { id: reservationId },
      { row_version: rowVersion },
      idempotentOptions("loyalty-release"),
    ),
  ).then(unwrapData);
}
