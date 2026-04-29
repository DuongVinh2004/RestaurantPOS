import { z } from "zod";

export const waitingListCreateSchema = z.object({
  guest_count: z.number().min(1).max(20),
  guest_name: z.string().min(1, "Nhập tên khách."),
  phone: z.string().min(6, "Nhập số điện thoại có thể liên hệ."),
  notes: z.string().max(500).optional(),
});

export type WaitingListCreateValues = z.infer<typeof waitingListCreateSchema>;
