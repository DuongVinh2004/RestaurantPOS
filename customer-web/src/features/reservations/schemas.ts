import { z } from "zod";
import { localDateTimeRangeToUtc, parseLocalDateTimeInput } from "@/lib/contracts/datetime";

export const reservationFormSchema = z.object({
  guest_name: z.string().min(1, "Nhập tên khách."),
  guest_phone: z.string().min(6, "Nhập số điện thoại có thể liên hệ."),
  guest_email: z.string().email("Nhập email hợp lệ.").optional().or(z.literal("")),
  start_time: z
    .string()
    .min(1, "Chọn ngày giờ đến nhà hàng.")
    .refine((value) => parseLocalDateTimeInput(value) !== null, "Chọn ngày giờ hợp lệ."),
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
