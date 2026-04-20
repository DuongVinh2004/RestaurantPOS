import { publicEnv, type PublicEnv } from "./env";
import { resolveSupportMatrixDecisions, type SupportMatrixEnv, type SurfaceRolloutDecision } from "./support-matrix";

export type FeatureFlags = {
  devMocks: boolean;
  showDevBackendStatus: boolean;
  preorder: boolean;
  menuCategories: boolean;
  menuItemDetail: boolean;
  tableAvailability: boolean;
  tableHolds: boolean;
  waitingList: boolean;
  accountBenefits: boolean;
  privacyTools: boolean;
  dataExport: boolean;
};

export type CustomerWebRollout = {
  authSession: SurfaceRolloutDecision;
  menuCatalog: SurfaceRolloutDecision;
  tableAvailabilityAndHolds: SurfaceRolloutDecision;
  reservations: SurfaceRolloutDecision;
  preorder: SurfaceRolloutDecision;
  depositSelfPay: SurfaceRolloutDecision;
  billAndActiveOrder: SurfaceRolloutDecision;
  waitingList: SurfaceRolloutDecision;
  accountBenefits: SurfaceRolloutDecision;
  privacyRequests: SurfaceRolloutDecision;
  dataExport: SurfaceRolloutDecision;
  devMockAdapter: SurfaceRolloutDecision;
};

export function getCustomerWebRollout(env: PublicEnv = publicEnv): CustomerWebRollout {
  const decisions = resolveSupportMatrixDecisions(getSupportMatrixEnv(env));

  return {
    authSession: decisions["auth-session"],
    menuCatalog: decisions["menu-catalog"],
    tableAvailabilityAndHolds: decisions["table-availability-and-holds"],
    reservations: decisions["reservations"],
    preorder: decisions["preorder"],
    depositSelfPay: decisions["deposit-self-pay"],
    billAndActiveOrder: decisions["bill-and-active-order"],
    waitingList: decisions["waiting-list"],
    accountBenefits: decisions["account-benefits"],
    privacyRequests: decisions["privacy-requests"],
    dataExport: decisions["data-export"],
    devMockAdapter: decisions["dev-mock-adapter"],
  };
}

export function getFeatureFlags(env: PublicEnv = publicEnv, rollout: CustomerWebRollout = getCustomerWebRollout(env)): FeatureFlags {
  return {
    devMocks: rollout.devMockAdapter.enabled,
    showDevBackendStatus: env.showDevBackendStatus,
    preorder: rollout.preorder.enabled,
    menuCategories: rollout.menuCatalog.enabled && env.enableMenuCategories,
    menuItemDetail: rollout.menuCatalog.enabled && env.enableMenuItemDetail,
    tableAvailability: rollout.tableAvailabilityAndHolds.enabled && env.enableTableAvailability,
    tableHolds: rollout.tableAvailabilityAndHolds.enabled && env.enableTableHolds,
    waitingList: rollout.waitingList.enabled,
    accountBenefits: rollout.accountBenefits.enabled,
    privacyTools: rollout.privacyRequests.enabled,
    dataExport: rollout.privacyRequests.enabled && rollout.dataExport.enabled,
  };
}

export const customerWebRollout = getCustomerWebRollout();
export const featureFlags = getFeatureFlags(publicEnv, customerWebRollout);

function getSupportMatrixEnv(env: PublicEnv): SupportMatrixEnv {
  return {
    enableDevMocks: env.enableDevMocks,
    enablePreorder: env.enablePreorder,
    enableMenuCategories: env.enableMenuCategories,
    enableMenuItemDetail: env.enableMenuItemDetail,
    enableTableAvailability: env.enableTableAvailability,
    enableTableHolds: env.enableTableHolds,
    enableWaitingList: env.enableWaitingList,
    enableAccountBenefits: env.enableAccountBenefits,
    enablePrivacyTools: env.enablePrivacyTools,
    enableDataExport: env.enableDataExport,
  };
}
