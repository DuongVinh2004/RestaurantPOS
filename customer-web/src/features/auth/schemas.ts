import { z } from "zod";

const optionalContact = z.string().trim().max(200).optional();
const emailValue = z.string().email();

export const loginSchema = z.object({
  identifier: z.string().min(1, "Nhập email, số điện thoại hoặc mã khách hàng."),
  password: z.string().min(1, "Nhập mật khẩu."),
});

export type LoginFormValues = z.infer<typeof loginSchema>;

export const registerSchema = z
  .object({
    full_name: z.string().trim().min(1, "Nhập họ tên.").max(200, "Họ tên quá dài."),
    email: optionalContact.refine((value) => !value || emailValue.safeParse(value).success, "Email chưa đúng định dạng."),
    phone: optionalContact.refine((value) => !value || value.length <= 30, "Số điện thoại quá dài."),
    password: z.string().min(8, "Mật khẩu cần ít nhất 8 ký tự.").max(255, "Mật khẩu quá dài."),
    password_confirmation: z.string().min(1, "Nhập lại mật khẩu."),
  })
  .superRefine((values, ctx) => {
    if (!values.email && !values.phone) {
      ctx.addIssue({
        code: "custom",
        path: ["email"],
        message: "Nhập email hoặc số điện thoại.",
      });
      ctx.addIssue({
        code: "custom",
        path: ["phone"],
        message: "Nhập email hoặc số điện thoại.",
      });
    }

    if (values.password !== values.password_confirmation) {
      ctx.addIssue({
        code: "custom",
        path: ["password_confirmation"],
        message: "Mật khẩu nhập lại chưa khớp.",
      });
    }
  });

export type RegisterFormValues = z.infer<typeof registerSchema>;
