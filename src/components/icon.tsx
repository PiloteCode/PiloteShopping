import {
  Box,
  Briefcase,
  Cpu,
  Gamepad2,
  Key,
  KeyRound,
  Package,
  Palette,
  Play,
  RefreshCw,
  ShieldCheck,
  UserRound,
  Wallet,
  Webhook,
  Bell,
  Tag,
  type LucideProps,
} from "lucide-react";

const MAP: Record<string, React.ComponentType<LucideProps>> = {
  Gamepad2,
  Play,
  Briefcase,
  Palette,
  ShieldCheck,
  Cpu,
  KeyRound,
  UserRound,
  RefreshCw,
  Key,
  Webhook,
  Package,
  Wallet,
  Bell,
  Tag,
};

export function DynamicIcon({ name, ...props }: { name?: string | null } & Omit<LucideProps, "name">) {
  const Comp = (name && MAP[name]) || Box;
  return <Comp {...props} />;
}
