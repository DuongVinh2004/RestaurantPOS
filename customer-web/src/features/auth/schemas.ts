import { z } from "zod";

export const loginSchema = z.object({
  identifier: z.string().min(1, "Enter your email, phone, or customer id."),
  password: z.string().min(1, "Enter your password."),
});

export type LoginFormValues = z.infer<typeof loginSchema>;
