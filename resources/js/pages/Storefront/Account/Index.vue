<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import Icon from '@/components/dashboard/Icon.vue';

defineProps<{
    customer: { name: string; phone: string | null; email: string | null };
    orders: Array<{ id: number; order_number: string; status: string; grand_total: string | number; created_at: string }>;
}>();
function logout() { router.post('/customer/logout'); }
</script>

<template>
    <Head title="My Account | MADINA" />
    <main class="account-page">
        <header class="account-header">
            <Link href="/" class="brand"><strong>MADINA</strong><span>WEIGHING SOLUTIONS</span></Link>
            <button type="button" class="logout" @click="logout">Sign out</button>
        </header>
        <section class="welcome">
            <p class="eyebrow">CUSTOMER ACCOUNT</p>
            <h1>Hello, {{ customer.name }}</h1>
            <p>Manage your profile and keep an eye on your recent orders.</p>
        </section>
        <section class="profile">
            <h2>Account details</h2>
            <p><strong>Mobile:</strong> {{ customer.phone || 'Not provided' }}</p>
            <p><strong>Email:</strong> {{ customer.email || 'Not provided' }}</p>
        </section>
        <section class="orders">
            <h2>Recent orders</h2>
            <div v-if="orders.length" class="order-list">
                <article v-for="order in orders" :key="order.id" class="order">
                    <div><strong>{{ order.order_number }}</strong><small>{{ order.created_at }}</small></div>
                    <span class="status">{{ order.status }}</span>
                    <strong>৳{{ Number(order.grand_total).toLocaleString('en-BD', { minimumFractionDigits: 2 }) }}</strong>
                </article>
            </div>
            <div v-else class="empty"><Icon name="package" :size="24" /><p>You haven't placed any orders yet.</p><Link href="/products">Explore products</Link></div>
        </section>
    </main>
</template>

<style scoped>
.account-page{min-height:100vh;background:#f7faf8;color:#14251b;font-family:Inter,ui-sans-serif,system-ui,sans-serif;padding:0 22px 50px}
.account-header{height:76px;display:flex;align-items:center;justify-content:space-between;max-width:1100px;margin:auto;border-bottom:1px solid #e6eee8}
.brand{display:grid;color:#087b3d;text-decoration:none}.brand strong{font-size:22px;letter-spacing:-1px}.brand span{font-size:9px;letter-spacing:1.5px;color:#75857b}
.logout{border:1px solid #d7e6dc;border-radius:9px;padding:9px 15px;background:white;color:#087b3d;font-weight:700;cursor:pointer}
.welcome,.profile,.orders{max-width:1100px;margin:28px auto;padding:25px;border:1px solid #e5eee8;border-radius:16px;background:#fff}
.eyebrow{color:#078044;font-size:10px;font-weight:800;letter-spacing:1.5px}.welcome h1{margin:5px 0;font-size:30px}.welcome p:last-child{color:#738278;font-size:13px}
h2{margin:0 0 17px;font-size:18px}.profile p{font-size:13px;color:#5f7065}
.order-list{display:grid;gap:10px}.order{display:grid;grid-template-columns:1fr auto auto;gap:20px;align-items:center;padding:15px;border:1px solid #e8efea;border-radius:11px}.order small{display:block;margin-top:5px;color:#849188;font-size:11px}.status{padding:5px 9px;border-radius:20px;background:#edf7f0;color:#087b3d;font-size:11px;text-transform:capitalize}
.empty{display:grid;justify-items:center;gap:8px;padding:28px;color:#78877e}.empty a{color:#087b3d;font-weight:700;text-decoration:none}
@media(max-width:600px){.account-page{padding:0 13px 30px}.welcome,.profile,.orders{padding:19px}.order{grid-template-columns:1fr auto;gap:10px}.order>strong{grid-column:1/-1}}
</style>
