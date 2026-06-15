import * as React from "react";
import { cn } from "@/lib/utils";

/** Native select styled to match the design system inputs. */
const AdminSelect = React.forwardRef<HTMLSelectElement, React.ComponentProps<"select">>(
  ({ className, children, ...props }, ref) => (
    <select
      ref={ref}
      className={cn(
        "flex h-10 w-full cursor-pointer rounded-lg border border-input bg-background/60 px-3 py-2 text-sm shadow-sm outline-none transition-colors focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/60 disabled:cursor-not-allowed disabled:opacity-50",
        className,
      )}
      {...props}
    >
      {children}
    </select>
  ),
);
AdminSelect.displayName = "AdminSelect";

export { AdminSelect };
