"use client";

import { zodResolver } from "@hookform/resolvers/zod";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useForm } from "react-hook-form";
import { toast } from "sonner";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { StatusBadge } from "@/components/status/status-badge";
import { EmptyState, ErrorState, LoadingBlock } from "@/components/states/state-blocks";
import { featureFlags } from "@/lib/config/feature-flags";
import { queryKeys } from "@/lib/api/query-keys";
import { formatDateTime } from "@/lib/contracts/format";
import {
  acceptWaitingListEntry,
  cancelWaitingListEntry,
  confirmWaitingListArrival,
  createWaitingListEntry,
  declineWaitingListEntry,
  listWaitingList,
} from "./api";
import { waitingListCreateSchema, type WaitingListCreateValues } from "./schemas";

export function WaitingListPage() {
  const queryClient = useQueryClient();
  const form = useForm<WaitingListCreateValues>({
    resolver: zodResolver(waitingListCreateSchema),
    defaultValues: {
      guest_count: 2,
      guest_name: "",
      phone: "",
      notes: "",
    },
  });
  const waitingListQuery = useQuery({
    queryKey: queryKeys.waitingList.list,
    queryFn: listWaitingList,
    enabled: featureFlags.waitingList,
  });
  const invalidate = () => queryClient.invalidateQueries({ queryKey: queryKeys.waitingList.list });
  const createMutation = useMutation({
    mutationFn: createWaitingListEntry,
    onSuccess() {
      toast.success("Waiting list entry created.");
      form.reset();
      invalidate();
    },
  });
  const actionMutation = useMutation({
    mutationFn: ({ id, rowVersion, action }: { id: number; rowVersion: number; action: "accept" | "arrival" | "decline" | "cancel" }) => {
      const body = { row_version: rowVersion };
      if (action === "accept") return acceptWaitingListEntry(id, body);
      if (action === "arrival") return confirmWaitingListArrival(id, body);
      if (action === "decline") return declineWaitingListEntry(id, body);
      return cancelWaitingListEntry(id, body);
    },
    onSuccess() {
      toast.success("Waiting list updated.");
      invalidate();
    },
  });

  if (!featureFlags.waitingList) {
    return (
      <main className="mx-auto w-full max-w-3xl px-4 py-6">
        <EmptyState title="Waiting list is not available" description="This customer flow is disabled for the current rollout." />
      </main>
    );
  }

  return (
    <main className="mx-auto w-full max-w-5xl px-4 py-6">
      <section className="mb-5">
        <h1 className="text-4xl font-semibold tracking-normal">Waiting list</h1>
        <p className="mt-2 max-w-xl text-muted-foreground">
          Join the live customer waiting list and respond to staff notifications from the same account.
        </p>
      </section>

      <div className="grid gap-5 lg:grid-cols-[340px_1fr]">
        <Card className="h-fit rounded-lg">
          <CardHeader>
            <CardTitle>Join waiting list</CardTitle>
          </CardHeader>
          <CardContent>
            <form className="space-y-4" onSubmit={form.handleSubmit((values) => createMutation.mutate(values))}>
              <div className="space-y-2">
                <Label htmlFor="guest_name">Guest name</Label>
                <Input id="guest_name" className="min-h-11 rounded-lg" {...form.register("guest_name")} />
              </div>
              <div className="grid grid-cols-2 gap-3">
                <div className="space-y-2">
                  <Label htmlFor="guest_count">Guests</Label>
                  <Input id="guest_count" type="number" min={1} className="min-h-11 rounded-lg" {...form.register("guest_count", { valueAsNumber: true })} />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="phone">Phone</Label>
                  <Input id="phone" className="min-h-11 rounded-lg" {...form.register("phone")} />
                </div>
              </div>
              <div className="space-y-2">
                <Label htmlFor="notes">Notes</Label>
                <Textarea id="notes" className="min-h-20 rounded-lg" {...form.register("notes")} />
              </div>
              {createMutation.error ? <ErrorState error={createMutation.error} title="Could not join waiting list" /> : null}
              <Button type="submit" className="min-h-11 w-full rounded-lg" disabled={createMutation.isPending}>
                {createMutation.isPending ? "Joining" : "Join waiting list"}
              </Button>
            </form>
          </CardContent>
        </Card>

        <section className="space-y-3">
          {waitingListQuery.isLoading ? <LoadingBlock label="Loading waiting list" /> : null}
          {waitingListQuery.error ? <ErrorState error={waitingListQuery.error} title="Waiting list is unavailable" onRetry={() => waitingListQuery.refetch()} /> : null}
          {waitingListQuery.data?.data.length === 0 ? (
            <EmptyState title="No active waiting list entries" description="Join the list when you arrive or when the restaurant opens table notifications." />
          ) : null}
          {waitingListQuery.data?.data.map((entry) => (
            <Card key={entry.waiting_id} className="rounded-lg">
              <CardContent className="space-y-4 p-4">
                <div className="flex items-start justify-between gap-3">
                  <div>
                    <p className="text-sm text-muted-foreground">Entry {entry.waiting_id}</p>
                    <h2 className="text-lg font-semibold">{entry.guest_name ?? "Guest"} - {entry.guest_count} guests</h2>
                    <p className="text-sm text-muted-foreground">Requested {formatDateTime(entry.requested_at)}</p>
                  </div>
                  <StatusBadge status={entry.status} />
                </div>
                <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
                  <Button type="button" variant="outline" className="rounded-lg" onClick={() => actionMutation.mutate({ id: entry.waiting_id, rowVersion: entry.row_version, action: "accept" })}>
                    Accept
                  </Button>
                  <Button type="button" variant="outline" className="rounded-lg" onClick={() => actionMutation.mutate({ id: entry.waiting_id, rowVersion: entry.row_version, action: "arrival" })}>
                    Arrived
                  </Button>
                  <Button type="button" variant="outline" className="rounded-lg" onClick={() => actionMutation.mutate({ id: entry.waiting_id, rowVersion: entry.row_version, action: "decline" })}>
                    Decline
                  </Button>
                  <Button type="button" variant="outline" className="rounded-lg" onClick={() => actionMutation.mutate({ id: entry.waiting_id, rowVersion: entry.row_version, action: "cancel" })}>
                    Cancel
                  </Button>
                </div>
              </CardContent>
            </Card>
          ))}
          {actionMutation.error ? <ErrorState error={actionMutation.error} title="Waiting list action failed" /> : null}
        </section>
      </div>
    </main>
  );
}
