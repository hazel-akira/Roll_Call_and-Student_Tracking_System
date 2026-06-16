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
    <Card className="p-5 bg-#13365F">
      <p className="text-sm font-medium text-white dark:text-white">{label}</p>
      <p className="mt-3 text-3xl font-semibold text-slate-900 dark:text-white">{value}</p>
      {helper ? (
        <p className="mt-2 text-sm text-slate-500 dark:text-slate-400">{helper}</p>
      ) : null}
    </Card>
  );
}
