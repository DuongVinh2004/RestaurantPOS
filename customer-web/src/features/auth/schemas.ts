import { z } from "zod";

export const loginSchema = z.object({
  identifier: z.string().min(1, "Nhập email, số điện thoại hoặc mã khách hàng."),
  password: z.string().min(1, "Nhập mật khẩu."),
});

export type LoginFormValues = z.infer<typeof loginSchema>;
