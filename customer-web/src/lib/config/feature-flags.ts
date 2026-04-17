import { publicEnv, type PublicEnv } from "./env";

export type FeatureFlags = {
  devMocks: boolean;
  showDevBackendStatus: boolean;
  menuCategories: boolean;
  menuItemDetail: boolean;
  tableAvailability: boolean;
  tableHolds: boolean;
  waitingList: boolean;
  vouchers: boolean;
  privacyTools: boolean;
  dataExport: boolean;
};

export function getFeatureFlags(env: PublicEnv = publicEnv): FeatureFlags {
  return {
    devMocks: env.enableDevMocks,
    showDevBackendStatus: env.showDevBackendStatus,
    menuCategories: env.enableMenuCategories,
    menuItemDetail: env.enableMenuItemDetail,
    tableAvailability: env.enableTableAvailability,
    tableHolds: env.enableTableHolds,
    waitingList: env.enableWaitingList,
    vouchers: env.enableVouchers,
    privacyTools: env.enablePrivacyTools,
    dataExport: env.enableDataExport,
  };
}

export const featureFlags = getFeatureFlags();
