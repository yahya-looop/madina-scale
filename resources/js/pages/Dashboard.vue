<script setup lang="ts">
import { computed } from 'vue';
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
const initials = computed(
    () =>
        props.user.name
            .split(/\s+/)
            .slice(0, 2)
            .map((part) => part[0])
            .join('')
            .toUpperCase() || '?',
);
function logout() {
    router.post('/logout');
}
const chartBars = [
    38, 55, 42, 68, 58, 76, 50, 62, 72, 48, 82, 60, 68, 54, 75, 64, 86, 58, 69,
    50, 77, 66, 73, 61, 80, 72, 86, 70, 90, 78,
];
</script>

<template>
    <ErpLayout :user-name="user.name" :user-email="user.email">
        <div class="dashboard-page">
            <section class="hero-banner">
                <div class="hero-copy">
                    <span class="eyebrow">Good Morning,</span>
                    <h1>{{ user.name }} <span>👋</span></h1>
                    <p>Here's what's happening with Madina Scale today.</p>
                    <div class="hero-actions">
                        <button class="store-btn">◫ Store</button
                        ><button class="hero-icon">
                            <Icon name="settings" :size="18" /></button
                        ><button class="hero-icon">⌁</button
                        ><button class="hero-icon">⌘</button>
                    </div>
                </div>
                <div class="hero-visual">
                    <div class="scale-art">⚖</div>
                    <div>
                        <strong>Reliable Scales<br />For Every Industry</strong
                        ><button>View Store →</button>
                    </div>
                    <div class="weather">
                        <span>☀️</span><strong>28°C</strong><small>Sunny</small>
                    </div>
                </div>
            </section>

            <section class="kpi-grid">
                <KpiCard
                    v-for="kpi in dashboardKpis"
                    :key="kpi.label"
                    :kpi="kpi"
                />
            </section>

            <section class="dashboard-grid main-grid">
                <article class="panel analytics-panel">
                    <div class="panel-head">
                        <div>
                            <h2>Sales & Order Analytics</h2>
                            <div class="tabs">
                                <button class="active">◉ Revenue</button
                                ><button>◌ Orders</button
                                ><button>♙ Customers</button
                                ><button>▣ Product Views</button
                                ><button>◎ Conversion</button>
                            </div>
                        </div>
                        <button class="range">
                            Last 30 Days <Icon name="chevron-down" :size="14" />
                        </button>
                    </div>
                    <div class="analytics-stats">
                        <div>
                            <strong>৳ 12,48,500</strong
                            ><span>Total Revenue <em>↗ 12.5%</em></span>
                        </div>
                        <div>
                            <strong>1,482</strong
                            ><span>Total Orders <em>↗ 8.2%</em></span>
                        </div>
                        <div>
                            <strong>3,842</strong
                            ><span>Product Views <em>↗ 18.7%</em></span>
                        </div>
                        <div>
                            <strong>892</strong
                            ><span>Total Customers <em>↗ 15.4%</em></span>
                        </div>
                    </div>
                    <div class="chart-area">
                        <div class="y-axis">
                            <span>৳ 500K</span><span>৳ 400K</span
                            ><span>৳ 300K</span><span>৳ 200K</span
                            ><span>৳ 100K</span><span>৳ 0</span>
                        </div>
                        <div class="chart-canvas">
                            <div class="grid-lines">
                                <i v-for="n in 6" :key="n"></i>
                            </div>
                            <div class="bars">
                                <span
                                    v-for="(bar, i) in chartBars"
                                    :key="i"
                                    :style="{ height: `${bar}%` }"
                                ></span>
                            </div>
                            <svg
                                viewBox="0 0 600 180"
                                preserveAspectRatio="none"
                                class="line-chart"
                            >
                                <polyline
                                    points="0,138 22,150 44,134 66,142 88,118 110,126 132,111 154,120 176,94 198,108 220,91 242,95 264,80 286,82 308,63 330,71 352,59 374,74 396,66 418,88 440,73 462,77 484,63 506,70 528,52 550,64 572,46 600,59"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="3"
                                />
                            </svg>
                            <div class="x-axis">
                                <span>Oct 1</span><span>Oct 5</span
                                ><span>Oct 10</span><span>Oct 15</span
                                ><span>Oct 20</span><span>Oct 25</span
                                ><span>Oct 30</span>
                            </div>
                        </div>
                    </div>
                </article>

                <article class="panel">
                    <div class="panel-head">
                        <h2>Order Status</h2>
                        <button class="range">
                            Last 30 Days <Icon name="chevron-down" :size="14" />
                        </button>
                    </div>
                    <div class="donut-row">
                        <DonutChart
                            total="1,482"
                            center-label="Total Orders"
                            :segments="
                                orderStatus.map((x) => ({
                                    value: x.value,
                                    tone: x.tone,
                                }))
                            "
                        />
                        <div class="legend-list">
                            <div v-for="item in orderStatus" :key="item.label">
                                <i :class="`legend-${item.tone}`"></i
                                ><span>{{ item.label }}</span
                                ><strong>{{ item.value }}</strong
                                ><small>({{ item.percent }})</small>
                            </div>
                        </div>
                    </div>
                </article>
            </section>

            <section class="dashboard-grid secondary-grid">
                <article class="panel table-panel">
                    <div class="panel-head">
                        <h2>Top Selling Products</h2>
                        <a href="#">View All</a>
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
                                    v-for="(item, i) in topProducts"
                                    :key="item[0]"
                                >
                                    <td>
                                        <span class="rank">{{ i + 1 }}</span>
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
                        <h2>Recent Orders</h2>
                        <a href="#">View All</a>
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
                                <tr v-for="item in recentOrders" :key="item[0]">
                                    <td>
                                        <a href="#">{{ item[0] }}</a>
                                    </td>
                                    <td>{{ item[1] }}</td>
                                    <td>{{ item[2] }}</td>
                                    <td>
                                        <span
                                            class="status-pill"
                                            :class="item[3].toLowerCase()"
                                            >{{ item[3] }}</span
                                        >
                                    </td>
                                    <td>{{ item[4] }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </article>
                <article class="panel">
                    <div class="panel-head"><h2>Inventory Status</h2></div>
                    <div class="donut-row inventory">
                        <DonutChart
                            total="1,245"
                            center-label="Total Products"
                            :segments="
                                inventoryStatus.map((x) => ({
                                    value: x.value,
                                    tone: x.tone,
                                }))
                            "
                        />
                        <div class="legend-list">
                            <div
                                v-for="item in inventoryStatus"
                                :key="item.label"
                            >
                                <i :class="`legend-${item.tone}`"></i
                                ><span>{{ item.label }}</span
                                ><strong>{{ item.value }}</strong
                                ><small>({{ item.percent }})</small>
                            </div>
                        </div>
                    </div>
                </article>
            </section>

            <section class="dashboard-grid lower-grid">
                <article class="panel">
                    <div class="panel-head">
                        <h2>Traffic Sources</h2>
                        <small>Last 30 days</small>
                    </div>
                    <div class="donut-row">
                        <DonutChart
                            total="12,480"
                            center-label="Total Visits"
                            :segments="
                                trafficSources.map((x, i) => ({
                                    value: [32, 28, 18, 12, 9][i],
                                    tone: [
                                        'blue',
                                        'sky',
                                        'warning',
                                        'danger',
                                        'purple',
                                    ][i],
                                }))
                            "
                        />
                        <div class="legend-list">
                            <div v-for="item in trafficSources" :key="item[0]">
                                <i class="legend-blue"></i
                                ><span>{{ item[0] }}</span
                                ><strong>{{ item[1] }}</strong>
                            </div>
                        </div>
                    </div>
                </article>
                <article class="panel">
                    <div class="panel-head">
                        <h2>Marketing Performance</h2>
                        <small>Last 30 days</small>
                    </div>
                    <div class="marketing-list">
                        <div
                            v-for="item in marketingPerformance"
                            :key="item[0]"
                        >
                            <span class="channel-icon">◎</span
                            ><strong>{{ item[0] }}</strong
                            ><span>{{ item[1] }}</span
                            ><span>{{ item[2] }}</span
                            ><b>{{ item[3] }}</b>
                        </div>
                    </div>
                </article>
                <article class="panel">
                    <div class="panel-head">
                        <h2>Customer Growth</h2>
                        <small>Last 30 days⌄</small>
                    </div>
                    <div class="customer-growth">
                        <strong
                            >892 <span>New Customers</span>
                            <em>↗ 15.4%</em></strong
                        ><svg viewBox="0 0 400 120" preserveAspectRatio="none">
                            <path
                                d="M0 105 35 98 65 86 92 90 120 76 148 80 175 62 205 68 235 48 265 55 290 34 320 45 350 30 400 10V120H0Z"
                                fill="currentColor"
                                opacity=".12"
                            />
                            <polyline
                                points="0,105 35,98 65,86 92,90 120,76 148,80 175,62 205,68 235,48 265,55 290,34 320,45 350,30 400,10"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="3"
                            />
                        </svg>
                        <div class="x-axis">
                            <span>Oct 1</span><span>Oct 10</span
                            ><span>Oct 20</span><span>Oct 30</span>
                        </div>
                    </div>
                </article>
            </section>

            <aside class="right-rail">
                <section class="quick-card">
                    <div class="panel-head">
                        <h2>Quick Actions</h2>
                        <a href="#">Customize</a>
                    </div>
                    <div class="quick-grid">
                        <button
                            v-for="item in [
                                'Add Product',
                                'Create Order',
                                'Add Customer',
                                'Create Quotation',
                                'Manage Inventory',
                                'Send Notification',
                            ]"
                            :key="item"
                        >
                            <Icon name="plus" :size="18" /><span>{{
                                item
                            }}</span>
                        </button>
                    </div>
                </section>
                <section class="tasks-card">
                    <div class="panel-head">
                        <h2>Today's Tasks</h2>
                        <a href="#">View All</a>
                    </div>
                    <label v-for="task in tasks" :key="task[0]"
                        ><input type="checkbox" /><span>{{ task[0] }}</span
                        ><small>{{ task[1] }}</small></label
                    >
                </section>
            </aside>

            <section class="chat-center">
                <div class="chat-head">
                    <strong>Chats</strong><strong>Support</strong
                    ><button>↗</button><button>⋮</button>
                </div>
                <div class="chat-tabs">
                    <button class="active">All (18)</button
                    ><button>Customers (12)</button><button>Team (6)</button
                    ><button>Unread <b>8</b></button>
                </div>
                <div class="chat-search">
                    <Icon name="search" :size="15" /> Search conversations...
                </div>
                <div class="chat-list">
                    <div v-for="chat in chats" :key="chat[0]" class="chat-item">
                        <span class="chat-avatar">{{
                            chat[0].slice(0, 1)
                        }}</span>
                        <div>
                            <strong>{{ chat[0] }}</strong
                            ><small>{{ chat[1] }}</small>
                        </div>
                        <time>{{ chat[2] }}</time>
                    </div>
                </div>
                <button class="open-chat">Open Chat Center →</button>
            </section>
            <button class="floating-chat" type="button" aria-label="Open chat">
                <Icon name="chat" :size="25" /><b>8</b>
            </button>

            <footer class="dashboard-footer">
                <span
                    >{{ roles.length }} active
                    {{ roles.length === 1 ? 'role' : 'roles' }}</span
                ><button type="button" @click="logout">Sign out</button>
            </footer>
        </div>
    </ErpLayout>
</template>
