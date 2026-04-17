import { z } from "zod";

export const reservationFormSchema = z.object({
  guest_name: z.string().min(1, "Enter the guest name."),
  guest_phone: z.string().min(6, "Enter a reachable phone number."),
  guest_email: z.string().email("Enter a valid email.").optional().or(z.literal("")),
  start_time: z.string().min(1, "Choose a visit time."),
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
  const start = new Date(values.start_time);
  const end = new Date(start.getTime() + values.duration_minutes * 60_000);

  return {
    start_time: start.toISOString(),
    end_time: end.toISOString(),
  };
}
