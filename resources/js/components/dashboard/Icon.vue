<script setup lang="ts">
import { computed } from "vue";
import {
  Activity,
  Archive,
  ArrowLeft,
  ArrowRight,
  BarChart3,
  Bell,
  BriefcaseBusiness,
  CalendarDays,
  Check,
  CheckCircle2,
  ChevronDown,
  ChevronLeft,
  ChevronRight,
  CircleHelp,
  Clock3,
  CreditCard,
  Database,
  Edit3,
  Eye,
  FileBarChart,
  FileText,
  Folder,
  Headphones,
  Info,
  KeyRound,
  LayoutDashboard,
  Lock,
  LogIn,
  Mail,
  Maximize2,
  Megaphone,
  Menu,
  MessageCircle,
  MapPin,
  MoreHorizontal,
  Moon,
  Package,
  Pencil,
  Plus,
  RefreshCw,
  Search,
  Settings,
  Shield,
  ShieldCheck,
  ShoppingCart,
  Sun,
  Tag,
  Trash2,
  Truck,
  User,
  UserPlus,
  Users,
  X,
} from "lucide-vue-next";

type IconComponent = typeof LayoutDashboard;

const iconMap: Record<string, IconComponent> = {
  dashboard: LayoutDashboard,

  search: Search,
  plus: Plus,
  bell: Bell,

  calendar: CalendarDays,
  sun: Sun,
  moon: Moon,
  settings: Settings,

  "chevron-down": ChevronDown,
  "chevron-left": ChevronLeft,
  "chevron-right": ChevronRight,

  expand: Maximize2,
  menu: Menu,

  check: Check,
  "check-circle": CheckCircle2,
  info: Info,

  dots: MoreHorizontal,

  chat: MessageCircle,
  support: Headphones,

  users: Users,
  customers: Users,
  user: User,
  "user-plus": UserPlus,

  cart: ShoppingCart,
  orders: ShoppingCart,
  sales: ShoppingCart,

  products: Package,
  inventory: Package,

  wholesale: BriefcaseBusiness,
  pricing: Tag,

  shipping: Truck,
  payments: CreditCard,
  revenue: CreditCard,

  service: Clock3,

  marketing: Megaphone,
  analytics: BarChart3,
  conversion: Activity,

  'map-pin': MapPin,

  reports: FileBarChart,
  cms: FileText,
  seo: FileText,
  developer: FileText,

  lock: Lock,
  unlock: ShieldCheck,

  security: Shield,
  permissions: ShieldCheck,
  roles: ShieldCheck,

  login: LogIn,
  "last-login": LogIn,

  refresh: RefreshCw,

  edit: Pencil,
  pencil: Pencil,
  "edit-3": Edit3,

  view: Eye,
  eye: Eye,

  delete: Trash2,
  trash: Trash2,

  close: X,
  x: X,

  back: ArrowLeft,
  "arrow-left": ArrowLeft,
  "arrow-right": ArrowRight,

  save: CheckCircle2,

  email: Mail,
  mail: Mail,

  password: KeyRound,
  key: KeyRound,

  database: Database,
  folder: Folder,

  help: CircleHelp,
};

const props = withDefaults(
  defineProps<{
    name: string;
    size?: number;
    strokeWidth?: number;
  }>(),
  {
    size: 20,
    strokeWidth: 1.8,
  }
);

const icon = computed(() => iconMap[props.name] ?? CircleHelp);
</script>

<template>
  <component
    :is="icon"
    :size="size"
    :stroke-width="strokeWidth"
    aria-hidden="true"
    focusable="false"
  />
</template>
