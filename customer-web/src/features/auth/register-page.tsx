"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { zodResolver } from "@hookform/resolvers/zod";
import { useMutation } from "@tanstack/react-query";
import { useRouter, useSearchParams } from "next/navigation";
import { useForm } from "react-hook-form";
import { toast } from "sonner";
import { CalendarCheck2, ReceiptText, ShieldCheck, type LucideIcon } from "lucide-react";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { AppButton, AppCard, AppInput } from "@/components/customer/ui";
import { getSessionRestoreDisplay, userFacingApiMessage } from "@/lib/api/errors";
import { sanitizeCustomerAuthRedirect } from "@/lib/auth/navigation";
import { getCustomerAuthRuntimeBlock } from "@/lib/auth/runtime-block";
import { publicEnv } from "@/lib/config/env";
import { useAuth } from "@/providers/auth-provider";
import { registerCustomer } from "./api";
import { registerSchema, type RegisterFormValues } from "./schemas";

export function RegisterPage() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const { markAuthenticated } = useAuth();
  const [appHost, setAppHost] = useState<string | null>(null);

  useEffect(() => {
    queueMicrotask(() => {
      setAppHost(window.location.hostname);
    });
  }, []);

  const runtimeBlock = getCustomerAuthRuntimeBlock(publicEnv.apiBaseUrl, appHost);
  const runtimeDisplay = runtimeBlock ? getSessionRestoreDisplay(runtimeBlock) : null;
  const loginHref = searchParams.toString() ? `/login?${searchParams.toString()}` : "/login";
  const form = useForm<RegisterFormValues>({
    resolver: zodResolver(registerSchema),
    defaultValues: {
      full_name: "",
      email: "",
      phone: "",
      password: "",
      password_confirmation: "",
    },
  });

  const registerMutation = useMutation({
    mutationFn: registerCustomer,
    onSuccess(session) {
      markAuthenticated(session);
      toast.success("Đã tạo tài khoản.");
      router.push(sanitizeCustomerAuthRedirect(searchParams.get("next")));
    },
  });
  const submitRegister = (values: RegisterFormValues) => {
    if (registerMutation.isPending || runtimeDisplay) {
      return;
    }

    registerMutation.mutate(values);
  };

  return (
    <main className="mx-auto grid min-h-[calc(100svh-4.5rem)] w-full max-w-6xl items-center gap-8 px-4 py-8 md:grid-cols-[minmax(0,1fr)_430px]">
      <section className="space-y-6">
        <div className="space-y-4">
          <h1 className="max-w-xl text-4xl font-bold leading-tight tracking-normal sm:text-5xl">Tạo tài khoản để quản lý lượt ghé.</h1>
          <p className="max-w-md text-base leading-7 text-muted-foreground">
            Dùng tài khoản để xem đặt bàn, đặt cọc, hóa đơn và cập nhật thông tin tự phục vụ trong cùng một nơi.
          </p>
        </div>
        <div className="grid max-w-xl gap-3 sm:grid-cols-3">
          <RegisterSignal icon={CalendarCheck2} title="Nhanh hơn" description="Thông tin liên hệ được ghi nhớ cho lần sau." />
          <RegisterSignal icon={ReceiptText} title="Rõ trạng thái" description="Theo dõi đặt cọc và hóa đơn khi có dữ liệu." />
          <RegisterSignal icon={ShieldCheck} title="Đúng chủ" description="Giữ các thao tác đặt bàn trong tài khoản của bạn." />
        </div>
        <p className="max-w-md text-sm text-muted-foreground">
          Bạn vẫn có thể xem thực đơn hoặc tìm bàn trước khi đăng ký.
        </p>
      </section>

      <AppCard className="p-5 sm:p-6">
        <div className="mb-5">
          <h2 className="text-xl font-bold">Đăng ký</h2>
          <p className="mt-1 text-sm text-muted-foreground">Tạo tài khoản khách hàng bằng thông tin liên hệ của bạn.</p>
        </div>
          <form
            className="space-y-4"
            onSubmit={form.handleSubmit(submitRegister)}
            noValidate
          >
            <AppInput
              label="Họ tên"
              autoComplete="name"
              error={form.formState.errors.full_name?.message}
              {...form.register("full_name")}
            />

            <div className="grid gap-4 sm:grid-cols-2">
              <AppInput
                label="Email"
                type="email"
                autoComplete="email"
                error={form.formState.errors.email?.message}
                {...form.register("email")}
              />

              <AppInput
                label="Số điện thoại"
                autoComplete="tel"
                error={form.formState.errors.phone?.message}
                {...form.register("phone")}
              />
            </div>

            <AppInput
              label="Mật khẩu"
              type="password"
              autoComplete="new-password"
              error={form.formState.errors.password?.message}
              {...form.register("password")}
            />

            <AppInput
              label="Nhập lại mật khẩu"
              type="password"
              autoComplete="new-password"
              error={form.formState.errors.password_confirmation?.message}
              {...form.register("password_confirmation")}
            />

            {runtimeDisplay ? (
              <Alert variant="destructive" className="rounded-lg">
                <AlertTitle>{runtimeDisplay.title}</AlertTitle>
                <AlertDescription className="space-y-2">
                  <p>{runtimeDisplay.message}</p>
                  {runtimeDisplay.retryHint ? <p>{runtimeDisplay.retryHint}</p> : null}
                </AlertDescription>
              </Alert>
            ) : registerMutation.error ? (
              <Alert variant="destructive" className="rounded-lg">
                <AlertDescription>{userFacingApiMessage(registerMutation.error)}</AlertDescription>
              </Alert>
            ) : null}

            <AppButton type="submit" className="w-full" disabled={registerMutation.isPending || Boolean(runtimeDisplay)}>
              {registerMutation.isPending ? "Đang tạo tài khoản" : "Tạo tài khoản"}
            </AppButton>
          </form>

          <p className="mt-5 text-center text-sm text-muted-foreground">
            Đã có tài khoản?{" "}
            <Link className="font-medium text-primary underline-offset-4 hover:underline" href={loginHref}>
              Đăng nhập
            </Link>
          </p>
      </AppCard>
    </main>
  );
}

function RegisterSignal({
  icon: Icon,
  title,
  description,
}: {
  icon: LucideIcon;
  title: string;
  description: string;
}) {
  return (
    <div className="min-h-28 rounded-lg border bg-card p-3">
      <Icon className="h-4 w-4 text-primary" />
      <p className="mt-3 text-sm font-semibold">{title}</p>
      <p className="mt-1 text-xs leading-5 text-muted-foreground">{description}</p>
    </div>
  );
}
