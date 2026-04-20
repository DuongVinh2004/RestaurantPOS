"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import { useMutation } from "@tanstack/react-query";
import { useRouter, useSearchParams } from "next/navigation";
import { useForm } from "react-hook-form";
import { toast } from "sonner";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { userFacingApiMessage } from "@/lib/api/errors";
import { useAuth } from "@/providers/auth-provider";
import { loginCustomer } from "./api";
import { loginSchema, type LoginFormValues } from "./schemas";

export function LoginPage() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const { markAuthenticated } = useAuth();
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
      toast.success("Signed in.");
      router.push(searchParams.get("next") || "/reservations");
    },
  });

  return (
    <main className="mx-auto grid min-h-[calc(100svh-4rem)] w-full max-w-5xl items-center gap-8 px-4 py-8 md:grid-cols-[1fr_420px]">
      <section className="space-y-4">
        <p className="text-sm font-medium text-primary">Customer access</p>
        <h1 className="max-w-lg text-4xl font-semibold leading-tight tracking-normal">Manage your visit without waiting at the counter.</h1>
        <p className="max-w-md text-base text-muted-foreground">
          Sign in to see reservations, deposits, bills, and any account tools enabled for your rollout.
        </p>
      </section>

      <Card className="rounded-lg">
        <CardHeader>
          <CardTitle>Sign in</CardTitle>
        </CardHeader>
        <CardContent>
          <form
            className="space-y-4"
            onSubmit={form.handleSubmit((values) => loginMutation.mutate(values))}
            noValidate
          >
            <div className="space-y-2">
              <Label htmlFor="identifier">Email, phone, or customer id</Label>
              <Input id="identifier" autoComplete="username" className="min-h-11 rounded-lg" {...form.register("identifier")} />
              {form.formState.errors.identifier ? (
                <p className="text-sm text-destructive">{form.formState.errors.identifier.message}</p>
              ) : null}
            </div>

            <div className="space-y-2">
              <Label htmlFor="password">Password</Label>
              <Input id="password" type="password" autoComplete="current-password" className="min-h-11 rounded-lg" {...form.register("password")} />
              {form.formState.errors.password ? (
                <p className="text-sm text-destructive">{form.formState.errors.password.message}</p>
              ) : null}
            </div>

            {loginMutation.error ? (
              <Alert variant="destructive" className="rounded-lg">
                <AlertDescription>{userFacingApiMessage(loginMutation.error)}</AlertDescription>
              </Alert>
            ) : null}

            <Button type="submit" className="min-h-11 w-full rounded-lg" disabled={loginMutation.isPending}>
              {loginMutation.isPending ? "Signing in" : "Sign in"}
            </Button>
          </form>
        </CardContent>
      </Card>
    </main>
  );
}
