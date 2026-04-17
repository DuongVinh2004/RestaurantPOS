import { z } from "zod";

export const availabilitySearchSchema = z.object({
  start_time: z.string().min(1, "Choose a visit time."),
  duration_minutes: z.number().min(30).max(240),
  guest_count: z.number().min(1).max(20),
  branch_id: z.number().optional(),
});

export type AvailabilitySearchValues = z.infer<typeof availabilitySearchSchema>;

export function availabilityTimes(values: AvailabilitySearchValues) {
  const start = new Date(values.start_time);
  const end = new Date(start.getTime() + values.duration_minutes * 60_000);

  return {
    start_time: start.toISOString(),
    end_time: end.toISOString(),
  };
}
