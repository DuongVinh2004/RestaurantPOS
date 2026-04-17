import { z } from "zod";

const truthyValues = new Set(["1", "true", "yes", "on"]);

function readBool(value: string | undefined, fallback: boolean): boolean {
  if (value === undefined || value === "") {
    return fallback;
  }

  return truthyValues.has(value.toLowerCase());
}

const envSchema = z.object({
  apiBaseUrl: z.string().min(1),
  enableDevMocks: z.boolean(),
  showDevBackendStatus: z.boolean(),
  enableMenuCategories: z.boolean(),
  enableMenuItemDetail: z.boolean(),
  enableTableAvailability: z.boolean(),
  enableTableHolds: z.boolean(),
  enableWaitingList: z.boolean(),
  enableVouchers: z.boolean(),
  enablePrivacyTools: z.boolean(),
  enableDataExport: z.boolean(),
});

export type PublicEnv = z.infer<typeof envSchema>;

type EnvSource = Record<string, string | undefined>;

export function readPublicEnv(source: EnvSource = process.env): PublicEnv {
  const isProduction = process.env.NODE_ENV === "production";

  return envSchema.parse({
    apiBaseUrl: source.NEXT_PUBLIC_API_BASE_URL ?? "http://127.0.0.1:8000",
    enableDevMocks: !isProduction && readBool(source.NEXT_PUBLIC_ENABLE_DEV_MOCKS, false),
    showDevBackendStatus: !isProduction && readBool(source.NEXT_PUBLIC_SHOW_DEV_BACKEND_STATUS, true),
    enableMenuCategories: readBool(source.NEXT_PUBLIC_FEATURE_MENU_CATEGORIES, true),
    enableMenuItemDetail: readBool(source.NEXT_PUBLIC_FEATURE_MENU_ITEM_DETAIL, true),
    enableTableAvailability: readBool(source.NEXT_PUBLIC_FEATURE_TABLE_AVAILABILITY, true),
    enableTableHolds: readBool(source.NEXT_PUBLIC_FEATURE_TABLE_HOLDS, true),
    enableWaitingList: readBool(
      source.NEXT_PUBLIC_ENABLE_WAITING_LIST ?? source.NEXT_PUBLIC_FEATURE_WAITING_LIST,
      true,
    ),
    enableVouchers: readBool(source.NEXT_PUBLIC_FEATURE_VOUCHERS, true),
    enablePrivacyTools: readBool(
      source.NEXT_PUBLIC_ENABLE_PRIVACY_TOOLS ?? source.NEXT_PUBLIC_FEATURE_PRIVACY_REQUESTS,
      true,
    ),
    enableDataExport: readBool(source.NEXT_PUBLIC_FEATURE_DATA_EXPORT, true),
  });
}

export const publicEnv = readPublicEnv();
