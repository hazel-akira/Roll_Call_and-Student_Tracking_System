import { LoginForm } from "@/components/auth/login-form";
import { SystemInstructions } from "@/components/auth/system-instructions";

export default function AuthLayout({ children }: { children: React.ReactNode }) {
  return (
    <div className="min-h-screen lg:grid lg:grid-cols-2">
      <SystemInstructions />
      <main className="flex items-center justify-center px-6 py-12 lg:px-8">{children}</main>
    </div>
  );
}
