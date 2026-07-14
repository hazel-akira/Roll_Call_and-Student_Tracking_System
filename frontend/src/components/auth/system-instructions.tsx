import { ClipboardList, LayoutDashboard, School, ShieldCheck } from "lucide-react";

const steps = [
  {
    title: "Sign in",
    description: "Use your Microsoft institutional account to authenticate securely.",
  },
  {
    title: "Select your school",
    description: "Choose the school you are working in for the correct class streams and rosters.",
  },
  {
    title: "Take roll call",
    description: "Mark attendance for each learner, then save to submit the roll call report by email.",
  },
  {
    title: "Review reports",
    description: "Access attendance summaries and exported roll call memos from your dashboard.",
  },
];

const roles = [
  {
    icon: ShieldCheck,
    title: "Administrators",
    description: "Manage schools, users, and system-wide reporting.",
  },
  {
    icon: ClipboardList,
    title: "Teachers",
    description: "Conduct roll call sessions and track learner attendance.",
  },
  {
    icon: LayoutDashboard,
    title: "ICT Staff",
    description: "Monitor integrations, audit activity, and support operations.",
  },
];

export function SystemInstructions() {
  return (
    <aside className="flex min-h-[320px] flex-col justify-between bg-(--color-primary-deep) px-8 py-10 text-white lg:min-h-screen lg:px-12 lg:py-14">
      <div>
        <p className="text-sm font-semibold uppercase tracking-[0.2em] text-white/70">
          Pioneer Group of Schools
        </p>
        <h1 className="mt-4 text-3xl font-semibold leading-tight lg:text-4xl">
          Roll Call and Student Tracking System
        </h1>
        <p className="mt-4 max-w-xl text-base leading-relaxed text-white/85">
          Enterprise attendance platform for recording daily roll call, syncing learner data, and
          distributing reports to school stakeholders immediately after each session.
        </p>

        <div className="mt-8 space-y-4">
          <div className="flex items-start gap-3">
            <School className="mt-0.5 shrink-0" size={20} />
            <p className="text-sm leading-relaxed text-white/85">
              Sign in with your <strong className="font-semibold text-white">Microsoft institutional account</strong>{" "}
              to access role-based dashboards and attendance workflows.
            </p>
          </div>
        </div>

        <div className="mt-10">
          <h2 className="text-sm font-semibold uppercase tracking-[0.18em] text-white/70">
            Who uses this system
          </h2>
          <ul className="mt-4 space-y-4">
            {roles.map((role) => (
              <li key={role.title} className="flex items-start gap-3">
                <role.icon className="mt-0.5 shrink-0 text-(--color-primary)" size={18} />
                <div>
                  <p className="font-semibold">{role.title}</p>
                  <p className="text-sm text-white/80">{role.description}</p>
                </div>
              </li>
            ))}
          </ul>
        </div>
      </div>

      <div className="mt-10">
        <h2 className="text-sm font-semibold uppercase tracking-[0.18em] text-white/70">
          Quick start
        </h2>
        <ol className="mt-4 space-y-4">
          {steps.map((step, index) => (
            <li key={step.title} className="flex gap-4">
              <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-white/10 text-sm font-semibold">
                {index + 1}
              </span>
              <div>
                <p className="font-semibold">{step.title}</p>
                <p className="text-sm text-white/80">{step.description}</p>
              </div>
            </li>
          ))}
        </ol>
      </div>
    </aside>
  );
}
