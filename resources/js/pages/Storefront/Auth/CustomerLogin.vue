<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Icon from '@/components/dashboard/Icon.vue';

const props = defineProps<{ mode: 'login' | 'register'; canUseOtp: boolean }>();
const page = usePage();
const mode = ref<'login' | 'register'>(props.mode);
const method = ref<'password' | 'otp'>('password');
const otpSent = ref(false);
const showPassword = ref(false);
const flashMessage = computed(() => (page.props.flash as Record<string, string> | undefined)?.otp_sent ?? '');

const passwordForm = useForm({ identity: '', password: '', remember: false });
const otpForm = useForm({ phone: '', code: '' });
const registerForm = useForm({ name: '', phone: '', email: '', password: '', password_confirmation: '', code: '' });

function passwordLogin() {
    passwordForm.post('/login/password', { onFinish: () => passwordForm.reset('password') });
}
function sendLoginOtp() {
    otpForm.post('/login/otp', { onSuccess: () => { otpSent.value = true; } });
}
function verifyLoginOtp() {
    otpForm.post('/login/otp/verify');
}
function sendRegisterOtp() {
    otpForm.transform((data) => ({ phone: data.phone })).post('/register/otp', {
        onSuccess: () => { otpSent.value = true; },
        onFinish: () => registerForm.transform((data) => data),
    });
}
function register() {
    registerForm.post('/register');
}
</script>

<template>
    <Head :title="mode === 'register' ? 'Create Account | MADINA' : 'Customer Login | MADINA'" />
    <main class="customer-auth">
        <section class="auth-card">
            <a href="/" class="brand" aria-label="MADINA home">
                <span class="brand-mark">M</span>
                <span><strong>MADINA</strong><small>WEIGHING SOLUTIONS</small></span>
            </a>
            <p class="eyebrow">MADINA CUSTOMER ACCOUNT</p>
            <h1>{{ mode === 'register' ? 'Create your account' : 'Welcome back' }}</h1>
            <p class="intro">Sign in to manage your orders, track deliveries and enjoy a faster checkout.</p>

            <div class="mode-tabs" role="tablist" aria-label="Account access">
                <button type="button" :class="{ active: mode === 'login' }" @click="mode = 'login'; otpSent = false">Sign in</button>
                <button type="button" :class="{ active: mode === 'register' }" @click="mode = 'register'; otpSent = false">Create account</button>
            </div>

            <template v-if="mode === 'login'">
                <div class="method-tabs">
                    <button type="button" :class="{ active: method === 'password' }" @click="method = 'password'">Password</button>
                    <button type="button" :class="{ active: method === 'otp' }" @click="method = 'otp'">One-time code</button>
                </div>

                <form v-if="method === 'password'" class="auth-form" @submit.prevent="passwordLogin">
                    <label>Mobile number or email
                        <input v-model="passwordForm.identity" autocomplete="username" placeholder="01XXXXXXXXX or email@example.com" required />
                        <small v-if="passwordForm.errors.identity" class="error">{{ passwordForm.errors.identity }}</small>
                    </label>
                    <label>Password
                        <div class="password-field">
                            <input v-model="passwordForm.password" :type="showPassword ? 'text' : 'password'" autocomplete="current-password" placeholder="Enter your password" required />
                            <button type="button" class="reveal" @click="showPassword = !showPassword">{{ showPassword ? 'Hide' : 'Show' }}</button>
                        </div>
                        <small v-if="passwordForm.errors.password" class="error">{{ passwordForm.errors.password }}</small>
                    </label>
                    <label class="remember"><input v-model="passwordForm.remember" type="checkbox" /> Remember me</label>
                    <button class="primary" :disabled="passwordForm.processing">{{ passwordForm.processing ? 'Signing in…' : 'Sign in securely' }} <Icon name="arrow-right" :size="17" /></button>
                </form>

                <form v-else class="auth-form" @submit.prevent="otpSent ? verifyLoginOtp() : sendLoginOtp()">
                    <label>Mobile number
                        <input v-model="otpForm.phone" inputmode="tel" autocomplete="tel" placeholder="01XXXXXXXXX" required />
                        <small v-if="otpForm.errors.phone" class="error">{{ otpForm.errors.phone }}</small>
                    </label>
                    <label v-if="otpSent">6-digit verification code
                        <input v-model="otpForm.code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" placeholder="Enter the code" required />
                        <small v-if="otpForm.errors.code" class="error">{{ otpForm.errors.code }}</small>
                    </label>
                    <p v-if="flashMessage" class="notice">{{ flashMessage }}</p>
                    <button class="primary" :disabled="otpForm.processing">{{ otpForm.processing ? 'Please wait…' : otpSent ? 'Verify and sign in' : 'Send verification code' }}</button>
                    <button v-if="otpSent" type="button" class="text-button" @click="otpSent = false; otpForm.code = ''">Change mobile number</button>
                </form>
            </template>

            <form v-else class="auth-form" @submit.prevent="register">
                <label>Full name<input v-model="registerForm.name" autocomplete="name" required placeholder="Your name" /><small v-if="registerForm.errors.name" class="error">{{ registerForm.errors.name }}</small></label>
                <label>Mobile number<input v-model="registerForm.phone" inputmode="tel" autocomplete="tel" required placeholder="01XXXXXXXXX" /><small v-if="registerForm.errors.phone" class="error">{{ registerForm.errors.phone }}</small></label>
                <button v-if="!otpSent" type="button" class="secondary" :disabled="registerForm.processing" @click="sendRegisterOtp">Send phone verification code</button>
                <label v-if="otpSent">Verification code<input v-model="registerForm.code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required placeholder="6-digit code" /><small v-if="registerForm.errors.code" class="error">{{ registerForm.errors.code }}</small></label>
                <label>Email address <span class="optional">(optional)</span><input v-model="registerForm.email" type="email" autocomplete="email" placeholder="you@example.com" /><small v-if="registerForm.errors.email" class="error">{{ registerForm.errors.email }}</small></label>
                <label v-if="otpSent">Create password<input v-model="registerForm.password" type="password" autocomplete="new-password" minlength="8" required placeholder="At least 8 characters" /><small v-if="registerForm.errors.password" class="error">{{ registerForm.errors.password }}</small></label>
                <label v-if="otpSent">Confirm password<input v-model="registerForm.password_confirmation" type="password" autocomplete="new-password" required placeholder="Re-enter your password" /></label>
                <p v-if="flashMessage" class="notice">{{ flashMessage }}</p>
                <button v-if="otpSent" class="primary" :disabled="registerForm.processing">{{ registerForm.processing ? 'Creating account…' : 'Verify and create account' }}</button>
            </form>

            <p class="guest-note">Just want to shop? <a href="/">Continue as a guest</a></p>
            <p class="security-note"><Icon name="shield-check" :size="16" /> Your account is protected with secure authentication.</p>
        </section>
    </main>
</template>

<style scoped>
* { box-sizing: border-box; }
.customer-auth { min-height: 100vh; display:grid; place-items:center; padding:28px 16px; color:#10251a; background:linear-gradient(145deg,#f4faf6,#fff 48%,#e9f6ed); font-family:Inter,ui-sans-serif,system-ui,sans-serif; }
.auth-card { width:min(100%,480px); padding:34px; border:1px solid #e2eee6; border-radius:22px; background:#fff; box-shadow:0 22px 65px #063c1a12; }
.brand { display:flex; align-items:center; gap:12px; color:inherit; text-decoration:none; margin-bottom:30px; }
.brand-mark { width:44px;height:44px;display:grid;place-items:center;border-radius:13px;background:#087b3d;color:white;font-size:25px;font-weight:900; }
.brand strong { display:block;font-size:22px;letter-spacing:-1px; }
.brand small { display:block;margin-top:2px;color:#688273;font-size:9px;letter-spacing:2px;font-weight:800; }
.eyebrow { color:#078044;font-size:10px;font-weight:800;letter-spacing:1.7px; }
h1 { margin:8px 0;color:#12251b;font-size:30px;letter-spacing:-1.2px; }
.intro { margin:0 0 22px;color:#718078;font-size:13px;line-height:1.65; }
.mode-tabs,.method-tabs { display:flex;gap:6px;padding:5px;border-radius:12px;background:#f1f6f2; }
.mode-tabs button,.method-tabs button { flex:1;border:0;border-radius:9px;padding:10px 8px;background:transparent;color:#63746a;font-weight:700;font-size:12px;cursor:pointer; }
.mode-tabs button.active,.method-tabs button.active { background:white;color:#087b3d;box-shadow:0 1px 5px #153b2412; }
.method-tabs { margin-top:14px;background:transparent;padding:0; }
.method-tabs button { border:1px solid #e1ebe4; }
.auth-form { display:grid;gap:15px;margin-top:18px; }
.auth-form label { display:grid;gap:7px;color:#33473a;font-size:12px;font-weight:700; }
.auth-form input:not([type=checkbox]) { width:100%;height:46px;padding:0 13px;border:1px solid #dce8df;border-radius:10px;outline:0;color:#17291f;background:#fff;font:inherit;font-weight:500; }
.auth-form input:focus { border-color:#078044;box-shadow:0 0 0 3px #07804416; }
.password-field { position:relative; }
.password-field input { padding-right:60px!important; }
.reveal { position:absolute;right:9px;top:8px;height:30px;border:0;background:transparent;color:#078044;font-size:11px;font-weight:800;cursor:pointer; }
.remember { display:flex!important;align-items:center;grid-template-columns:16px 1fr; }
.primary,.secondary { display:flex;justify-content:center;align-items:center;gap:9px;width:100%;min-height:47px;border:0;border-radius:11px;background:#087b3d;color:white;font-size:13px;font-weight:800;cursor:pointer; }
.primary:disabled,.secondary:disabled { opacity:.65;cursor:wait; }
.secondary { border:1px solid #087b3d;background:#fff;color:#087b3d; }
.text-button { border:0;background:transparent;color:#087b3d;font-size:12px;font-weight:700;cursor:pointer; }
.error { color:#c52e2e;font-size:11px;font-weight:500; }
.notice { margin:0;padding:10px 12px;border-radius:9px;background:#edf8f0;color:#17653a;font-size:12px;line-height:1.5; }
.optional { color:#84938a;font-weight:500; }
.guest-note { margin:23px 0 0;text-align:center;color:#718078;font-size:12px; }
.guest-note a { color:#087b3d;font-weight:800;text-decoration:none; }
.security-note { display:flex;align-items:center;justify-content:center;gap:7px;margin:18px 0 0;color:#87948c;font-size:10px; }
.security-note :deep(svg) { color:#078044; }
@media(max-width:520px) { .auth-card{padding:25px 20px;border-radius:17px;} h1{font-size:26px;} }
@media(prefers-reduced-motion:reduce){*{scroll-behavior:auto!important;transition:none!important;}}
</style>
