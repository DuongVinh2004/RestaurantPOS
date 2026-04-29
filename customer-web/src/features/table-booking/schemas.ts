import { z } from "zod";
import { localDateTimeRangeToUtc, parseLocalDateTimeInput } from "@/lib/contracts/datetime";

export const availabilitySearchSchema = z.object({
  start_time: z
    .string()
    .min(1, "Chọn ngày giờ đến nhà hàng.")
    .refine((value) => parseLocalDateTimeInput(value) !== null, "Chọn ngày giờ hợp lệ."),
  duration_minutes: z.number().min(30).max(240),
  guest_count: z.number().min(1).max(20),
  branch_id: z.number().optional(),
});

export type AvailabilitySearchValues = z.infer<typeof availabilitySearchSchema>;

export function availabilityTimes(values: AvailabilitySearchValues) {
  return localDateTimeRangeToUtc(values.start_time, values.duration_minutes);
}
