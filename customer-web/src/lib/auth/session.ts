import type { CustomerAuthSessionEnvelope } from "@/lib/contracts/generated/restaurantpos-sdk";

export type CustomerProfile = {
  userId: number | null;
  name: string;
  email: string | null;
  phone: string | null;
};

function stringFromRecord(record: Record<string, unknown>, keys: string[]): string | null {
  for (const key of keys) {
    const value = record[key];

    if (typeof value === "string" && value.trim() !== "") {
      return value;
    }
  }

  return null;
}

function numberFromRecord(record: Record<string, unknown>, keys: string[]): number | null {
  for (const key of keys) {
    const value = record[key];

    if (typeof value === "number") {
      return value;
    }
  }

  return null;
}

export function customerProfileFromSession(envelope: CustomerAuthSessionEnvelope | null): CustomerProfile | null {
  const user = envelope?.data.user;

  if (!user) {
    return null;
  }

  const record = user as Record<string, unknown>;
  const name =
    stringFromRecord(record, ["name", "full_name", "display_name", "username"]) ??
    "Customer";

  return {
    userId: numberFromRecord(record, ["user_id", "id"]),
    name,
    email: stringFromRecord(record, ["email", "email_address"]),
    phone: stringFromRecord(record, ["phone", "phone_number"]),
  };
}
