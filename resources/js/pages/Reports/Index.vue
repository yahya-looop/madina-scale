<script setup lang="ts">
import { computed, defineComponent, h, onMounted, ref, type PropType } from "vue";
import ErpLayout from "@/layouts/ErpLayout.vue";
import { Head, usePage } from "@inertiajs/vue3";

type LocationOption = {
  id: number;
  name: string;
  access_level: string;
};

type Row = Record<string, string | number | null>;

type Report = {
  meta: {
    from: string;
    to: string;
    location_id: number | null;
    currency: string;
    generated_at: string;
  };
  kpis: {
    orders: number;
    gross_sales: number;
    average_order_value: number;
    cancelled_orders: number;
    posted_income: number;
    posted_expense: number;
    net_operating_result: number;
    returns_count: number;
    refund_total: number;
  };
  daily_sales: Row[];
  top_products: Row[];
  order_status: Row[];
  finance: Row[];
  inventory: Row[];
  delivery: Row[];
  returns: Row[];
  customers: Row[];
};

const props = defineProps<{
  locations: LocationOption[];
  canExport: boolean;
  defaultFrom: string;
  defaultTo: string;
}>();

// Authenticated user information for ErpLayout.
const page = usePage();

const authUser = computed(() => {
  const pageProps = page.props as {
    auth?: {
      user?: {
        name?: string | null;
        email?: string | null;
      } | null;
    };
  };

  return pageProps.auth?.user;
});

const userName = computed(() => authUser.value?.name ?? "");
const userEmail = computed(() => authUser.value?.email ?? "");

const from = ref(props.defaultFrom);
const to = ref(props.defaultTo);
const locationId = ref("");
const activeTab = ref("overview");
const report = ref<Report | null>(null);
const loading = ref(false);
const error = ref("");

const tabs = [
  { key: "overview", label: "Overview" },
  { key: "sales", label: "Sales" },
  { key: "finance", label: "Finance" },
  { key: "inventory", label: "Inventory" },
  { key: "delivery", label: "Delivery" },
  { key: "customers", label: "Customers" },
];

const money = (value: unknown): string =>
  new Intl.NumberFormat("en-BD", {
    style: "currency",
    currency: "BDT",
    maximumFractionDigits: 2,
  }).format(Number(value ?? 0));

const number = (value: unknown): string =>
  new Intl.NumberFormat("en-BD", {
    maximumFractionDigits: 2,
  }).format(Number(value ?? 0));

const dailyMax = computed(() =>
  Math.max(
    1,
    ...(report.value?.daily_sales.map((row) => Number(row.sales ?? 0)) ?? [1]),
  ),
);

const dailyBars = computed(() => report.value?.daily_sales.slice(-31) ?? []);

const shipmentCount = computed(() =>
  (report.value?.delivery ?? []).reduce(
    (total, row) => total + Number(row.shipments ?? 0),
    0,
  ),
);

const dateLabel = (value: unknown): string => {
  const date = new Date(`${String(value)}T00:00:00`);

  if (Number.isNaN(date.getTime())) {
    return String(value ?? "");
  }

  return date.toLocaleDateString("en-GB", {
    day: "2-digit",
    month: "short",
  });
};

async function load(): Promise<void> {
  if (!from.value || !to.value) {
    error.value = "Please select both a start date and an end date.";
    return;
  }

  if (to.value < from.value) {
    error.value = "The end date cannot be earlier than the start date.";
    return;
  }

  loading.value = true;
  error.value = "";

  const params = new URLSearchParams({
    from: from.value,
    to: to.value,
  });

  if (locationId.value) {
    params.set("location_id", locationId.value);
  }

  try {
    const response = await fetch(`/api/reports/data?${params.toString()}`, {
      method: "GET",
      credentials: "same-origin",
      headers: {
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest",
      },
    });

    const data = await response.json().catch(() => null);

    if (!response.ok) {
      throw new Error(
        data?.message ??
          `Unable to load reports. Server returned status ${response.status}.`,
      );
    }

    if (!data || typeof data !== "object") {
      throw new Error("The server returned an invalid report response.");
    }

    report.value = data as Report;
  } catch (exception: unknown) {
    error.value =
      exception instanceof Error
        ? exception.message
        : "Unable to load reports. Check your date range and location access.";
  } finally {
    loading.value = false;
  }
}

function exportCsv(dataset: string): void {
  if (!props.canExport) {
    return;
  }

  const params = new URLSearchParams({
    from: from.value,
    to: to.value,
    dataset,
  });

  if (locationId.value) {
    params.set("location_id", locationId.value);
  }

  window.location.href = `/api/reports/export?${params.toString()}`;
}

function currentExportDataset(): string {
  const datasets: Record<string, string> = {
    overview: "daily_sales",
    sales: "top_products",
    finance: "finance",
    inventory: "inventory",
    delivery: "delivery",
    customers: "customers",
  };

  return datasets[activeTab.value] ?? "daily_sales";
}

function setRange(range: "7d" | "30d" | "month" | "90d"): void {
  const end = new Date();
  const start = new Date();

  if (range === "month") {
    start.setDate(1);
  } else {
    const days = range === "7d" ? 6 : range === "30d" ? 29 : 89;
    start.setDate(start.getDate() - days);
  }

  const localDate = (date: Date): string => {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, "0");
    const day = String(date.getDate()).padStart(2, "0");

    return `${year}-${month}-${day}`;
  };

  from.value = localDate(start);
  to.value = localDate(end);

  void load();
}

// Reusable table component for report datasets.
const DataTable = defineComponent({
  name: "ReportDataTable",

  props: {
    rows: {
      type: Array as PropType<Row[]>,
      required: true,
    },
    money: {
      type: Function as PropType<(value: unknown) => string>,
      required: false,
    },
  },

  setup(tableProps) {
    return () => {
      const rows = tableProps.rows;

      if (!rows.length) {
        return h(
          "p",
          {
            class: "py-8 text-center text-sm text-slate-400",
          },
          "No records for this period.",
        );
      }

      const keys = Object.keys(rows[0]);

      const moneyColumns = [
        "sales",
        "debit",
        "credit",
        "net",
        "refund",
        "cod",
        "refund_total",
      ];

      return h("div", { class: "mt-4 overflow-x-auto" }, [
        h("table", { class: "w-full text-left text-sm" }, [
          h("thead", { class: "text-xs uppercase text-slate-500" }, [
            h(
              "tr",
              keys.map((key) =>
                h(
                  "th",
                  {
                    class: "whitespace-nowrap py-2 pr-4",
                  },
                  key.replaceAll("_", " "),
                ),
              ),
            ),
          ]),

          h(
            "tbody",
            rows.map((row, index) =>
              h(
                "tr",
                {
                  class:
                    "border-t border-slate-100 dark:border-slate-800",
                  key: index,
                },
                keys.map((key) => {
                  const value = row[key];

                  const formattedValue =
                    typeof value === "number" &&
                    moneyColumns.includes(key) &&
                    tableProps.money
                      ? tableProps.money(value)
                      : String(value ?? "—");

                  return h(
                    "td",
                    {
                      class: "whitespace-nowrap py-3 pr-4",
                    },
                    formattedValue,
                  );
                }),
              ),
            ),
          ),
        ]),
      ]);
    };
  },
});

onMounted(() => {
  void load();
});
</script>

<template>
  <Head title="Reports & Analytics" />

  <ErpLayout :user-name="userName" :user-email="userEmail">
    <div
      class="min-h-screen bg-slate-50/70 px-4 py-6 dark:bg-slate-950 sm:px-6 lg:px-8"
    >
      <div class="mx-auto max-w-[1600px] space-y-6">
        <!-- Header -->
        <header
          class="relative overflow-hidden rounded-3xl bg-slate-950 px-6 py-7 text-white shadow-xl sm:px-8"
        >
          <div
            class="pointer-events-none absolute -right-16 -top-28 h-80 w-80 rounded-full bg-cyan-400/20 blur-3xl"
          ></div>

          <div
            class="pointer-events-none absolute right-48 top-20 h-48 w-48 rounded-full bg-indigo-500/20 blur-3xl"
          ></div>

          <div
            class="relative flex flex-col justify-between gap-5 lg:flex-row lg:items-end"
          >
            <div>
              <p
                class="text-xs font-bold uppercase tracking-[0.24em] text-cyan-300"
              >
                Intelligence workspace
              </p>

              <h1 class="mt-2 text-3xl font-bold tracking-tight sm:text-4xl">
                Reports & Analytics
              </h1>

              <p class="mt-2 max-w-2xl text-sm text-slate-300">
                A clear view of commercial performance, financial outcomes and
                operational health.
              </p>
            </div>

            <div class="flex flex-wrap gap-2">
              <button
                v-for="range in [
                  { key: '7d', label: '7 days' },
                  { key: '30d', label: '30 days' },
                  { key: 'month', label: 'This month' },
                  { key: '90d', label: '90 days' },
                ]"
                :key="range.key"
                type="button"
                @click="setRange(range.key as '7d' | '30d' | 'month' | '90d')"
                class="rounded-xl border border-white/15 bg-white/10 px-3 py-2 text-xs font-semibold text-white transition hover:bg-white/20"
              >
                {{ range.label }}
              </button>
            </div>
          </div>
        </header>

        <!-- Filters -->
        <section
          class="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 md:flex-row md:items-end"
        >
          <label class="flex-1 text-xs font-semibold text-slate-500">
            From date
            <input
              v-model="from"
              type="date"
              class="mt-1.5 w-full rounded-xl border-slate-200 text-sm dark:border-slate-700 dark:bg-slate-950"
            />
          </label>

          <label class="flex-1 text-xs font-semibold text-slate-500">
            To date
            <input
              v-model="to"
              type="date"
              :min="from"
              class="mt-1.5 w-full rounded-xl border-slate-200 text-sm dark:border-slate-700 dark:bg-slate-950"
            />
          </label>

          <label class="flex-1 text-xs font-semibold text-slate-500">
            Location
            <select
              v-model="locationId"
              class="mt-1.5 w-full rounded-xl border-slate-200 text-sm dark:border-slate-700 dark:bg-slate-950"
            >
              <option value="">All accessible locations</option>
              <option
                v-for="location in locations"
                :key="location.id"
                :value="String(location.id)"
              >
                {{ location.name }}
              </option>
            </select>
          </label>

          <button
            type="button"
            @click="load"
            :disabled="loading || !from || !to || to < from"
            class="rounded-xl bg-blue-700 px-5 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-blue-800 disabled:opacity-50"
          >
            {{ loading ? "Loading…" : "Apply filters" }}
          </button>

          <button
            v-if="canExport"
            type="button"
            @click="exportCsv(currentExportDataset())"
            :disabled="!report || loading"
            class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
          >
            Export CSV
          </button>
        </section>

        <!-- Error -->
        <div
          v-if="error"
          class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700 dark:border-rose-900 dark:bg-rose-950/30 dark:text-rose-300"
        >
          {{ error }}
        </div>

        <!-- Report content -->
        <div v-if="report" class="space-y-5">
          <!-- Tabs -->
          <nav
            class="flex gap-1 overflow-x-auto rounded-2xl border border-slate-200 bg-white p-1.5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
          >
            <button
              v-for="tab in tabs"
              :key="tab.key"
              type="button"
              @click="activeTab = tab.key"
              class="whitespace-nowrap rounded-xl px-4 py-2.5 text-sm font-semibold transition"
              :class="
                activeTab === tab.key
                  ? 'bg-slate-950 text-white shadow dark:bg-cyan-500 dark:text-slate-950'
                  : 'text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800'
              "
            >
              {{ tab.label }}
            </button>
          </nav>

          <!-- OVERVIEW -->
          <template v-if="activeTab === 'overview'">
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
              <article
                v-for="card in [
                  {
                    label: 'Gross sales value',
                    value: money(report.kpis.gross_sales),
                    hint: `${number(report.kpis.orders)} non-cancelled orders`,
                  },
                  {
                    label: 'Average order value',
                    value: money(report.kpis.average_order_value),
                    hint: 'Per non-cancelled order',
                  },
                  {
                    label: 'Posted income',
                    value: money(report.kpis.posted_income),
                    hint: 'From posted journal entries',
                  },
                  {
                    label: 'Net operating result',
                    value: money(report.kpis.net_operating_result),
                    hint: `Expenses ${money(report.kpis.posted_expense)}`,
                  },
                ]"
                :key="card.label"
                class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
              >
                <p
                  class="text-xs font-semibold uppercase tracking-wider text-slate-500"
                >
                  {{ card.label }}
                </p>

                <p
                  class="mt-3 text-2xl font-bold tracking-tight text-slate-900 dark:text-white"
                >
                  {{ card.value }}
                </p>

                <p class="mt-2 text-xs text-slate-500">
                  {{ card.hint }}
                </p>
              </article>
            </div>

            <div class="grid gap-5 xl:grid-cols-[1.6fr_1fr]">
              <!-- Sales trend -->
              <section
                class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
              >
                <div class="flex items-center justify-between">
                  <div>
                    <h2 class="font-bold text-slate-900 dark:text-white">
                      Sales momentum
                    </h2>
                    <p class="mt-1 text-xs text-slate-500">
                      Order value by day · BDT
                    </p>
                  </div>

                  <span
                    class="rounded-full bg-blue-50 px-3 py-1 text-xs font-bold text-blue-700 dark:bg-blue-950 dark:text-blue-300"
                  >
                    Daily trend
                  </span>
                </div>

                <div
                  v-if="dailyBars.length"
                  class="mt-6 flex h-56 items-end gap-1.5 overflow-x-auto border-b border-slate-100 pb-2 dark:border-slate-800"
                >
                  <div
                    v-for="bar in dailyBars"
                    :key="String(bar.date)"
                    class="group flex min-w-5 flex-1 flex-col items-center justify-end gap-2"
                  >
                    <div
                      class="w-full rounded-t-md bg-gradient-to-t from-blue-700 to-cyan-400 transition hover:brightness-110"
                      :style="{
                        height: `${Math.max(3, (Number(bar.sales ?? 0) / dailyMax) * 175)}px`,
                      }"
                      :title="`${bar.date}: ${money(bar.sales)}`"
                    ></div>

                    <span class="text-[9px] text-slate-400">
                      {{ dateLabel(bar.date) }}
                    </span>
                  </div>
                </div>

                <div
                  v-else
                  class="grid h-56 place-items-center text-sm text-slate-400"
                >
                  No sales activity in this period.
                </div>
              </section>

              <!-- Order health -->
              <section
                class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
              >
                <h2 class="font-bold text-slate-900 dark:text-white">
                  Order health
                </h2>

                <p class="mt-1 text-xs text-slate-500">
                  Order lifecycle distribution
                </p>

                <div class="mt-5 space-y-4">
                  <div
                    v-for="item in report.order_status"
                    :key="String(item.status)"
                  >
                    <div class="mb-1.5 flex justify-between text-xs">
                      <span
                        class="font-semibold capitalize text-slate-700 dark:text-slate-200"
                      >
                        {{ item.status }}
                      </span>
                      <span class="text-slate-500">
                        {{ number(item.orders) }}
                      </span>
                    </div>

                    <div
                      class="h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800"
                    >
                      <div
                        class="h-full rounded-full bg-gradient-to-r from-blue-600 to-cyan-400"
                        :style="{
                          width: `${Math.min(
                            100,
                            (Number(item.orders ?? 0) /
                              Math.max(
                                1,
                                report.kpis.orders +
                                  report.kpis.cancelled_orders,
                              )) *
                              100,
                          )}%`,
                        }"
                      ></div>
                    </div>
                  </div>

                  <p
                    v-if="!report.order_status.length"
                    class="text-sm text-slate-400"
                  >
                    No orders found.
                  </p>
                </div>

                <div
                  class="mt-6 grid grid-cols-2 gap-3 border-t border-slate-100 pt-4 dark:border-slate-800"
                >
                  <div>
                    <p class="text-xs text-slate-500">Cancelled orders</p>
                    <p class="mt-1 text-xl font-bold">
                      {{ number(report.kpis.cancelled_orders) }}
                    </p>
                  </div>

                  <div>
                    <p class="text-xs text-slate-500">Refund total</p>
                    <p class="mt-1 text-xl font-bold">
                      {{ money(report.kpis.refund_total) }}
                    </p>
                  </div>
                </div>
              </section>
            </div>

            <div class="grid gap-5 xl:grid-cols-2">
              <!-- Top products -->
              <section
                class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
              >
                <h2 class="font-bold">Top products</h2>

                <div
                  class="mt-4 divide-y divide-slate-100 dark:divide-slate-800"
                >
                  <div
                    v-for="(row, index) in report.top_products.slice(0, 5)"
                    :key="index"
                    class="flex items-center justify-between gap-4 py-3"
                  >
                    <div class="min-w-0">
                      <p class="truncate text-sm font-semibold">
                        {{ row.product }}
                      </p>
                      <p class="text-xs text-slate-500">
                        {{ row.sku || "No SKU" }} · Qty
                        {{ number(row.quantity) }}
                      </p>
                    </div>

                    <p class="shrink-0 text-sm font-bold">
                      {{ money(row.sales) }}
                    </p>
                  </div>

                  <p
                    v-if="!report.top_products.length"
                    class="py-8 text-center text-sm text-slate-400"
                  >
                    No product sales.
                  </p>
                </div>
              </section>

              <!-- Operational signals -->
              <section
                class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
              >
                <h2 class="font-bold">Operational signals</h2>

                <div class="mt-4 grid grid-cols-2 gap-3">
                  <div class="rounded-xl bg-slate-50 p-4 dark:bg-slate-800">
                    <p class="text-xs text-slate-500">Returns processed</p>
                    <p class="mt-1 text-2xl font-bold">
                      {{ number(report.kpis.returns_count) }}
                    </p>
                  </div>

                  <div class="rounded-xl bg-slate-50 p-4 dark:bg-slate-800">
                    <p class="text-xs text-slate-500">Refund value</p>
                    <p class="mt-1 text-xl font-bold">
                      {{ money(report.kpis.refund_total) }}
                    </p>
                  </div>

                  <div class="rounded-xl bg-slate-50 p-4 dark:bg-slate-800">
                    <p class="text-xs text-slate-500">Shipments</p>
                    <p class="mt-1 text-2xl font-bold">
                      {{ number(shipmentCount) }}
                    </p>
                  </div>

                  <div class="rounded-xl bg-slate-50 p-4 dark:bg-slate-800">
                    <p class="text-xs text-slate-500">Inventory SKUs</p>
                    <p class="mt-1 text-2xl font-bold">
                      {{ number(report.inventory.length) }}
                    </p>
                  </div>
                </div>
              </section>
            </div>
          </template>

          <!-- SALES -->
          <template v-else-if="activeTab === 'sales'">
            <div class="grid gap-5 xl:grid-cols-2">
              <section
                class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900"
              >
                <h2 class="font-bold">Daily sales</h2>

                <div class="mt-4 overflow-x-auto">
                  <table class="w-full text-left text-sm">
                    <thead class="text-xs uppercase text-slate-500">
                      <tr>
                        <th class="py-2">Date</th>
                        <th>Orders</th>
                        <th class="text-right">Sales value</th>
                      </tr>
                    </thead>

                    <tbody>
                      <tr
                        v-for="row in report.daily_sales"
                        :key="String(row.date)"
                        class="border-t border-slate-100 dark:border-slate-800"
                      >
                        <td class="py-3">{{ row.date }}</td>
                        <td>{{ row.orders }}</td>
                        <td class="text-right font-semibold">
                          {{ money(row.sales) }}
                        </td>
                      </tr>
                    </tbody>
                  </table>

                  <p
                    v-if="!report.daily_sales.length"
                    class="py-8 text-center text-sm text-slate-400"
                  >
                    No daily sales records.
                  </p>
                </div>
              </section>

              <section
                class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900"
              >
                <h2 class="font-bold">Best-performing products</h2>
                <DataTable :rows="report.top_products" :money="money" />
              </section>
            </div>

            <section
              class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900"
            >
              <h2 class="font-bold">Order status breakdown</h2>
              <DataTable :rows="report.order_status" />
            </section>
          </template>

          <!-- FINANCE -->
          <template v-else-if="activeTab === 'finance'">
            <div class="grid gap-4 sm:grid-cols-3">
              <div class="rounded-2xl bg-emerald-50 p-5 dark:bg-emerald-950/30">
                <p class="text-xs text-emerald-700 dark:text-emerald-300">
                  Posted income
                </p>
                <p class="mt-2 text-2xl font-bold">
                  {{ money(report.kpis.posted_income) }}
                </p>
              </div>

              <div class="rounded-2xl bg-rose-50 p-5 dark:bg-rose-950/30">
                <p class="text-xs text-rose-700 dark:text-rose-300">
                  Posted expense
                </p>
                <p class="mt-2 text-2xl font-bold">
                  {{ money(report.kpis.posted_expense) }}
                </p>
              </div>

              <div class="rounded-2xl bg-blue-50 p-5 dark:bg-blue-950/30">
                <p class="text-xs text-blue-700 dark:text-blue-300">
                  Net operating result
                </p>
                <p class="mt-2 text-2xl font-bold">
                  {{ money(report.kpis.net_operating_result) }}
                </p>
              </div>
            </div>

            <section
              class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900"
            >
              <h2 class="font-bold">Posted ledger by account</h2>
              <DataTable :rows="report.finance" :money="money" />
            </section>
          </template>

          <!-- INVENTORY -->
          <template v-else-if="activeTab === 'inventory'">
            <section
              class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900"
            >
              <h2 class="font-bold">Inventory position</h2>

              <p class="mt-1 text-xs text-slate-500">
                On-hand and reserved quantities across accessible locations.
                Values are quantities, not inventory valuation.
              </p>

              <DataTable :rows="report.inventory" />
            </section>
          </template>

          <!-- DELIVERY -->
          <template v-else-if="activeTab === 'delivery'">
            <section
              class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900"
            >
              <h2 class="font-bold">Courier performance</h2>

              <p class="mt-1 text-xs text-slate-500">
                COD is operational collection value, not recognized cash or
                revenue.
              </p>

              <DataTable :rows="report.delivery" :money="money" />
            </section>
          </template>

          <!-- CUSTOMERS -->
          <template v-else-if="activeTab === 'customers'">
            <div class="grid gap-5 xl:grid-cols-2">
              <section
                class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900"
              >
                <h2 class="font-bold">Top customers by order value</h2>
                <DataTable :rows="report.customers" :money="money" />
              </section>

              <section
                class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900"
              >
                <h2 class="font-bold">Return and refund summary</h2>
                <DataTable :rows="report.returns" :money="money" />
              </section>
            </div>
          </template>

          <!-- Footer -->
          <footer
            class="flex flex-wrap items-center justify-between gap-2 text-xs text-slate-400"
          >
            <span>
              Generated
              {{ new Date(report.meta.generated_at).toLocaleString() }}
              · Data scoped to your accessible locations.
            </span>

            <span>Reporting range is limited to 367 days.</span>
          </footer>
        </div>

        <!-- Loading state -->
        <div
          v-else-if="loading"
          class="rounded-2xl border border-slate-200 bg-white p-16 text-center text-sm text-slate-500 dark:border-slate-800 dark:bg-slate-900"
        >
          Preparing your reports…
        </div>

        <!-- Empty state -->
        <div
          v-else-if="!error"
          class="rounded-2xl border border-slate-200 bg-white p-16 text-center text-sm text-slate-500 dark:border-slate-800 dark:bg-slate-900"
        >
          No report data available.
        </div>
      </div>
    </div>
  </ErpLayout>
</template>
