"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { zodResolver } from "@hookform/resolvers/zod";
import { useMutation } from "@tanstack/react-query";
import { useRouter, useSearchParams } from "next/navigation";
import { useForm } from "react-hook-form";
import { toast } from "sonner";
import { ArrowRight, CalendarCheck2, ReceiptText, ShieldCheck, UserRound, type LucideIcon } from "lucide-react";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { AppButton, AppCard, AppInput } from "@/components/customer/ui";
import { useCustomerSession } from "@/features/auth/hooks";
import { getSessionRestoreDisplay, userFacingApiMessage } from "@/lib/api/errors";
import { sanitizeCustomerAuthRedirect } from "@/lib/auth/navigation";
import { consumeCustomerReturnToAction } from "@/lib/auth/return-to-action";
import { getCustomerAuthRuntimeBlock } from "@/lib/auth/runtime-block";
import { publicEnv } from "@/lib/config/env";
import { useAuth } from "@/providers/auth-provider";
import { loginCustomer } from "./api";
import { loginSchema, type LoginFormValues } from "./schemas";

export function LoginPage() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const { markAuthenticated } = useAuth();
  const { continueAsGuest } = useCustomerSession();
  const [appHost, setAppHost] = useState<string | null>(null);

  useEffect(() => {
    queueMicrotask(() => {
      setAppHost(window.location.hostname);
    });
  }, []);

  const runtimeBlock = getCustomerAuthRuntimeBlock(publicEnv.apiBaseUrl, appHost);
  const runtimeDisplay = runtimeBlock ? getSessionRestoreDisplay(runtimeBlock) : null;
  const registerHref = searchParams.toString() ? `/register?${searchParams.toString()}` : "/register";
  const nextHref = sanitizeCustomerAuthRedirect(searchParams.get("next"), "/");
  const form = useForm<LoginFormValues>({
    resolver: zodResolver(loginSchema),
    defaultValues: {
      identifier: "",
      password: "",
    },
  });

  const loginMutation = useMutation({
    mutationFn: loginCustomer,
    onSuccess(session) {
      markAuthenticated(session);
      toast.success("Đã đăng nhập.");
      const returnToAction = consumeCustomerReturnToAction();

      router.push(sanitizeCustomerAuthRedirect(searchParams.get("next") ?? returnToAction?.href, "/reservations"));
    },
  });
  const submitLogin = (values: LoginFormValues) => {
    if (loginMutation.isPending || runtimeDisplay) {
      return;
    }

    loginMutation.mutate(values);
  };

  const handleContinueAsGuest = () => {
    continueAsGuest();
    router.push(nextHref);
  };

  return (
    <main className="mx-auto grid min-h-[calc(100svh-4.5rem)] w-full max-w-6xl items-center gap-8 px-4 py-8 md:grid-cols-[minmax(0,1fr)_430px]">
      <section className="space-y-6">
        <div className="space-y-4">
          <h1 className="max-w-xl text-4xl font-bold leading-tight tracking-normal sm:text-5xl">Tiếp tục lượt ghé của bạn.</h1>
          <p className="max-w-md text-base leading-7 text-muted-foreground">
            Đăng nhập để xem lịch sử đặt bàn, đặt cọc, hóa đơn và thông tin tài khoản. Bạn vẫn có thể xem trước với tư cách khách.
          </p>
        </div>
        <div className="grid max-w-xl gap-3 sm:grid-cols-2">
          <AppButton type="button" variant="outline" className="justify-between border-teal-500 text-teal-700 hover:bg-teal-50" onClick={handleContinueAsGuest}>
            <span className="inline-flex items-center gap-2">
              <UserRound className="h-4 w-4" />
              Tiếp tục với tư cách khách
            </span>
            <ArrowRight className="h-4 w-4" />
          </AppButton>
          <AppButton asChild variant="outline" className="justify-between">
            <Link href="/menu">
              Xem thực đơn trước
              <ArrowRight className="h-4 w-4" />
            </Link>
          </AppButton>
        </div>
        <div className="grid max-w-xl gap-3 sm:grid-cols-3">
          <AuthSignal icon={CalendarCheck2} title="Lịch đặt" description="Theo dõi lượt ghé hiện có." />
          <AuthSignal icon={ReceiptText} title="Hóa đơn" description="Xem trạng thái khi nhà hàng sẵn sàng." />
          <AuthSignal icon={ShieldCheck} title="Phiên khách" description="Giữ thao tác tự phục vụ rõ ràng." />
        </div>
      </section>

      <AppCard className="p-5 sm:p-6">
        <div className="mb-5">
          <h2 className="text-xl font-bold">Đăng nhập</h2>
          <p className="mt-1 text-sm text-muted-foreground">Dùng email, số điện thoại hoặc mã khách hàng.</p>
        </div>
          <form className="space-y-4" onSubmit={form.handleSubmit(submitLogin)} noValidate>
            <AppInput
              label="Email, số điện thoại hoặc mã khách hàng"
              autoComplete="username"
              error={form.formState.errors.identifier?.message}
              {...form.register("identifier")}
            />

            <AppInput
              label="Mật khẩu"
              type="password"
              autoComplete="current-password"
              error={form.formState.errors.password?.message}
              {...form.register("password")}
            />

            {runtimeDisplay ? (
              <Alert variant="destructive" className="rounded-lg">
                <AlertTitle>{runtimeDisplay.title}</AlertTitle>
                <AlertDescription className="space-y-2">
                  <p>{runtimeDisplay.message}</p>
                  {runtimeDisplay.retryHint ? <p>{runtimeDisplay.retryHint}</p> : null}
                </AlertDescription>
              </Alert>
            ) : loginMutation.error ? (
              <Alert variant="destructive" className="rounded-lg">
                <AlertDescription>{userFacingApiMessage(loginMutation.error)}</AlertDescription>
              </Alert>
            ) : null}

            <AppButton type="submit" className="w-full" disabled={loginMutation.isPending || Boolean(runtimeDisplay)}>
              {loginMutation.isPending ? "Đang đăng nhập" : "Đăng nhập"}
            </AppButton>
          </form>

          <p className="mt-5 text-center text-sm text-muted-foreground">
            Khách hàng mới?{" "}
            <Link className="font-medium text-primary underline-offset-4 hover:underline" href={registerHref}>
              Tạo tài khoản
            </Link>
          </p>
      </AppCard>
    </main>
  );
}

function AuthSignal({
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
