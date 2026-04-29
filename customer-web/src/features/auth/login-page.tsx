"use client";

import { useEffect, useState } from "react";
import { zodResolver } from "@hookform/resolvers/zod";
import { useMutation } from "@tanstack/react-query";
import { useRouter, useSearchParams } from "next/navigation";
import { useForm } from "react-hook-form";
import { toast } from "sonner";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import { getSessionRestoreDisplay, userFacingApiMessage } from "@/lib/api/errors";
import { sanitizeCustomerAuthRedirect } from "@/lib/auth/navigation";
import { getCustomerAuthRuntimeBlock } from "@/lib/auth/runtime-block";
import { publicEnv } from "@/lib/config/env";
import { useAuth } from "@/providers/auth-provider";
import { loginCustomer } from "./api";
import { loginSchema, type LoginFormValues } from "./schemas";

export function LoginPage() {
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
      router.push(sanitizeCustomerAuthRedirect(searchParams.get("next")));
    },
  });

  return (
    <main className="mx-auto grid min-h-[calc(100svh-4rem)] w-full max-w-5xl items-center gap-8 px-4 py-8 md:grid-cols-[1fr_420px]">
      <section className="space-y-4">
        <p className="text-sm font-medium text-primary">Tài khoản khách hàng</p>
        <h1 className="max-w-lg text-4xl font-semibold leading-tight tracking-normal">Quản lý lượt ghé nhà hàng dễ hơn.</h1>
        <p className="max-w-md text-base text-muted-foreground">
          Đăng nhập để xem lịch đặt, đặt cọc, hóa đơn và các thông tin chỉ dành cho tài khoản của bạn.
        </p>
      </section>

      <Card className="rounded-lg">
        <CardHeader>
          <CardTitle>Đăng nhập</CardTitle>
        </CardHeader>
        <CardContent>
          <form
            className="space-y-4"
            onSubmit={form.handleSubmit((values) => loginMutation.mutate(values))}
            noValidate
          >
            <div className="space-y-2">
              <Label htmlFor="identifier">Email, số điện thoại hoặc mã khách hàng</Label>
              <Input id="identifier" autoComplete="username" className="min-h-11 rounded-lg" {...form.register("identifier")} />
              {form.formState.errors.identifier ? (
                <p className="text-sm text-destructive">{form.formState.errors.identifier.message}</p>
              ) : null}
            </div>

            <div className="space-y-2">
              <Label htmlFor="password">Mật khẩu</Label>
              <Input id="password" type="password" autoComplete="current-password" className="min-h-11 rounded-lg" {...form.register("password")} />
              {form.formState.errors.password ? (
                <p className="text-sm text-destructive">{form.formState.errors.password.message}</p>
              ) : null}
            </div>

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

            <Button type="submit" className="min-h-11 w-full rounded-lg" disabled={loginMutation.isPending || Boolean(runtimeDisplay)}>
              {loginMutation.isPending ? "Đang đăng nhập" : "Đăng nhập"}
            </Button>
          </form>
        </CardContent>
      </Card>
    </main>
  );
}
