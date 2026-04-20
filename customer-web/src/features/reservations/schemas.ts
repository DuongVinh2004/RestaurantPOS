import { z } from "zod";
import { localDateTimeRangeToUtc, parseLocalDateTimeInput } from "@/lib/contracts/datetime";

export const reservationFormSchema = z.object({
  guest_name: z.string().min(1, "Enter the guest name."),
  guest_phone: z.string().min(6, "Enter a reachable phone number."),
  guest_email: z.string().email("Enter a valid email.").optional().or(z.literal("")),
  start_time: z
    .string()
    .min(1, "Choose a visit time.")
    .refine((value) => parseLocalDateTimeInput(value) !== null, "Choose a valid local date and time."),
  duration_minutes: z.number().min(30).max(240),
  guest_count: z.number().min(1).max(20),
  notes: z.string().max(500).optional(),
});

export const reservationActionSchema = z.object({
  row_version: z.number().min(1),
  reason: z.string().max(300).optional(),
  start_time: z.string().optional(),
  guest_count: z.number().min(1).max(20).optional(),
});

export type ReservationFormValues = z.infer<typeof reservationFormSchema>;
export type ReservationActionValues = z.infer<typeof reservationActionSchema>;

export function reservationTimes(values: ReservationFormValues) {
  return localDateTimeRangeToUtc(values.start_time, values.duration_minutes);
}
