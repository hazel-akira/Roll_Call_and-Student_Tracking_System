import { Badge } from "@/components/ui/badge";
import { Card } from "@/components/ui/card";
import { Spinner } from "@/components/ui/spinner";
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
  loading,
}: {
  student: Student | null;
  history: HistoryItem[];
  loading?: boolean;
}) {
  if (!student) {
    return (
      <Card className="p-6 text-sm text-muted">
        Search for a student to view attendance history.
      </Card>
    );
  }

  return (
    <Card className="overflow-hidden">
      <div className="border-b px-5 py-4">
        <h3 className="section-title">Attendance history</h3>
        <p className="mt-1 text-sm text-muted">
          Recent roll-call marks for {student.full_name}.
        </p>
      </div>

      {loading ? (
        <div className="flex items-center gap-3 px-5 py-8 text-sm text-muted">
          <Spinner />
          Loading attendance history…
        </div>
      ) : history.length === 0 ? (
        <p className="px-5 py-8 text-sm text-muted">
          No attendance records found for this student yet.
        </p>
      ) : (
        <div className="divide-y">
          {history.map((item) => (
            <div key={item.id} className="px-5 py-4">
              <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                  <p className="font-medium text-foreground">{item.session.title}</p>
                  <p className="mt-1 text-sm text-muted">
                    {formatDate(item.session.session_date)} · {item.session.subject}
                  </p>
                </div>
                <Badge value={item.status} />
              </div>
              {item.remark ? (
                <p className="mt-3 text-sm text-muted">{item.remark}</p>
              ) : null}
            </div>
          ))}
        </div>
      )}
    </Card>
  );
}
