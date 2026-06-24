import { Card } from "@/components/ui/card";

export function SummaryCard({
  label,
  value,
  helper,
}: {
  label: string;
  value: string | number;
  helper?: string;
}) {
  return (
    <Card className="p-5">
      <p className="text-sm font-medium text-(--color-primary)">{label}</p>
      <p className="mt-3 text-3xl font-semibold text-foreground">{value}</p>
      {helper ? <p className="mt-2 text-sm text-muted">{helper}</p> : null}
    </Card>
  );
}
