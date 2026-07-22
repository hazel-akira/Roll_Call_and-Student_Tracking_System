"use client";

import { useEffect, useId, useLayoutEffect, useMemo, useRef, useState } from "react";
import { createPortal } from "react-dom";
import { Check, ChevronsUpDown, Search, X } from "lucide-react";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import type { SchoolStaffMember } from "@/types";

type StaffMultiSelectProps = {
  options: SchoolStaffMember[];
  value: number[];
  onChange: (next: number[]) => void;
  placeholder?: string;
  disabled?: boolean;
};

type MenuPosition = {
  top: number;
  left: number;
  width: number;
  maxHeight: number;
  openUpward: boolean;
};

export function StaffMultiSelect({
  options,
  value,
  onChange,
  placeholder = "Search and select staff…",
  disabled = false,
}: StaffMultiSelectProps) {
  const listId = useId();
  const rootRef = useRef<HTMLDivElement>(null);
  const triggerRef = useRef<HTMLButtonElement>(null);
  const menuRef = useRef<HTMLDivElement>(null);
  const inputRef = useRef<HTMLInputElement>(null);
  const [open, setOpen] = useState(false);
  const [query, setQuery] = useState("");
  const [draft, setDraft] = useState<number[]>(value);
  const [menuPosition, setMenuPosition] = useState<MenuPosition | null>(null);

  const displayIds = open ? draft : value;

  const selected = useMemo(() => {
    const selectedSet = new Set(displayIds);
    return options.filter((member) => selectedSet.has(member.id));
  }, [displayIds, options]);

  const filtered = useMemo(() => {
    const needle = query.trim().toLowerCase();
    if (!needle) {
      return options;
    }

    return options.filter((member) => {
      const haystack = `${member.name} ${member.email} ${member.job_title ?? ""}`.toLowerCase();
      return haystack.includes(needle);
    });
  }, [options, query]);

  const closeWithoutSaving = () => {
    setOpen(false);
    setQuery("");
    setDraft(value);
    setMenuPosition(null);
  };

  const assignSelection = () => {
    onChange(draft);
    setOpen(false);
    setQuery("");
    setMenuPosition(null);
  };

  const updateMenuPosition = () => {
    const trigger = triggerRef.current;
    if (!trigger) {
      return;
    }

    const rect = trigger.getBoundingClientRect();
    const gap = 8;
    const preferredHeight = 320;
    const spaceBelow = window.innerHeight - rect.bottom - gap - 12;
    const spaceAbove = rect.top - gap - 12;
    const openUpward = spaceBelow < 220 && spaceAbove > spaceBelow;
    const available = openUpward ? spaceAbove : spaceBelow;

    setMenuPosition({
      top: openUpward ? rect.top - gap : rect.bottom + gap,
      left: rect.left,
      width: rect.width,
      maxHeight: Math.max(180, Math.min(preferredHeight, available)),
      openUpward,
    });
  };

  useEffect(() => {
    if (!open) {
      setDraft(value);
    }
  }, [open, value]);

  useLayoutEffect(() => {
    if (!open) {
      return;
    }

    updateMenuPosition();
    queueMicrotask(() => inputRef.current?.focus());

    const onReposition = () => updateMenuPosition();
    window.addEventListener("resize", onReposition);
    window.addEventListener("scroll", onReposition, true);

    return () => {
      window.removeEventListener("resize", onReposition);
      window.removeEventListener("scroll", onReposition, true);
    };
  }, [open]);

  useEffect(() => {
    if (!open) {
      return;
    }

    const onPointerDown = (event: MouseEvent) => {
      const target = event.target as Node;
      if (rootRef.current?.contains(target) || menuRef.current?.contains(target)) {
        return;
      }
      closeWithoutSaving();
    };

    const onKeyDown = (event: KeyboardEvent) => {
      if (event.key === "Escape") {
        closeWithoutSaving();
      }
    };

    document.addEventListener("mousedown", onPointerDown);
    document.addEventListener("keydown", onKeyDown);
    return () => {
      document.removeEventListener("mousedown", onPointerDown);
      document.removeEventListener("keydown", onKeyDown);
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps -- close handlers refresh with open/value
  }, [open, value]);

  const toggle = (id: number) => {
    setDraft((current) =>
      current.includes(id) ? current.filter((item) => item !== id) : [...current, id],
    );
  };

  const removeCommitted = (id: number) => {
    onChange(value.filter((item) => item !== id));
  };

  const menu =
    open && menuPosition
      ? createPortal(
          <div
            ref={menuRef}
            id={listId}
            style={{
              position: "fixed",
              top: menuPosition.openUpward ? undefined : menuPosition.top,
              bottom: menuPosition.openUpward
                ? window.innerHeight - menuPosition.top
                : undefined,
              left: menuPosition.left,
              width: menuPosition.width,
              maxHeight: menuPosition.maxHeight,
              zIndex: 80,
            }}
            className="flex flex-col overflow-hidden rounded-xl border border-[rgba(148,163,184,0.28)] bg-(--surface-solid) shadow-(--shadow-card)"
          >
            <div className="flex shrink-0 items-center gap-2 border-b border-[rgba(148,163,184,0.18)] px-3 py-2">
              <Search size={14} className="text-(--text-muted)" />
              <input
                ref={inputRef}
                value={query}
                onChange={(event) => setQuery(event.target.value)}
                placeholder="Type a name or job title…"
                className="w-full bg-transparent text-sm outline-none placeholder:text-(--text-muted)"
              />
            </div>
            <ul className="min-h-0 flex-1 overflow-y-auto py-1">
              {filtered.length === 0 ? (
                <li className="px-3 py-3 text-sm text-(--text-muted)">No matching staff.</li>
              ) : (
                filtered.map((member) => {
                  const checked = draft.includes(member.id);

                  return (
                    <li key={member.id}>
                      <button
                        type="button"
                        onClick={() => toggle(member.id)}
                        className={cn(
                          "flex w-full items-start gap-3 px-3 py-2 text-left text-sm transition hover:bg-(--surface-muted)",
                          checked && "bg-[rgba(212,174,43,0.12)]",
                        )}
                      >
                        <span
                          className={cn(
                            "mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded border",
                            checked
                              ? "border-(--color-primary) bg-(--color-primary) text-white"
                              : "border-[rgba(148,163,184,0.45)]",
                          )}
                        >
                          {checked ? <Check size={12} /> : null}
                        </span>
                        <span className="min-w-0">
                          <span className="block font-medium text-foreground">{member.name}</span>
                          <span className="block truncate text-xs text-(--text-muted)">
                            {[member.job_title, member.email].filter(Boolean).join(" · ")}
                          </span>
                        </span>
                      </button>
                    </li>
                  );
                })
              )}
            </ul>
            <div className="flex shrink-0 flex-wrap items-center justify-between gap-2 border-t border-[rgba(148,163,184,0.18)] bg-(--surface-solid) px-3 py-2.5">
              <div className="flex items-center gap-3 text-xs text-(--text-muted)">
                <span>{draft.length} selected</span>
                {draft.length > 0 ? (
                  <button
                    type="button"
                    className="font-medium text-(--color-primary) hover:underline"
                    onClick={() => setDraft([])}
                  >
                    Clear all
                  </button>
                ) : null}
              </div>
              <div className="flex items-center gap-2">
                <Button type="button" size="sm" variant="outline" onClick={closeWithoutSaving}>
                  Cancel
                </Button>
                <Button type="button" size="sm" onClick={assignSelection}>
                  Assign
                </Button>
              </div>
            </div>
          </div>,
          document.body,
        )
      : null;

  return (
    <div ref={rootRef} className="relative">
      <button
        ref={triggerRef}
        type="button"
        disabled={disabled}
        aria-expanded={open}
        aria-controls={listId}
        onClick={() => {
          if (open) {
            closeWithoutSaving();
            return;
          }
          setDraft(value);
          setOpen(true);
        }}
        className={cn(
          "flex min-h-11 w-full items-center justify-between gap-2 rounded-xl border border-[rgba(148,163,184,0.25)] bg-(--surface-solid) px-3 py-2 text-left text-sm transition",
          "hover:border-[rgba(148,163,184,0.45)] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-(--color-accent)",
          disabled && "cursor-not-allowed opacity-60",
        )}
      >
        <div className="flex min-w-0 flex-1 flex-wrap gap-1.5">
          {selected.length === 0 ? (
            <span className="text-(--text-muted)">{placeholder}</span>
          ) : (
            selected.map((member) => (
              <span
                key={member.id}
                className="inline-flex max-w-full items-center gap-1 rounded-lg bg-(--surface-muted) px-2 py-0.5 text-xs font-medium text-foreground"
              >
                <span className="truncate">{member.name}</span>
                {!open ? (
                  <span
                    role="button"
                    tabIndex={0}
                    aria-label={`Remove ${member.name}`}
                    className="rounded p-0.5 hover:bg-[rgba(148,163,184,0.25)]"
                    onClick={(event) => {
                      event.stopPropagation();
                      removeCommitted(member.id);
                    }}
                    onKeyDown={(event) => {
                      if (event.key === "Enter" || event.key === " ") {
                        event.preventDefault();
                        event.stopPropagation();
                        removeCommitted(member.id);
                      }
                    }}
                  >
                    <X size={12} />
                  </span>
                ) : null}
              </span>
            ))
          )}
        </div>
        <ChevronsUpDown size={16} className="shrink-0 text-(--text-muted)" />
      </button>
      {menu}
    </div>
  );
}
