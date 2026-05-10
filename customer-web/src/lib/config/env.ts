import { z } from "zod";

const truthyValues = new Set(["1", "true", "yes", "on"]);
const localHostnames = new Set(["127.0.0.1", "localhost", "0.0.0.0", "::1"]);

function readBool(value: string | undefined, fallback: boolean): boolean {
  if (value === undefined || value === "") {
    return fallback;
  }

  return truthyValues.has(value.toLowerCase());
}

type StringSetting = {
  value: string;
  sourceKey: string | "default";
};

type BoolSetting = {
  value: boolean;
  sourceKey: string | "default";
  usedAlias: boolean;
};

function readStringSetting(source: Record<string, string | undefined>, keys: string[], fallback: string): StringSetting {
  for (const key of keys) {
    const value = source[key];

    if (value !== undefined && value !== "") {
      return {
        value,
        sourceKey: key,
      };
    }
  }

  return {
    value: fallback,
    sourceKey: "default",
  };
}

function readBoolSetting(source: Record<string, string | undefined>, keys: string[], fallback: boolean): BoolSetting {
  for (const [index, key] of keys.entries()) {
    const value = source[key];

    if (value !== undefined && value !== "") {
      return {
        value: readBool(value, fallback),
        sourceKey: key,
        usedAlias: index > 0,
      };
    }
  }

  return {
    value: fallback,
    sourceKey: "default",
    usedAlias: false,
  };
}

function parseHostname(urlString: string): string | null {
  try {
    return new URL(urlString).hostname ?? null;
  } catch {
    return null;
  }
}

function isLocalHostname(hostname: string | null | undefined): boolean {
  if (!hostname) {
    return false;
  }

  return localHostnames.has(hostname.toLowerCase());
}

const envSchema = z.object({
  apiBaseUrl: z.string().min(1),
  enableDevMocks: z.boolean(),
  showDevBackendStatus: z.boolean(),
  enablePreorder: z.boolean(),
  enableMenuCategories: z.boolean(),
  enableMenuItemDetail: z.boolean(),
  enableTableAvailability: z.boolean(),
  enableTableHolds: z.boolean(),
  enableWaitingList: z.boolean(),
  enableAccountBenefits: z.boolean(),
  enablePrivacyTools: z.boolean(),
  enableDataExport: z.boolean(),
});

export type PublicEnv = z.infer<typeof envSchema>;

export type PublicEnvFlagDiagnostic = {
  value: boolean;
  sourceKey: string | "default";
  usedAlias: boolean;
  preferredKey: string;
  aliasKeys: string[];
};

export type PublicEnvDiagnostics = {
  apiBaseUrl: string;
  apiBaseUrlSource: string | "default";
  apiBaseUrlHost: string | null;
  apiBaseUrlLooksLocal: boolean;
  apiBaseUrlUsesDefaultLocal: boolean;
  rolloutFlagsUsingAliases: string[];
  waitingList: PublicEnvFlagDiagnostic;
  accountBenefits: PublicEnvFlagDiagnostic;
  privacyTools: PublicEnvFlagDiagnostic;
  dataExport: PublicEnvFlagDiagnostic;
  preorder: PublicEnvFlagDiagnostic;
};

export type ApiBaseUrlRuntimeDiagnostics = {
  apiHost: string | null;
  appHost: string | null;
  apiLooksLocal: boolean;
  appLooksLocal: boolean;
  likelyWrongForCurrentHost: boolean;
};

type EnvSource = Record<string, string | undefined>;

function currentRuntimeEnvSource(): EnvSource {
  return {
    NODE_ENV: process.env.NODE_ENV,
    NEXT_PUBLIC_API_BASE_URL: process.env.NEXT_PUBLIC_API_BASE_URL,
    NEXT_PUBLIC_ENABLE_DEV_MOCKS: process.env.NEXT_PUBLIC_ENABLE_DEV_MOCKS,
    NEXT_PUBLIC_SHOW_DEV_BACKEND_STATUS:
      process.env.NEXT_PUBLIC_SHOW_DEV_BACKEND_STATUS,
    NEXT_PUBLIC_FEATURE_PREORDER: process.env.NEXT_PUBLIC_FEATURE_PREORDER,
    NEXT_PUBLIC_FEATURE_MENU_CATEGORIES:
      process.env.NEXT_PUBLIC_FEATURE_MENU_CATEGORIES,
    NEXT_PUBLIC_FEATURE_MENU_ITEM_DETAIL:
      process.env.NEXT_PUBLIC_FEATURE_MENU_ITEM_DETAIL,
    NEXT_PUBLIC_FEATURE_TABLE_AVAILABILITY:
      process.env.NEXT_PUBLIC_FEATURE_TABLE_AVAILABILITY,
    NEXT_PUBLIC_FEATURE_TABLE_HOLDS: process.env.NEXT_PUBLIC_FEATURE_TABLE_HOLDS,
    NEXT_PUBLIC_FEATURE_WAITING_LIST: process.env.NEXT_PUBLIC_FEATURE_WAITING_LIST,
    NEXT_PUBLIC_ENABLE_WAITING_LIST: process.env.NEXT_PUBLIC_ENABLE_WAITING_LIST,
    NEXT_PUBLIC_FEATURE_ACCOUNT_BENEFITS:
      process.env.NEXT_PUBLIC_FEATURE_ACCOUNT_BENEFITS,
    NEXT_PUBLIC_FEATURE_VOUCHERS: process.env.NEXT_PUBLIC_FEATURE_VOUCHERS,
    NEXT_PUBLIC_FEATURE_PRIVACY_TOOLS:
      process.env.NEXT_PUBLIC_FEATURE_PRIVACY_TOOLS,
    NEXT_PUBLIC_ENABLE_PRIVACY_TOOLS:
      process.env.NEXT_PUBLIC_ENABLE_PRIVACY_TOOLS,
    NEXT_PUBLIC_FEATURE_PRIVACY_REQUESTS:
      process.env.NEXT_PUBLIC_FEATURE_PRIVACY_REQUESTS,
    NEXT_PUBLIC_FEATURE_DATA_EXPORT: process.env.NEXT_PUBLIC_FEATURE_DATA_EXPORT,
  };
}

export function readPublicEnv(source: EnvSource = currentRuntimeEnvSource()): PublicEnv {
  const isProduction = (source.NODE_ENV ?? process.env.NODE_ENV) === "production";
  const apiBaseUrl = readStringSetting(source, ["NEXT_PUBLIC_API_BASE_URL"], "http://127.0.0.1:8000");
  const enableDevMocks = readBoolSetting(source, ["NEXT_PUBLIC_ENABLE_DEV_MOCKS"], false);
  const showDevBackendStatus = readBoolSetting(source, ["NEXT_PUBLIC_SHOW_DEV_BACKEND_STATUS"], true);
  const enablePreorder = readBoolSetting(source, ["NEXT_PUBLIC_FEATURE_PREORDER"], !isProduction);
  const enableMenuCategories = readBoolSetting(source, ["NEXT_PUBLIC_FEATURE_MENU_CATEGORIES"], true);
  const enableMenuItemDetail = readBoolSetting(source, ["NEXT_PUBLIC_FEATURE_MENU_ITEM_DETAIL"], true);
  const enableTableAvailability = readBoolSetting(source, ["NEXT_PUBLIC_FEATURE_TABLE_AVAILABILITY"], true);
  const enableTableHolds = readBoolSetting(source, ["NEXT_PUBLIC_FEATURE_TABLE_HOLDS"], true);
  const enableWaitingList = readBoolSetting(
    source,
    ["NEXT_PUBLIC_FEATURE_WAITING_LIST", "NEXT_PUBLIC_ENABLE_WAITING_LIST"],
    false,
  );
  const enableAccountBenefits = readBoolSetting(
    source,
    ["NEXT_PUBLIC_FEATURE_ACCOUNT_BENEFITS", "NEXT_PUBLIC_FEATURE_VOUCHERS"],
    false,
  );
  const enablePrivacyTools = readBoolSetting(
    source,
    ["NEXT_PUBLIC_FEATURE_PRIVACY_TOOLS", "NEXT_PUBLIC_ENABLE_PRIVACY_TOOLS", "NEXT_PUBLIC_FEATURE_PRIVACY_REQUESTS"],
    false,
  );
  const enableDataExport = readBoolSetting(source, ["NEXT_PUBLIC_FEATURE_DATA_EXPORT"], false);

  return envSchema.parse({
    apiBaseUrl: apiBaseUrl.value,
    enableDevMocks: !isProduction && enableDevMocks.value,
    showDevBackendStatus: !isProduction && showDevBackendStatus.value,
    enablePreorder: enablePreorder.value,
    enableMenuCategories: enableMenuCategories.value,
    enableMenuItemDetail: enableMenuItemDetail.value,
    enableTableAvailability: enableTableAvailability.value,
    enableTableHolds: enableTableHolds.value,
    enableWaitingList: enableWaitingList.value,
    enableAccountBenefits: enableAccountBenefits.value,
    enablePrivacyTools: enablePrivacyTools.value,
    enableDataExport: enableDataExport.value,
  });
}

export function readPublicEnvDiagnostics(
  source: EnvSource = currentRuntimeEnvSource(),
): PublicEnvDiagnostics {
  const isProduction = (source.NODE_ENV ?? process.env.NODE_ENV) === "production";
  const apiBaseUrl = readStringSetting(source, ["NEXT_PUBLIC_API_BASE_URL"], "http://127.0.0.1:8000");
  const waitingList = buildFlagDiagnostic(
    source,
    "NEXT_PUBLIC_FEATURE_WAITING_LIST",
    ["NEXT_PUBLIC_ENABLE_WAITING_LIST"],
    false,
  );
  const accountBenefits = buildFlagDiagnostic(
    source,
    "NEXT_PUBLIC_FEATURE_ACCOUNT_BENEFITS",
    ["NEXT_PUBLIC_FEATURE_VOUCHERS"],
    false,
  );
  const privacyTools = buildFlagDiagnostic(
    source,
    "NEXT_PUBLIC_FEATURE_PRIVACY_TOOLS",
    ["NEXT_PUBLIC_ENABLE_PRIVACY_TOOLS", "NEXT_PUBLIC_FEATURE_PRIVACY_REQUESTS"],
    false,
  );
  const dataExport = buildFlagDiagnostic(source, "NEXT_PUBLIC_FEATURE_DATA_EXPORT", [], false);
  const preorder = buildFlagDiagnostic(source, "NEXT_PUBLIC_FEATURE_PREORDER", [], !isProduction);
  const rolloutFlagsUsingAliases = [preorder, waitingList, accountBenefits, privacyTools, dataExport]
    .filter((diagnostic) => diagnostic.usedAlias && diagnostic.sourceKey !== "default")
    .map((diagnostic) => diagnostic.sourceKey);

  return {
    apiBaseUrl: apiBaseUrl.value,
    apiBaseUrlSource: apiBaseUrl.sourceKey,
    apiBaseUrlHost: parseHostname(apiBaseUrl.value),
    apiBaseUrlLooksLocal: isLocalHostname(parseHostname(apiBaseUrl.value)),
    apiBaseUrlUsesDefaultLocal: apiBaseUrl.sourceKey === "default",
    rolloutFlagsUsingAliases,
    waitingList,
    accountBenefits,
    privacyTools,
    dataExport,
    preorder,
  };
}

export function getApiBaseUrlRuntimeDiagnostics(
  apiBaseUrl: string,
  appHostname: string | null | undefined,
): ApiBaseUrlRuntimeDiagnostics {
  const apiHost = parseHostname(apiBaseUrl);
  const appHost = appHostname ?? null;
  const apiLooksLocal = isLocalHostname(apiHost);
  const appLooksLocal = isLocalHostname(appHost);

  return {
    apiHost,
    appHost,
    apiLooksLocal,
    appLooksLocal,
    likelyWrongForCurrentHost: apiLooksLocal && Boolean(appHost) && !appLooksLocal,
  };
}

export const publicEnv = readPublicEnv(currentRuntimeEnvSource());
export const publicEnvDiagnostics = readPublicEnvDiagnostics(currentRuntimeEnvSource());

function buildFlagDiagnostic(
  source: EnvSource,
  preferredKey: string,
  aliasKeys: string[],
  fallback: boolean,
): PublicEnvFlagDiagnostic {
  const setting = readBoolSetting(source, [preferredKey, ...aliasKeys], fallback);

  return {
    value: setting.value,
    sourceKey: setting.sourceKey,
    usedAlias: setting.usedAlias,
    preferredKey,
    aliasKeys,
  };
}
