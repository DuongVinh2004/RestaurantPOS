import { AppCard, PriceText } from "@/components/customer/ui";

export type PaymentBreakdownLine = {
  label: string;
  amount: string | number | null | undefined;
  currency: string;
  emphasis?: boolean;
};

export function PaymentBreakdown({
  title,
  description,
  lines,
  note,
}: {
  title: string;
  description?: string;
  lines: PaymentBreakdownLine[];
  note?: string;
}) {
  if (lines.length === 0) {
    return null;
  }

  return (
    <AppCard className="p-4">
      <div className="space-y-3">
        <div>
          <h4 className="font-semibold">{title}</h4>
          {description ? <p className="mt-1 text-sm text-muted-foreground">{description}</p> : null}
        </div>
        <dl className="space-y-2">
          {lines.map((line) => (
            <div
              key={line.label}
              className={line.emphasis ? "flex items-center justify-between gap-3 border-t pt-2" : "flex items-center justify-between gap-3"}
            >
              <dt className={line.emphasis ? "font-semibold" : "text-sm text-muted-foreground"}>{line.label}</dt>
              <dd>
                <PriceText amount={line.amount} currency={line.currency} />
              </dd>
            </div>
          ))}
        </dl>
        {note ? <p className="text-sm text-muted-foreground">{note}</p> : null}
      </div>
    </AppCard>
  );
}
