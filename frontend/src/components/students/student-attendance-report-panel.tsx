"use client";

import { useEffect, useState } from "react";
import { Download, FileText } from "lucide-react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Spinner } from "@/components/ui/spinner";
import {
  downloadStudentAttendanceReport,
  fetchStudentAttendanceReport,
  fetchStudentAttendanceReportPdf,
  type StudentAttendanceReport,
} from "@/lib/students/attendance-report";
import { formatDate } from "@/lib/utils";
import type { Student } from "@/types";

function defaultDateRange(): { from: string; to: string } {
  const to = new Date();
  const from = new Date();
  from.setMonth(from.getMonth() - 3);

  return {
    from: from.toISOString().slice(0, 10),
    to: to.toISOString().slice(0, 10),
  };
}

export function StudentAttendanceReportPanel({ student }: { student: Student }) {
  const [range, setRange] = useState(defaultDateRange);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [report, setReport] = useState<StudentAttendanceReport | null>(null);
  const [previewUrl, setPreviewUrl] = useState<string | null>(null);
  const [downloading, setDownloading] = useState(false);

  useEffect(() => {
    return () => {
      if (previewUrl) {
        URL.revokeObjectURL(previewUrl);
      }
    };
  }, [previewUrl]);

  useEffect(() => {
    setReport(null);
    setError(null);
    setPreviewUrl((current) => {
      if (current) {
        URL.revokeObjectURL(current);
      }

      return null;
    });
  }, [student.id]);

  async function generateReport() {
    setLoading(true);
    setError(null);

    try {
      const [reportData, pdfFile] = await Promise.all([
        fetchStudentAttendanceReport(student.id, range),
        fetchStudentAttendanceReportPdf(student.id, range),
      ]);

      setReport(reportData);
      setPreviewUrl((current) => {
        if (current) {
          URL.revokeObjectURL(current);
        }

        return URL.createObjectURL(pdfFile.blob);
      });
    } catch {
      setReport(null);
      setPreviewUrl((current) => {
        if (current) {
          URL.revokeObjectURL(current);
        }

        return null;
      });
      setError("Unable to generate the attendance report. Try again.");
    } finally {
      setLoading(false);
    }
  }

  async function handleDownload() {
    setDownloading(true);

    try {
      const file = await fetchStudentAttendanceReportPdf(student.id, range);
      downloadStudentAttendanceReport(file);
    } catch {
      setError("Unable to download the PDF report.");
    } finally {
      setDownloading(false);
    }
  }

  return (
    <Card className="overflow-hidden">
      <div className="border-b px-5 py-4">
        <h3 className="section-title">Attendance report</h3>
        <p className="mt-1 text-sm text-muted">
          Generate a PDF attendance report for {student.full_name}.
        </p>
      </div>

      <div className="space-y-4 px-5 py-4">
        <div className="grid gap-4 md:grid-cols-[1fr_1fr_auto]">
          <div>
            <label htmlFor="report-from" className="text-xs font-semibold uppercase tracking-wide text-muted">
              From
            </label>
            <input
              id="report-from"
              type="date"
              className="field-control mt-2 w-full"
              value={range.from}
              onChange={(event) => setRange((current) => ({ ...current, from: event.target.value }))}
            />
          </div>
          <div>
            <label htmlFor="report-to" className="text-xs font-semibold uppercase tracking-wide text-muted">
              To
            </label>
            <input
              id="report-to"
              type="date"
              className="field-control mt-2 w-full"
              value={range.to}
              onChange={(event) => setRange((current) => ({ ...current, to: event.target.value }))}
            />
          </div>
          <div className="flex items-end">
            <Button type="button" disabled={loading} onClick={() => void generateReport()}>
              <FileText size={16} />
              {loading ? "Generating…" : "Generate report"}
            </Button>
          </div>
        </div>

        {error ? (
          <p className="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-200">
            {error}
          </p>
        ) : null}

        {loading ? (
          <div className="flex items-center gap-3 rounded-xl border bg-(--surface-muted) px-4 py-6 text-sm text-muted">
            <Spinner />
            Building attendance report and PDF preview…
          </div>
        ) : null}

        {report ? (
          <>
            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
              <SummaryStat label="Records" value={report.summary.records} />
              <SummaryStat label="Present" value={report.summary.present} />
              <SummaryStat label="Attendance rate" value={`${report.summary.attendance_rate}%`} />
              <SummaryStat
                label="Absent / missing"
                value={report.summary.absent + report.summary.missing}
              />
            </div>

            <div className="overflow-x-auto rounded-xl border">
              <table className="min-w-full text-sm">
                <thead className="bg-(--surface-muted) text-left text-muted">
                  <tr>
                    <th className="px-4 py-3 font-medium">Date</th>
                    <th className="px-4 py-3 font-medium">Session</th>
                    <th className="px-4 py-3 font-medium">Subject</th>
                    <th className="px-4 py-3 font-medium">Status</th>
                    <th className="px-4 py-3 font-medium">Remark</th>
                  </tr>
                </thead>
                <tbody>
                  {report.rows.length === 0 ? (
                    <tr>
                      <td colSpan={5} className="px-4 py-6 text-center text-muted">
                        No attendance records in this date range.
                      </td>
                    </tr>
                  ) : (
                    report.rows.map((row, index) => (
                      <tr key={`${row.session_date}-${row.session_title}-${index}`} className="border-t">
                        <td className="px-4 py-3 text-foreground">
                          {row.session_date ? formatDate(row.session_date) : "—"}
                        </td>
                        <td className="px-4 py-3 text-foreground">{row.session_title ?? "—"}</td>
                        <td className="px-4 py-3 text-muted">{row.subject ?? "—"}</td>
                        <td className="px-4 py-3">
                          <Badge value={row.status.toLowerCase()} />
                        </td>
                        <td className="px-4 py-3 text-muted">{row.remark ?? "—"}</td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            </div>

            {previewUrl ? (
              <div className="space-y-3">
                <div className="flex flex-wrap items-center justify-between gap-3">
                  <p className="text-sm font-medium text-foreground">PDF preview</p>
                  <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    disabled={downloading}
                    onClick={() => void handleDownload()}
                  >
                    <Download size={16} />
                    {downloading ? "Downloading…" : "Download PDF"}
                  </Button>
                </div>
                <iframe
                  title={`Attendance report for ${student.full_name}`}
                  src={previewUrl}
                  className="h-[70vh] w-full rounded-xl border bg-(--surface-solid)"
                />
              </div>
            ) : null}
          </>
        ) : null}
      </div>
    </Card>
  );
}

function SummaryStat({ label, value }: { label: string; value: string | number }) {
  return (
    <div className="rounded-xl border bg-(--surface-muted) px-4 py-3">
      <p className="text-xs font-semibold uppercase tracking-wide text-muted">{label}</p>
      <p className="mt-2 text-2xl font-semibold text-foreground">{value}</p>
    </div>
  );
}
