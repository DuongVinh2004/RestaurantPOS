import { z } from "zod";

export const waitingListCreateSchema = z.object({
  guest_count: z.number().min(1).max(20),
  guest_name: z.string().min(1, "Enter the guest name."),
  phone: z.string().min(6, "Enter a reachable phone number."),
  notes: z.string().max(500).optional(),
});

export type WaitingListCreateValues = z.infer<typeof waitingListCreateSchema>;
