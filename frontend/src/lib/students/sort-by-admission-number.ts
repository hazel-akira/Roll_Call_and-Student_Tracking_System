import type { Student } from "@/types";

function admissionNumber(student: Student): string {
  return String(student.admission_number ?? "").trim();
}

export function compareAdmissionNumbers(left: Student, right: Student): number {
  return admissionNumber(left).localeCompare(admissionNumber(right), undefined, {
    numeric: true,
    sensitivity: "base",
  });
}

export function sortStudentsByAdmissionNumber<T extends Student>(students: T[]): T[] {
  return [...students].sort(compareAdmissionNumbers);
}
