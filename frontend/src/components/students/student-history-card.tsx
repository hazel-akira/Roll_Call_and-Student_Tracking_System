import { Badge } from "@/components/ui/badge";
import { Card } from "@/components/ui/card";
import { formatDate } from "@/lib/utils";
import type { Student } from "@/types";

type HistoryItem = {
  id: number;
  status: string;
  remark?: string | null;
  marked_at?: string | null;
  session: {
    id: number;
    title: string;
    session_date: string;
    class: string;
    subject: string;
  };
};

export function StudentHistoryCard({
  student,
  history,
}: {
  student: Student | null;
  history: HistoryItem[];
}) {
  if (!student) {
    return (
      <Card className="p-6 text-sm text-slate-500 dark:text-slate-400">
        Select a student to view attendance history.
      </Card>
    );
  }

  return (
    <Card className="overflow-hidden">
      <div className="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
        <h3 className="text-lg font-semibold text-slate-900 dark:text-white">
          {student.full_name}
        </h3>
        <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
          {student.admission_number} · {student.class?.name ?? "No class assigned"}
        </p>
      </div>
      <div className="divide-y divide-slate-200 dark:divide-slate-800">
        {history.map((item) => (
          <div key={item.id} className="px-5 py-4">
            <div className="flex flex-wrap items-center justify-between gap-3">
              <div>
                <p className="font-medium text-slate-900 dark:text-white">{item.session.title}</p>
                <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
                  {formatDate(item.session.session_date)} · {item.session.subject}
                </p>
              </div>
              <Badge value={item.status} />
            </div>
            {item.remark ? (
              <p className="mt-3 text-sm text-slate-600 dark:text-slate-300">{item.remark}</p>
            ) : null}
          </div>
        ))}
      </div>
    </Card>
  );
}
