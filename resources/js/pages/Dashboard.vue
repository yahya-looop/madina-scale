<script setup lang="ts">
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import ErpLayout from '@/layouts/ErpLayout.vue';
import Icon from '@/components/dashboard/Icon.vue';
import KpiCard from '@/components/dashboard/KpiCard.vue';
import DonutChart from '@/components/dashboard/DonutChart.vue';
import {
    dashboardKpis,
    orderStatus,
    inventoryStatus,
    topProducts,
    recentOrders,
    tasks,
    trafficSources,
    marketingPerformance,
    chats,
} from '@/config/dashboard';

interface User {
    id: number;
    name: string;
    email: string;
}

interface Role {
    id: number;
    name: string;
    slug: string;
}

interface Capabilities {
    canManageUsers: boolean;
    canManageRoles: boolean;
    canViewReports: boolean;
}

interface Props {
    user: User;
    roles: Role[];
    capabilities: Capabilities;
}

const props = defineProps<Props>();

const activeAnalyticsTab = ref('Revenue');
const selectedRange = ref('Last 30 Days');
const rangeOpen = ref(false);
const chatOpen = ref(false);
const activeChatFilter = ref('All');
const chatSearch = ref('');
const completedTasks = ref<string[]>([]);

const analyticsTabs = [
    { label: 'Revenue', icon: 'revenue' },
    { label: 'Orders', icon: 'orders' },
    { label: 'Customers', icon: 'customers' },
    { label: 'Product Views', icon: 'products' },
    { label: 'Conversion', icon: 'conversion' },
];

const ranges = ['Last 7 Days', 'Last 30 Days', 'Last 90 Days', 'This Year'];

const quickActions = [
    { label: 'Add Product', icon: 'products', tone: 'green' },
    { label: 'Create Order', icon: 'orders', tone: 'blue' },
    { label: 'Add Customer', icon: 'customers', tone: 'purple' },
    { label: 'Create Quotation', icon: 'reports', tone: 'orange' },
    { label: 'Manage Inventory', icon: 'inventory', tone: 'blue' },
    { label: 'Send Notification', icon: 'bell', tone: 'green' },
];

const chartBars = [
    38, 55, 42, 68, 58, 76, 50, 62, 72, 48,
    82, 60, 68, 54, 75, 64, 86, 58, 69, 50,
    77, 66, 73, 61, 80, 72, 86, 70, 90, 78,
];

const chatItems = computed(() => {
    const query = chatSearch.value.trim().toLowerCase();

    return chats.filter((chat) => {
        const type = chat[3] === 'team' ? 'Team' : 'Customers';
        const filterMatch =
            activeChatFilter.value === 'All' ||
            (activeChatFilter.value === 'Customers' && type === 'Customers') ||
            (activeChatFilter.value === 'Team' && type === 'Team') ||
            (activeChatFilter.value === 'Unread' && ['Rahim Ahmed', 'Nusrat Jahan', 'Sales Team', 'Visitor #1283'].includes(chat[0]));

        const searchMatch =
            !query ||
            chat[0].toLowerCase().includes(query) ||
            chat[1].toLowerCase().includes(query);

        return filterMatch && searchMatch;
    });
});

const completedTaskCount = computed(() => completedTasks.value.length);

function toggleTask(task: string): void {
    if (completedTasks.value.includes(task)) {
        completedTasks.value = completedTasks.value.filter(
            (item) => item !== task,
        );
        return;
    }

    completedTasks.value = [...completedTasks.value, task];
}

function selectRange(range: string): void {
    selectedRange.value = range;
    rangeOpen.value = false;
}

function logout(): void {
    router.post('/logout');
}

function closeChat(): void {
    chatOpen.value = false;
}

function toggleChat(): void {
    chatOpen.value = !chatOpen.value;
}

function viewStore(): void {
    window.location.href = '/';
}
</script>

<template>
    <ErpLayout :user-name="user.name" :user-email="user.email">
        <div class="dashboard-page">
            <!-- Hero / welcome -->
            <section class="dashboard-hero">
                <div class="hero-copy">
                    <span class="hero-eyebrow">Good Morning,</span>
                    <h1>
                        {{ user.name }}
                        <span aria-hidden="true">👋</span>
                    </h1>
                    <p>Here's what's happening with Madina Scale today.</p>

                    <div class="hero-actions">
                        <button
                            type="button"
                            class="hero-primary"
                            @click="viewStore"
                        >
                            <Icon name="cart" :size="16" />
                            <span>Store</span>
                            <Icon name="chevron-right" :size="14" />
                        </button>

                        <button
                            type="button"
                            class="hero-action"
                            aria-label="Dashboard settings"
                        >
                            <Icon name="settings" :size="17" />
                        </button>

                        <button
                            type="button"
                            class="hero-action"
                            aria-label="Analytics"
                        >
                            <Icon name="analytics" :size="17" />
                        </button>

                        <button
                            type="button"
                            class="hero-action"
                            aria-label="Quick access"
                        >
                            <Icon name="dots" :size="18" />
                        </button>
                    </div>
                </div>

                <div class="hero-art" aria-hidden="true">
                    <div class="hero-art-glow"></div>

                    <!-- Inline default image/illustration. Replace later with a dynamic business image. -->
                    <svg
                        class="hero-scale-image"
                        viewBox="0 0 430 190"
                        role="img"
                    >
                        <defs>
                            <linearGradient
                                id="scaleBody"
                                x1="0"
                                y1="0"
                                x2="1"
                                y2="1"
                            >
                                <stop offset="0" stop-color="#1e2d3d" />
                                <stop offset="1" stop-color="#0a111a" />
                            </linearGradient>
                            <linearGradient
                                id="scaleMetal"
                                x1="0"
                                y1="0"
                                x2="1"
                                y2="1"
                            >
                                <stop offset="0" stop-color="#f8fbff" />
                                <stop offset="1" stop-color="#b8c5d3" />
                            </linearGradient>
                            <linearGradient
                                id="scaleScreen"
                                x1="0"
                                y1="0"
                                x2="1"
                                y2="1"
                            >
                                <stop offset="0" stop-color="#1b2937" />
                                <stop offset="1" stop-color="#071019" />
                            </linearGradient>
                            <filter id="softShadow">
                                <feDropShadow
                                    dx="0"
                                    dy="10"
                                    stdDeviation="10"
                                    flood-opacity=".18"
                                />
                            </filter>
                        </defs>

                        <ellipse
                            cx="218"
                            cy="168"
                            rx="158"
                            ry="15"
                            fill="#91a7bc"
                            opacity=".2"
                        />

                        <g filter="url(#softShadow)">
                            <path
                                d="M74 145h267l-28 19H102z"
                                fill="url(#scaleMetal)"
                            />
                            <path
                                d="M91 137h233l17 8H74z"
                                fill="#dce5ed"
                            />
                            <path
                                d="M116 121h183l25 16H91z"
                                fill="#f4f7fa"
                            />
                            <path
                                d="M149 73h112v55H149z"
                                fill="url(#scaleBody)"
                            />
                            <rect
                                x="162"
                                y="82"
                                width="86"
                                height="29"
                                rx="5"
                                fill="url(#scaleScreen)"
                            />
                            <rect
                                x="172"
                                y="89"
                                width="65"
                                height="15"
                                rx="3"
                                fill="#9cf2bf"
                                opacity=".95"
                            />
                            <text
                                x="204"
                                y="101"
                                text-anchor="middle"
                                font-family="Inter, Arial, sans-serif"
                                font-size="13"
                                font-weight="800"
                                fill="#123322"
                            >
                                0000
                            </text>
                            <circle cx="268" cy="95" r="4" fill="#0bb271" />
                            <path
                                d="M165 72 179 35h61l14 37z"
                                fill="url(#scaleBody)"
                            />
                            <rect
                                x="181"
                                y="40"
                                width="57"
                                height="26"
                                rx="5"
                                fill="#0c1621"
                            />
                            <rect
                                x="188"
                                y="46"
                                width="43"
                                height="12"
                                rx="3"
                                fill="#b7f6cc"
                                opacity=".95"
                            />
                            <path
                                d="M120 122h171"
                                stroke="#8fa0b0"
                                stroke-width="3"
                                stroke-linecap="round"
                            />
                            <path
                                d="M130 138v19M292 138v19"
                                stroke="#7e8e9f"
                                stroke-width="7"
                                stroke-linecap="round"
                            />
                        </g>
                    </svg>

                    <div class="hero-store-copy">
                        <strong>Reliable Scales</strong>
                        <span>For Every Industry</span>
                        <button type="button" @click="viewStore">
                            View Store
                            <Icon name="chevron-right" :size="13" />
                        </button>
                    </div>

                    <div class="hero-weather">
                        <span class="weather-icon">☀️</span>
                        <div>
                            <strong>28°C</strong>
                            <small>Sunny</small>
                        </div>
                    </div>
                </div>
            </section>

            <!-- KPI overview -->
            <section class="kpi-grid" aria-label="Key performance indicators">
                <KpiCard
                    v-for="kpi in dashboardKpis"
                    :key="kpi.label"
                    :kpi="kpi"
                />
            </section>

            <!-- Main analytics row -->
            <section class="dashboard-grid main-grid">
                <article class="panel analytics-panel">
                    <div class="panel-head analytics-head">
                        <div>
                            <div class="section-title-row">
                                <h2>Sales &amp; Order Analytics</h2>
                                <span class="live-dot">Live</span>
                            </div>

                            <div class="tabs" role="tablist">
                                <button
                                    v-for="tab in analyticsTabs"
                                    :key="tab.label"
                                    type="button"
                                    :class="{
                                        active:
                                            activeAnalyticsTab === tab.label,
                                    }"
                                    role="tab"
                                    :aria-selected="
                                        activeAnalyticsTab === tab.label
                                    "
                                    @click="
                                        activeAnalyticsTab = tab.label
                                    "
                                >
                                    <Icon :name="tab.icon" :size="13" />
                                    <span>{{ tab.label }}</span>
                                </button>
                            </div>
                        </div>

                        <div class="range-wrap">
                            <button
                                type="button"
                                class="range"
                                @click="rangeOpen = !rangeOpen"
                            >
                                {{ selectedRange }}
                                <Icon name="chevron-down" :size="13" />
                            </button>

                            <div v-if="rangeOpen" class="range-menu">
                                <button
                                    v-for="range in ranges"
                                    :key="range"
                                    type="button"
                                    :class="{
                                        selected: selectedRange === range,
                                    }"
                                    @click="selectRange(range)"
                                >
                                    {{ range }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="analytics-stats">
                        <div>
                            <strong>৳ 12,48,500</strong>
                            <span>Total Revenue <em>↗ 12.5%</em></span>
                        </div>
                        <div>
                            <strong>1,482</strong>
                            <span>Total Orders <em>↗ 8.2%</em></span>
                        </div>
                        <div>
                            <strong>3,842</strong>
                            <span>Product Views <em>↗ 18.7%</em></span>
                        </div>
                        <div>
                            <strong>892</strong>
                            <span>Total Customers <em>↗ 15.4%</em></span>
                        </div>
                    </div>

                    <div class="chart-area">
                        <div class="y-axis">
                            <span>৳ 500K</span>
                            <span>৳ 400K</span>
                            <span>৳ 300K</span>
                            <span>৳ 200K</span>
                            <span>৳ 100K</span>
                            <span>৳ 0</span>
                        </div>

                        <div class="chart-canvas">
                            <div class="grid-lines">
                                <i v-for="n in 6" :key="n"></i>
                            </div>

                            <div class="bars">
                                <span
                                    v-for="(bar, index) in chartBars"
                                    :key="index"
                                    :style="{ height: `${bar}%` }"
                                ></span>
                            </div>

                            <svg
                                viewBox="0 0 600 180"
                                preserveAspectRatio="none"
                                class="line-chart"
                                aria-hidden="true"
                            >
                                <polyline
                                    points="0,138 22,150 44,134 66,142 88,118 110,126 132,111 154,120 176,94 198,108 220,91 242,95 264,80 286,82 308,63 330,71 352,59 374,74 396,66 418,88 440,73 462,77 484,63 506,70 528,52 550,64 572,46 600,59"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="3"
                                    vector-effect="non-scaling-stroke"
                                />
                            </svg>

                            <div class="chart-tooltip">
                                <small>20 Oct 2024</small>
                                <span><i></i> Revenue <b>৳ 3,48,250</b></span>
                                <span><i class="tooltip-order"></i> Orders <b>412</b></span>
                            </div>

                            <div class="x-axis">
                                <span>Oct 1</span>
                                <span>Oct 5</span>
                                <span>Oct 10</span>
                                <span>Oct 15</span>
                                <span>Oct 20</span>
                                <span>Oct 25</span>
                                <span>Oct 30</span>
                            </div>
                        </div>
                    </div>
                </article>

                <article class="panel">
                    <div class="panel-head">
                        <div>
                            <h2>Order Status</h2>
                            <small>Distribution by current status</small>
                        </div>
                        <button type="button" class="panel-link">
                            View
                            <Icon name="chevron-right" :size="12" />
                        </button>
                    </div>

                    <div class="donut-row">
                        <DonutChart
                            total="1,482"
                            center-label="Total Orders"
                            :segments="
                                orderStatus.map((item) => ({
                                    value: item.value,
                                    tone: item.tone,
                                }))
                            "
                        />

                        <div class="legend-list">
                            <div
                                v-for="item in orderStatus"
                                :key="item.label"
                            >
                                <i :class="`legend-${item.tone}`"></i>
                                <span>{{ item.label }}</span>
                                <strong>{{ item.value }}</strong>
                                <small>({{ item.percent }})</small>
                            </div>
                        </div>
                    </div>
                </article>
            </section>

            <!-- Operational tables -->
            <section class="dashboard-grid secondary-grid">
                <article class="panel table-panel">
                    <div class="panel-head">
                        <div>
                            <h2>Top Selling Products</h2>
                            <small>Best performing products</small>
                        </div>
                        <button type="button" class="panel-link">
                            View All
                            <Icon name="chevron-right" :size="12" />
                        </button>
                    </div>

                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Product</th>
                                    <th>Sold</th>
                                    <th>Revenue</th>
                                    <th>Growth</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="(item, index) in topProducts"
                                    :key="item[0]"
                                >
                                    <td>
                                        <span class="rank">{{ index + 1 }}</span>
                                    </td>
                                    <td>
                                        <strong>{{ item[0] }}</strong>
                                    </td>
                                    <td>{{ item[1] }}</td>
                                    <td>{{ item[2] }}</td>
                                    <td class="positive">↗ {{ item[3] }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </article>

                <article class="panel table-panel">
                    <div class="panel-head">
                        <div>
                            <h2>Recent Orders</h2>
                            <small>Latest order activity</small>
                        </div>
                        <button type="button" class="panel-link">
                            View All
                            <Icon name="chevron-right" :size="12" />
                        </button>
                    </div>

                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Customer</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="item in recentOrders"
                                    :key="item[0]"
                                >
                                    <td>
                                        <button
                                            type="button"
                                            class="order-link"
                                        >
                                            {{ item[0] }}
                                        </button>
                                    </td>
                                    <td>{{ item[1] }}</td>
                                    <td>{{ item[2] }}</td>
                                    <td>
                                        <span
                                            class="status-pill"
                                            :class="
                                                item[3]
                                                    .toLowerCase()
                                                    .replace(/\s+/g, '-')
                                            "
                                        >
                                            {{ item[3] }}
                                        </span>
                                    </td>
                                    <td>{{ item[4] }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </article>

                <article class="panel">
                    <div class="panel-head">
                        <div>
                            <h2>Inventory Status</h2>
                            <small>Current stock health</small>
                        </div>
                        <button type="button" class="panel-link">
                            Manage
                            <Icon name="chevron-right" :size="12" />
                        </button>
                    </div>

                    <div class="donut-row inventory">
                        <DonutChart
                            total="1,245"
                            center-label="Total Products"
                            :segments="
                                inventoryStatus.map((item) => ({
                                    value: item.value,
                                    tone: item.tone,
                                }))
                            "
                        />

                        <div class="legend-list">
                            <div
                                v-for="item in inventoryStatus"
                                :key="item.label"
                            >
                                <i :class="`legend-${item.tone}`"></i>
                                <span>{{ item.label }}</span>
                                <strong>{{ item.value }}</strong>
                                <small>({{ item.percent }})</small>
                            </div>
                        </div>
                    </div>
                </article>
            </section>

            <!-- Marketing / traffic / customer analytics -->
            <section class="dashboard-grid lower-grid">
                <article class="panel">
                    <div class="panel-head">
                        <div>
                            <h2>Traffic Sources</h2>
                            <small>Last 30 days</small>
                        </div>
                        <button type="button" class="panel-link">
                            Details
                            <Icon name="chevron-right" :size="12" />
                        </button>
                    </div>

                    <div class="donut-row traffic">
                        <DonutChart
                            total="12,480"
                            center-label="Total Visits"
                            :segments="
                                trafficSources.map((item, index) => ({
                                    value: [32, 28, 18, 12, 9][index],
                                    tone: [
                                        'blue',
                                        'sky',
                                        'warning',
                                        'danger',
                                        'purple',
                                    ][index],
                                }))
                            "
                        />

                        <div class="legend-list">
                            <div
                                v-for="(item, index) in trafficSources"
                                :key="item[0]"
                            >
                                <i
                                    :class="`legend-${
                                        [
                                            'blue',
                                            'sky',
                                            'warning',
                                            'danger',
                                            'purple',
                                        ][index]
                                    }`"
                                ></i>
                                <span>{{ item[0] }}</span>
                                <strong>{{ item[1] }}</strong>
                            </div>
                        </div>
                    </div>
                </article>

                <article class="panel">
                    <div class="panel-head">
                        <div>
                            <h2>Marketing Performance</h2>
                            <small>Spend, revenue and ROAS</small>
                        </div>
                        <button type="button" class="panel-link">
                            Reports
                            <Icon name="chevron-right" :size="12" />
                        </button>
                    </div>

                    <div class="marketing-list">
                        <div
                            v-for="item in marketingPerformance"
                            :key="item[0]"
                        >
                            <span class="channel-icon">
                                <Icon name="marketing" :size="14" />
                            </span>
                            <strong>{{ item[0] }}</strong>
                            <span>{{ item[1] }}</span>
                            <span>{{ item[2] }}</span>
                            <b>{{ item[3] }}</b>
                        </div>
                    </div>
                </article>

                <article class="panel">
                    <div class="panel-head">
                        <div>
                            <h2>Customer Growth</h2>
                            <small>New customers · Last 30 days</small>
                        </div>
                        <span class="growth-chip">↗ 15.4%</span>
                    </div>

                    <div class="customer-growth">
                        <strong>
                            892
                            <span>New Customers</span>
                        </strong>

                        <svg
                            viewBox="0 0 400 120"
                            preserveAspectRatio="none"
                            aria-hidden="true"
                        >
                            <path
                                d="M0 105 35 98 65 86 92 90 120 76 148 80 175 62 205 68 235 48 265 55 290 34 320 45 350 30 400 10V120H0Z"
                                fill="currentColor"
                                opacity=".1"
                            />
                            <polyline
                                points="0,105 35,98 65,86 92,90 120,76 148,80 175,62 205,68 235,48 265,55 290,34 320,45 350,30 400,10"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="3"
                                vector-effect="non-scaling-stroke"
                            />
                        </svg>

                        <div class="x-axis">
                            <span>Oct 1</span>
                            <span>Oct 10</span>
                            <span>Oct 20</span>
                            <span>Oct 30</span>
                        </div>
                    </div>
                </article>
            </section>

            <!-- Desktop right rail -->
            <aside class="dashboard-right-rail">
                <section class="quick-card">
                    <div class="panel-head">
                        <div>
                            <h2>Quick Actions</h2>
                            <small>Common tasks</small>
                        </div>
                        <button type="button" class="panel-link">Customize</button>
                    </div>

                    <div class="quick-grid">
                        <button
                            v-for="action in quickActions"
                            :key="action.label"
                            type="button"
                        >
                            <span
                                class="quick-icon"
                                :class="`quick-${action.tone}`"
                            >
                                <Icon :name="action.icon" :size="16" />
                            </span>
                            <span>{{ action.label }}</span>
                        </button>
                    </div>
                </section>

                <section class="tasks-card">
                    <div class="panel-head">
                        <div>
                            <h2>Today's Tasks</h2>
                            <small>{{ completedTaskCount }} completed</small>
                        </div>
                        <button type="button" class="panel-link">
                            View All
                        </button>
                    </div>

                    <label
                        v-for="task in tasks"
                        :key="task[0]"
                        class="task-row"
                        :class="{
                            completed: completedTasks.includes(task[0]),
                        }"
                    >
                        <input
                            type="checkbox"
                            :checked="completedTasks.includes(task[0])"
                            @change="toggleTask(task[0])"
                        />
                        <span>{{ task[0] }}</span>
                        <small>{{ task[1] }}</small>
                    </label>
                </section>
            </aside>

            <!-- Floating chat entry point -->
            <button
                class="chat-fab"
                type="button"
                aria-label="Open Chats and Support"
                :aria-expanded="chatOpen"
                @click="toggleChat"
            >
                <span class="chat-fab-icon">
                    <Icon name="chat" :size="23" />
                </span>
                <b>8</b>
            </button>

            <!-- Chats / Support modal -->
            <Transition name="chat-pop">
                <section
                    v-if="chatOpen"
                    class="chat-modal"
                    role="dialog"
                    aria-modal="false"
                    aria-label="Chats and Support"
                >
                    <div class="chat-modal-head">
                        <div class="chat-title">
                            <span class="chat-status"></span>
                            <div>
                                <strong>Chats &amp; Support</strong>
                                <small>Customer and team conversations</small>
                            </div>
                        </div>

                        <div class="chat-head-actions">
                            <button
                                type="button"
                                aria-label="Open chat center"
                                title="Open chat center"
                            >
                                <Icon name="expand" :size="16" />
                            </button>
                            <button
                                type="button"
                                aria-label="Close chats"
                                title="Close"
                                @click="closeChat"
                            >
                                <Icon name="chevron-down" :size="17" />
                            </button>
                        </div>
                    </div>

                    <div class="chat-tabs">
                        <button
                            v-for="filter in ['All', 'Customers', 'Team', 'Unread']"
                            :key="filter"
                            type="button"
                            :class="{ active: activeChatFilter === filter }"
                            @click="activeChatFilter = filter"
                        >
                            {{ filter }}
                            <span v-if="filter === 'All'">18</span>
                            <span v-else-if="filter === 'Customers'">12</span>
                            <span v-else-if="filter === 'Team'">6</span>
                            <span v-else>8</span>
                        </button>
                    </div>

                    <label class="chat-search">
                        <Icon name="search" :size="15" />
                        <input
                            v-model="chatSearch"
                            type="search"
                            placeholder="Search conversations..."
                            aria-label="Search conversations"
                        />
                    </label>

                    <div class="chat-list">
                        <button
                            v-for="chat in chatItems"
                            :key="`${chat[0]}-${chat[2]}`"
                            type="button"
                            class="chat-item"
                        >
                            <span class="chat-avatar">
                                {{ chat[0].slice(0, 1).toUpperCase() }}
                                <i
                                    v-if="chat[3] === 'customer'"
                                    class="presence"
                                ></i>
                            </span>

                            <span class="chat-item-copy">
                                <strong>{{ chat[0] }}</strong>
                                <small>{{ chat[1] }}</small>
                            </span>

                            <time>{{ chat[2] }}</time>
                        </button>

                        <div v-if="chatItems.length === 0" class="empty-chat">
                            <Icon name="chat" :size="22" />
                            <strong>No conversations found</strong>
                            <span>Try another search or filter.</span>
                        </div>
                    </div>

                    <button type="button" class="open-chat-center">
                        Open Chat Center
                        <Icon name="chevron-right" :size="15" />
                    </button>
                </section>
            </Transition>

            <footer class="dashboard-footer">
                <span>
                    {{ roles.length }} active
                    {{ roles.length === 1 ? 'role' : 'roles' }}
                </span>

                <div class="footer-meta">
                    <span>{{ capabilities.canViewReports ? 'Reports enabled' : 'Reports restricted' }}</span>
                    <button type="button" @click="logout">Sign out</button>
                </div>
            </footer>
        </div>
    </ErpLayout>
</template>

<style scoped>
/* -------------------------------------------------------------------------- */
/* Dashboard-specific visual layer. It intentionally lives in this page so   */
/* this file can be replaced as a single unit without editing another file.   */
/* -------------------------------------------------------------------------- */

.dashboard-page {
    --dash-radius: 14px;
    --dash-shadow:
        0 10px 32px rgba(18, 40, 67, 0.055),
        0 1px 2px rgba(18, 40, 67, 0.035);
    position: relative;
    min-width: 0;
    padding-bottom: 34px;
}

.dashboard-page button {
    -webkit-tap-highlight-color: transparent;
}

.dashboard-hero {
    position: relative;
    min-height: 192px;
    display: flex;
    align-items: stretch;
    justify-content: space-between;
    gap: 24px;
    overflow: hidden;
    margin-bottom: 12px;
    padding: 28px 30px;
    border: 1px solid color-mix(in srgb, var(--border) 86%, #8fb2d1);
    border-radius: var(--dash-radius);
    background:
        radial-gradient(circle at 64% 45%, rgba(255, 255, 255, 0.92), transparent 38%),
        linear-gradient(110deg, #eef5fb 0%, #e9f1f8 46%, #dce8f2 100%);
    box-shadow: var(--dash-shadow);
}

.dashboard-hero::before {
    content: '';
    position: absolute;
    width: 420px;
    height: 420px;
    right: 20%;
    top: -250px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.35);
    filter: blur(2px);
}

.hero-copy {
    position: relative;
    z-index: 2;
    display: flex;
    flex-direction: column;
    justify-content: center;
    min-width: 330px;
}

.hero-eyebrow {
    color: #6e7f93;
    font-size: 15px;
    font-weight: 500;
}

.hero-copy h1 {
    margin: 2px 0 5px;
    color: #132033;
    font-size: clamp(28px, 3vw, 38px);
    line-height: 1;
    letter-spacing: -1.4px;
    font-weight: 700;
}

.hero-copy h1 span {
    display: inline-block;
    margin-left: 5px;
    font-size: 28px;
    transform: translateY(-2px);
}

.hero-copy p {
    margin: 0;
    color: #63748a;
    font-size: 13px;
}

.hero-actions {
    display: flex;
    gap: 8px;
    margin-top: 18px;
}

.hero-primary,
.hero-action {
    min-height: 38px;
    border: 1px solid transparent;
    border-radius: 9px;
    cursor: pointer;
    transition:
        transform 0.16s ease,
        box-shadow 0.16s ease,
        background 0.16s ease;
}

.hero-primary {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 0 14px;
    color: #fff;
    background: linear-gradient(135deg, #10bd78, #07875b);
    box-shadow: 0 8px 18px rgba(7, 145, 93, 0.18);
    font-size: 11px;
    font-weight: 700;
}

.hero-action {
    width: 38px;
    display: grid;
    place-items: center;
    color: #5f7187;
    border-color: rgba(133, 157, 181, 0.32);
    background: rgba(255, 255, 255, 0.62);
}

.hero-primary:hover,
.hero-action:hover {
    transform: translateY(-1px);
}

.hero-primary:hover {
    box-shadow: 0 11px 24px rgba(7, 145, 93, 0.23);
}

.hero-art {
    position: relative;
    z-index: 1;
    flex: 1;
    min-width: 500px;
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 16px;
}

.hero-art-glow {
    position: absolute;
    width: 500px;
    height: 170px;
    right: 90px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.55);
    filter: blur(22px);
}

.hero-scale-image {
    position: relative;
    width: min(42%, 390px);
    min-width: 270px;
    height: auto;
    filter: drop-shadow(0 16px 18px rgba(46, 70, 93, 0.15));
}

.hero-store-copy {
    position: relative;
    display: flex;
    flex-direction: column;
    min-width: 125px;
}

.hero-store-copy strong {
    color: #1b2b3c;
    font-size: 14px;
    line-height: 1.25;
}

.hero-store-copy > span {
    margin-top: 3px;
    color: #708196;
    font-size: 10px;
}

.hero-store-copy button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 4px;
    width: fit-content;
    margin-top: 12px;
    padding: 8px 11px;
    border: 0;
    border-radius: 7px;
    color: #fff;
    background: #465365;
    cursor: pointer;
    font-size: 10px;
    font-weight: 600;
}

.hero-weather {
    position: relative;
    display: flex;
    align-items: center;
    gap: 8px;
    min-width: 80px;
    padding-left: 10px;
}

.weather-icon {
    font-size: 31px;
}

.hero-weather strong {
    display: block;
    color: #27384b;
    font-size: 20px;
    line-height: 1;
}

.hero-weather small {
    display: block;
    margin-top: 5px;
    color: #75869a;
    font-size: 9px;
}

.kpi-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 10px;
    margin-bottom: 12px;
}

.dashboard-grid {
    display: grid;
    gap: 12px;
    margin-bottom: 12px;
}

.main-grid {
    grid-template-columns: minmax(0, 2.1fr) minmax(320px, 0.86fr);
}

.secondary-grid {
    grid-template-columns: 1.15fr 1.15fr 0.9fr;
}

.lower-grid {
    grid-template-columns: 1fr 1.1fr 1fr;
}

.panel,
.quick-card,
.tasks-card {
    min-width: 0;
    border: 1px solid var(--border);
    border-radius: var(--dash-radius);
    background: var(--surface);
    box-shadow: var(--dash-shadow);
}

.panel {
    overflow: hidden;
    padding: 16px;
}

.panel-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    margin-bottom: 12px;
}

.panel-head h2 {
    margin: 0;
    color: var(--text-primary);
    font-size: 14px;
    line-height: 1.25;
    letter-spacing: -0.2px;
}

.panel-head small {
    display: block;
    margin-top: 3px;
    color: var(--text-muted);
    font-size: 9px;
}

.section-title-row {
    display: flex;
    align-items: center;
    gap: 7px;
}

.live-dot {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 6px;
    border-radius: 999px;
    color: var(--success);
    background: color-mix(in srgb, var(--success) 10%, transparent);
    font-size: 8px;
    font-weight: 700;
}

.live-dot::before {
    content: '';
    width: 5px;
    height: 5px;
    border-radius: 50%;
    background: currentColor;
    box-shadow: 0 0 0 3px color-mix(in srgb, currentColor 12%, transparent);
}

.panel-link {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    border: 0;
    background: transparent;
    color: #1269ed;
    cursor: pointer;
    font-size: 9px;
    font-weight: 600;
}

.range-wrap {
    position: relative;
}

.range {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 10px;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: var(--surface);
    color: var(--text-secondary);
    cursor: pointer;
    font-size: 9px;
}

.range:hover {
    border-color: var(--border-strong);
}

.range-menu {
    position: absolute;
    top: calc(100% + 6px);
    right: 0;
    z-index: 15;
    min-width: 145px;
    padding: 5px;
    border: 1px solid var(--border);
    border-radius: 9px;
    background: var(--surface);
    box-shadow: 0 12px 30px rgba(20, 43, 70, 0.13);
}

.range-menu button {
    width: 100%;
    padding: 8px 9px;
    border: 0;
    border-radius: 6px;
    color: var(--text-secondary);
    background: transparent;
    text-align: left;
    cursor: pointer;
    font-size: 10px;
}

.range-menu button:hover,
.range-menu button.selected {
    color: var(--text-primary);
    background: var(--surface-muted);
}

.tabs {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    margin-top: 11px;
}

.tabs button {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 7px 9px;
    border: 0;
    border-radius: 7px;
    color: var(--text-secondary);
    background: transparent;
    cursor: pointer;
    font-size: 9px;
    transition:
        background 0.16s ease,
        color 0.16s ease;
}

.tabs button:hover {
    background: var(--surface-muted);
}

.tabs button.active {
    color: #fff;
    background: linear-gradient(135deg, #153d76, #1b4f95);
    box-shadow: 0 5px 12px rgba(24, 65, 125, 0.17);
}

.analytics-stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    margin-top: 10px;
    padding: 11px 0;
    border-top: 1px solid var(--border);
    border-bottom: 1px solid var(--border);
}

.analytics-stats > div {
    min-width: 0;
    padding: 0 12px;
    border-right: 1px solid var(--border);
}

.analytics-stats > div:first-child {
    padding-left: 0;
}

.analytics-stats > div:last-child {
    border-right: 0;
}

.analytics-stats strong {
    display: block;
    color: var(--text-primary);
    font-size: 17px;
    line-height: 1.2;
    letter-spacing: -0.45px;
}

.analytics-stats span {
    display: block;
    margin-top: 4px;
    color: var(--text-muted);
    font-size: 8px;
}

.analytics-stats em {
    margin-left: 3px;
    color: var(--success);
    font-style: normal;
    font-weight: 700;
}

.chart-area {
    display: flex;
    height: 235px;
    margin-top: 10px;
}

.y-axis {
    width: 50px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    padding: 10px 7px 28px 0;
    color: var(--text-muted);
    font-size: 8px;
    text-align: right;
}

.chart-canvas {
    position: relative;
    flex: 1;
    min-width: 0;
    overflow: hidden;
}

.grid-lines {
    position: absolute;
    inset: 10px 0 30px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}

.grid-lines i {
    display: block;
    border-top: 1px dashed color-mix(in srgb, var(--border) 95%, transparent);
}

.bars {
    position: absolute;
    inset: 14px 0 33px;
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 3px;
    opacity: 0.72;
}

.bars span {
    width: 2.5%;
    min-width: 3px;
    border-radius: 4px 4px 0 0;
    background: linear-gradient(180deg, #4c83e8, #9db9ea);
}

.line-chart {
    position: absolute;
    inset: 10px 0 30px;
    width: 100%;
    height: calc(100% - 40px);
    color: #0cab70;
    overflow: visible;
}

.chart-tooltip {
    position: absolute;
    right: 16%;
    top: 18%;
    z-index: 3;
    display: grid;
    gap: 4px;
    min-width: 145px;
    padding: 9px 10px;
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 9px;
    color: #fff;
    background: #162233;
    box-shadow: 0 12px 24px rgba(10, 23, 38, 0.2);
    font-size: 8px;
}

.chart-tooltip small {
    color: #b8c5d4;
    font-size: 7px;
}

.chart-tooltip span {
    display: flex;
    align-items: center;
    gap: 5px;
}

.chart-tooltip i {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #4d86ee;
}

.chart-tooltip i.tooltip-order {
    background: #15b678;
}

.chart-tooltip b {
    margin-left: auto;
    font-size: 8px;
}

.x-axis {
    position: absolute;
    left: 0;
    right: 0;
    bottom: 0;
    display: flex;
    justify-content: space-between;
    color: var(--text-muted);
    font-size: 8px;
}

.donut-row {
    display: flex;
    align-items: center;
    gap: 16px;
    min-height: 190px;
}

.donut-row.inventory,
.donut-row.traffic {
    min-height: 174px;
}

.donut-row :deep(.donut-wrap) {
    width: 148px;
    height: 148px;
    flex: 0 0 148px;
}

.donut-row :deep(.donut-center strong) {
    font-size: 18px;
}

.donut-row :deep(.donut-center span) {
    font-size: 8px;
}

.legend-list {
    flex: 1;
    min-width: 0;
}

.legend-list > div {
    display: grid;
    grid-template-columns: 9px 1fr auto auto;
    gap: 6px;
    align-items: center;
    padding: 6px 0;
    color: var(--text-secondary);
    font-size: 9px;
}

.legend-list i {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: currentColor;
}

.legend-list strong {
    color: var(--text-primary);
    font-size: 10px;
}

.legend-list small {
    color: var(--text-muted);
    font-size: 8px;
}

.table-panel {
    padding-bottom: 8px;
}

.table-wrap {
    overflow-x: auto;
}

table {
    width: 100%;
    min-width: 440px;
    border-collapse: collapse;
}

th {
    padding: 7px 5px;
    border-bottom: 1px solid var(--border);
    color: var(--text-muted);
    font-size: 8px;
    font-weight: 600;
    text-align: left;
}

td {
    padding: 9px 5px;
    border-bottom: 1px solid var(--border);
    color: var(--text-secondary);
    font-size: 9px;
    white-space: nowrap;
}

tbody tr:last-child td {
    border-bottom: 0;
}

td strong {
    color: var(--text-primary);
    font-weight: 600;
}

.rank {
    width: 20px;
    height: 20px;
    display: inline-grid;
    place-items: center;
    border: 1px solid var(--border);
    border-radius: 50%;
    color: var(--text-secondary);
    background: var(--surface-muted);
    font-size: 8px;
    font-weight: 700;
}

.positive {
    color: var(--success);
    font-weight: 700;
}

.order-link {
    padding: 0;
    border: 0;
    color: #1468ff;
    background: transparent;
    cursor: pointer;
    font-size: 9px;
    font-weight: 600;
}

.status-pill {
    display: inline-flex;
    align-items: center;
    padding: 4px 7px;
    border-radius: 999px;
    font-size: 8px;
    font-weight: 700;
}

.status-pill.pending {
    color: #9b6a00;
    background: #fff2c8;
}

.status-pill.processing {
    color: #1762c0;
    background: #dcecff;
}

.status-pill.shipped {
    color: #1678b7;
    background: #dff5ff;
}

.status-pill.delivered {
    color: #12804f;
    background: #dff8ea;
}

.status-pill.cancelled {
    color: #c83b50;
    background: #ffe3e8;
}

.marketing-list {
    display: grid;
    gap: 1px;
}

.marketing-list > div {
    display: grid;
    grid-template-columns: 28px minmax(100px, 1.2fr) 75px 85px 32px;
    gap: 6px;
    align-items: center;
    min-height: 39px;
    border-bottom: 1px solid var(--border);
}

.marketing-list > div:last-child {
    border-bottom: 0;
}

.marketing-list strong,
.marketing-list > span {
    color: var(--text-secondary);
    font-size: 9px;
}

.marketing-list strong {
    overflow: hidden;
    color: var(--text-primary);
    text-overflow: ellipsis;
    white-space: nowrap;
}

.marketing-list b {
    color: var(--success);
    font-size: 9px;
    text-align: right;
}

.channel-icon {
    width: 26px;
    height: 26px;
    display: grid;
    place-items: center;
    border-radius: 7px;
    color: #1668e8;
    background: #e9f1ff;
}

.growth-chip {
    padding: 4px 7px;
    border-radius: 999px;
    color: var(--success);
    background: color-mix(in srgb, var(--success) 9%, transparent);
    font-size: 8px;
    font-weight: 700;
}

.customer-growth {
    position: relative;
    height: 168px;
    padding-top: 5px;
}

.customer-growth > strong {
    display: flex;
    align-items: baseline;
    gap: 7px;
    color: var(--text-primary);
    font-size: 23px;
    letter-spacing: -0.5px;
}

.customer-growth > strong span {
    color: var(--text-secondary);
    font-size: 9px;
    font-weight: 500;
    letter-spacing: 0;
}

.customer-growth > svg {
    position: absolute;
    left: 0;
    right: 0;
    bottom: 23px;
    width: 100%;
    height: 105px;
    color: #2477f5;
}

.customer-growth .x-axis {
    bottom: 0;
}

/* Right rail: it occupies the reserved desktop dashboard area from app.css. */
.dashboard-right-rail {
    position: absolute;
    top: 0;
    right: 0;
    width: 258px;
    display: grid;
    gap: 12px;
}

.quick-card,
.tasks-card {
    padding: 14px;
}

.quick-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 7px;
}

.quick-grid button {
    min-height: 58px;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    justify-content: center;
    gap: 6px;
    padding: 8px;
    border: 1px solid var(--border);
    border-radius: 9px;
    color: var(--text-secondary);
    background: var(--surface-muted);
    cursor: pointer;
    text-align: left;
    transition:
        border-color 0.16s ease,
        transform 0.16s ease,
        background 0.16s ease;
}

.quick-grid button:hover {
    transform: translateY(-1px);
    border-color: var(--border-strong);
    background: var(--surface);
}

.quick-grid button > span:last-child {
    font-size: 8px;
    line-height: 1.2;
}

.quick-icon {
    width: 27px;
    height: 27px;
    display: grid;
    place-items: center;
    border-radius: 7px;
}

.quick-green {
    color: #0a9c67;
    background: #dff8ec;
}

.quick-blue {
    color: #246fe7;
    background: #e5efff;
}

.quick-purple {
    color: #8346dd;
    background: #eee5ff;
}

.quick-orange {
    color: #e08a00;
    background: #fff1d7;
}

.task-row {
    display: grid;
    grid-template-columns: 17px 1fr auto;
    gap: 7px;
    align-items: center;
    min-height: 39px;
    border-bottom: 1px solid var(--border);
    cursor: pointer;
}

.task-row:last-child {
    border-bottom: 0;
}

.task-row input {
    width: 16px;
    height: 16px;
    accent-color: var(--primary);
}

.task-row > span {
    min-width: 0;
    color: var(--text-secondary);
    font-size: 9px;
    line-height: 1.25;
}

.task-row small {
    color: var(--text-muted);
    font-size: 7px;
    white-space: nowrap;
}

.task-row.completed > span {
    color: var(--text-muted);
    text-decoration: line-through;
}

.dashboard-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 14px;
    padding: 8px 3px 0;
    color: var(--text-muted);
    font-size: 9px;
}

.footer-meta {
    display: flex;
    align-items: center;
    gap: 12px;
}

.dashboard-footer button {
    border: 0;
    color: var(--text-secondary);
    background: transparent;
    cursor: pointer;
    font-size: 9px;
}

/* -------------------------------------------------------------------------- */
/* Floating Chats / Support                                                   */
/* -------------------------------------------------------------------------- */

.chat-fab {
    position: fixed;
    right: 24px;
    bottom: 24px;
    z-index: 100;
    width: 58px;
    height: 58px;
    display: grid;
    place-items: center;
    border: 0;
    border-radius: 50%;
    color: #fff;
    background: linear-gradient(145deg, #6b45ff, #4f2de6);
    box-shadow:
        0 15px 34px rgba(92, 59, 240, 0.34),
        0 0 0 5px color-mix(in srgb, #6b45ff 12%, transparent);
    cursor: pointer;
    transition:
        transform 0.18s ease,
        box-shadow 0.18s ease;
}

.chat-fab:hover {
    transform: translateY(-2px) scale(1.02);
    box-shadow:
        0 18px 38px rgba(92, 59, 240, 0.4),
        0 0 0 6px color-mix(in srgb, #6b45ff 13%, transparent);
}

.chat-fab-icon {
    display: grid;
    place-items: center;
}

.chat-fab > b {
    position: absolute;
    top: -2px;
    right: -1px;
    min-width: 20px;
    height: 20px;
    display: grid;
    place-items: center;
    padding: 0 5px;
    border: 2px solid var(--surface);
    border-radius: 999px;
    color: #fff;
    background: #f04c61;
    font-size: 8px;
}

.chat-modal {
    position: fixed;
    right: 24px;
    bottom: 94px;
    z-index: 99;
    width: min(380px, calc(100vw - 32px));
    overflow: hidden;
    border: 1px solid #7658ff;
    border-radius: 18px;
    background: var(--surface);
    box-shadow:
        0 26px 70px rgba(24, 37, 57, 0.2),
        0 0 0 2px rgba(105, 73, 255, 0.1);
}

.chat-modal-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 15px 16px 13px;
    border-bottom: 1px solid var(--border);
}

.chat-title {
    display: flex;
    align-items: center;
    gap: 9px;
}

.chat-status {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #15b779;
    box-shadow: 0 0 0 4px rgba(21, 183, 121, 0.1);
}

.chat-title strong,
.chat-title small {
    display: block;
}

.chat-title strong {
    color: var(--text-primary);
    font-size: 12px;
}

.chat-title small {
    margin-top: 3px;
    color: var(--text-muted);
    font-size: 8px;
}

.chat-head-actions {
    display: flex;
    gap: 3px;
}

.chat-head-actions button {
    width: 29px;
    height: 29px;
    display: grid;
    place-items: center;
    border: 0;
    border-radius: 7px;
    color: var(--text-secondary);
    background: transparent;
    cursor: pointer;
}

.chat-head-actions button:hover {
    background: var(--surface-muted);
}

.chat-tabs {
    display: flex;
    gap: 5px;
    padding: 10px 12px 8px;
    overflow-x: auto;
}

.chat-tabs button {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    flex: 0 0 auto;
    padding: 6px 8px;
    border: 0;
    border-radius: 999px;
    color: var(--text-secondary);
    background: var(--surface-muted);
    cursor: pointer;
    font-size: 8px;
    font-weight: 600;
}

.chat-tabs button.active {
    color: #fff;
    background: #5c3ff1;
}

.chat-tabs button span {
    opacity: 0.9;
}

.chat-search {
    display: flex;
    align-items: center;
    gap: 7px;
    height: 35px;
    margin: 0 12px 9px;
    padding: 0 10px;
    border: 1px solid var(--border);
    border-radius: 9px;
    color: var(--text-muted);
    background: var(--surface-muted);
}

.chat-search input {
    width: 100%;
    min-width: 0;
    border: 0;
    outline: 0;
    color: var(--text-primary);
    background: transparent;
    font-size: 9px;
}

.chat-list {
    max-height: 350px;
    overflow-y: auto;
    padding: 0 8px 8px;
}

.chat-item {
    width: 100%;
    display: grid;
    grid-template-columns: 38px minmax(0, 1fr) auto;
    gap: 9px;
    align-items: center;
    padding: 8px 7px;
    border: 0;
    border-radius: 10px;
    color: inherit;
    background: transparent;
    cursor: pointer;
    text-align: left;
}

.chat-item:hover {
    background: var(--surface-muted);
}

.chat-avatar {
    position: relative;
    width: 34px;
    height: 34px;
    display: grid;
    place-items: center;
    border-radius: 50%;
    color: #fff;
    background: linear-gradient(145deg, #7f8fa5, #4c5d72);
    font-size: 11px;
    font-weight: 700;
}

.chat-avatar .presence {
    position: absolute;
    right: 0;
    bottom: 0;
    width: 9px;
    height: 9px;
    border: 2px solid var(--surface);
    border-radius: 50%;
    background: #17bd79;
}

.chat-item-copy {
    min-width: 0;
}

.chat-item-copy strong,
.chat-item-copy small {
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.chat-item-copy strong {
    color: var(--text-primary);
    font-size: 9px;
}

.chat-item-copy small {
    margin-top: 4px;
    color: var(--text-muted);
    font-size: 8px;
}

.chat-item time {
    align-self: start;
    padding-top: 2px;
    color: var(--text-muted);
    font-size: 7px;
    white-space: nowrap;
}

.empty-chat {
    display: grid;
    place-items: center;
    gap: 5px;
    padding: 32px 12px;
    color: var(--text-muted);
    text-align: center;
}

.empty-chat strong {
    color: var(--text-secondary);
    font-size: 10px;
}

.empty-chat span {
    font-size: 8px;
}

.open-chat-center {
    width: calc(100% - 24px);
    min-height: 39px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    margin: 4px 12px 12px;
    border: 0;
    border-radius: 9px;
    color: #fff;
    background: linear-gradient(135deg, #6746ff, #4d2de3);
    cursor: pointer;
    font-size: 9px;
    font-weight: 700;
}

.chat-pop-enter-active,
.chat-pop-leave-active {
    transition:
        opacity 0.18s ease,
        transform 0.18s ease;
}

.chat-pop-enter-from,
.chat-pop-leave-to {
    opacity: 0;
    transform: translateY(12px) scale(0.97);
}

/* -------------------------------------------------------------------------- */
/* Dark theme                                                                 */
/* -------------------------------------------------------------------------- */

:global(:root[data-theme='dark']) .dashboard-hero {
    border-color: #26384b;
    background:
        radial-gradient(circle at 64% 45%, rgba(49, 72, 96, 0.72), transparent 38%),
        linear-gradient(110deg, #172332 0%, #172736 48%, #13202d 100%);
}

:global(:root[data-theme='dark']) .hero-copy h1,
:global(:root[data-theme='dark']) .hero-store-copy strong,
:global(:root[data-theme='dark']) .hero-weather strong {
    color: #f3f7fb;
}

:global(:root[data-theme='dark']) .hero-eyebrow,
:global(:root[data-theme='dark']) .hero-copy p,
:global(:root[data-theme='dark']) .hero-store-copy > span,
:global(:root[data-theme='dark']) .hero-weather small {
    color: #9aabbe;
}

:global(:root[data-theme='dark']) .hero-action {
    color: #b6c4d4;
    border-color: #304357;
    background: rgba(17, 28, 40, 0.72);
}

:global(:root[data-theme='dark']) .hero-art-glow {
    background: rgba(86, 116, 145, 0.18);
}

:global(:root[data-theme='dark']) .quick-green {
    color: #4edb9b;
    background: rgba(20, 178, 113, 0.12);
}

:global(:root[data-theme='dark']) .quick-blue {
    color: #72a8ff;
    background: rgba(53, 121, 245, 0.13);
}

:global(:root[data-theme='dark']) .quick-purple {
    color: #b28bff;
    background: rgba(132, 70, 225, 0.13);
}

:global(:root[data-theme='dark']) .quick-orange {
    color: #ffbd52;
    background: rgba(224, 138, 0, 0.13);
}

:global(:root[data-theme='dark']) .status-pill.pending {
    color: #ffd56a;
    background: rgba(247, 183, 49, 0.14);
}

:global(:root[data-theme='dark']) .status-pill.processing {
    color: #8dbaff;
    background: rgba(36, 119, 245, 0.14);
}

:global(:root[data-theme='dark']) .status-pill.shipped {
    color: #7dd7ff;
    background: rgba(75, 180, 247, 0.14);
}

:global(:root[data-theme='dark']) .status-pill.delivered {
    color: #6ee4a7;
    background: rgba(73, 203, 130, 0.14);
}

:global(:root[data-theme='dark']) .status-pill.cancelled {
    color: #ff8393;
    background: rgba(240, 76, 97, 0.14);
}

:global(:root[data-theme='dark']) .channel-icon {
    color: #78a8ff;
    background: rgba(36, 119, 245, 0.14);
}

/* -------------------------------------------------------------------------- */
/* Responsive                                                                 */
/* -------------------------------------------------------------------------- */

@media (min-width: 1450px) {
    .dashboard-page {
        padding-right: 270px;
    }
}

@media (max-width: 1449px) {
    .dashboard-right-rail {
        position: static;
        width: auto;
        grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
        margin-bottom: 12px;
    }

    .dashboard-page {
        padding-right: 0;
    }

    .quick-card,
    .tasks-card {
        min-height: 100%;
    }
}

@media (max-width: 1180px) {
    .hero-art {
        min-width: 390px;
    }

    .hero-scale-image {
        min-width: 220px;
    }

    .secondary-grid,
    .lower-grid {
        grid-template-columns: 1fr 1fr;
    }

    .secondary-grid > :last-child,
    .lower-grid > :last-child {
        grid-column: 1 / -1;
    }
}

@media (max-width: 900px) {
    .dashboard-hero {
        min-height: 180px;
        padding: 23px;
    }

    .hero-art {
        display: none;
    }

    .hero-copy {
        min-width: 0;
    }

    .kpi-grid {
        grid-template-columns: 1fr 1fr;
    }

    .main-grid,
    .secondary-grid,
    .lower-grid {
        grid-template-columns: 1fr;
    }

    .secondary-grid > :last-child,
    .lower-grid > :last-child {
        grid-column: auto;
    }

    .analytics-stats {
        grid-template-columns: 1fr 1fr;
        row-gap: 10px;
    }

    .analytics-stats > div:nth-child(2) {
        border-right: 0;
    }

    .dashboard-right-rail {
        grid-template-columns: 1fr;
    }

    .chat-fab {
        right: 18px;
        bottom: 18px;
    }

    .chat-modal {
        right: 18px;
        bottom: 88px;
    }
}

@media (max-width: 600px) {
    .dashboard-hero {
        min-height: 165px;
        padding: 20px;
        border-radius: 12px;
    }

    .hero-copy h1 {
        font-size: 28px;
    }

    .hero-copy p {
        max-width: 300px;
    }

    .kpi-grid {
        grid-template-columns: 1fr;
    }

    .panel {
        padding: 12px;
    }

    .tabs {
        flex-wrap: nowrap;
        overflow-x: auto;
        padding-bottom: 2px;
    }

    .tabs button {
        white-space: nowrap;
    }

    .analytics-head {
        align-items: flex-start;
    }

    .analytics-stats {
        grid-template-columns: 1fr 1fr;
    }

    .analytics-stats > div {
        padding: 0 8px;
        border-right: 0;
    }

    .analytics-stats > div:nth-child(odd) {
        border-right: 1px solid var(--border);
    }

    .chart-area {
        height: 190px;
    }

    .y-axis {
        width: 42px;
    }

    .donut-row {
        flex-direction: column;
        align-items: stretch;
        min-height: auto;
        padding: 8px 0 4px;
    }

    .donut-row :deep(.donut-wrap) {
        margin: 0 auto;
    }

    .legend-list {
        width: 100%;
    }

    .marketing-list > div {
        grid-template-columns: 28px minmax(90px, 1fr) 65px 75px 30px;
    }

    .dashboard-footer {
        align-items: flex-start;
        flex-direction: column;
    }

    .footer-meta {
        width: 100%;
        justify-content: space-between;
    }

    .chat-fab {
        right: 14px;
        bottom: 14px;
        width: 54px;
        height: 54px;
    }

    .chat-modal {
        right: 10px;
        bottom: 78px;
        width: calc(100vw - 20px);
        border-radius: 15px;
    }
}

@media (max-width: 420px) {
    .hero-actions {
        gap: 6px;
    }

    .hero-primary {
        padding: 0 10px;
    }

    .hero-action {
        width: 34px;
    }

    .analytics-stats strong {
        font-size: 14px;
    }

    .analytics-stats span {
        line-height: 1.25;
    }

    .quick-grid {
        grid-template-columns: 1fr 1fr;
    }
}
</style>
