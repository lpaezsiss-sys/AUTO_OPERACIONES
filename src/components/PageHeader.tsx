import { ReactNode } from "react";

export function PageHeader({
  title,
  description,
  action,
}: {
  title: string;
  description?: string;
  action?: ReactNode;
}) {
  return (
    <div className="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
      <div className="animate-fade-up">
        <h1 className="font-[family-name:var(--font-fraunces)] text-3xl font-semibold tracking-tight text-ink sm:text-4xl">
          {title}
        </h1>
        {description ? (
          <p className="mt-2 max-w-xl text-sm leading-relaxed text-ink-muted sm:text-base">
            {description}
          </p>
        ) : null}
      </div>
      {action ? <div className="animate-fade-up stagger-1">{action}</div> : null}
    </div>
  );
}
