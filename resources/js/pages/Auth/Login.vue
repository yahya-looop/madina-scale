<script setup lang="ts">
import { Head, useForm } from "@inertiajs/vue3";
import { ref } from "vue";
import Icon from "@/components/dashboard/Icon.vue";

const form = useForm({ email: '', password: '', remember: false });
const showPassword = ref(false);

const submit = () => {
    form.post('/login', {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Login | Madina Scale" />

    <main class="login-page">
        <section class="login-card" aria-label="Sign in to Madina Scale">
            <header class="brand-header">
                <img src="/images/madina-scale-icon.png" alt="Madina Scale logo" class="brand-logo" />
                <div class="brand-copy">
                    <div class="brand-name">Madina Scale <span>&amp; Co.</span></div>
                    <div class="brand-tagline">PRECISION WEIGHING SOLUTIONS</div>
                </div>
            </header>

            <div class="heading">
                <span class="eyebrow"><span class="eyebrow-line"></span> ERP CONTROL CENTER</span>
                <h1>Welcome back<span>.</span></h1>
                <p>Sign in to access your Madina Scale dashboard.</p>
            </div>

            <form class="login-form" @submit.prevent="submit">
                <div class="form-group">
                    <label for="email">Email address</label>
                    <div class="input-wrap" :class="{ 'has-error': form.errors.email }">
                        <Icon name="mail" :size="18" aria-hidden="true" />
                        <input
                            id="email"
                            v-model="form.email"
                            type="email"
                            name="email"
                            autocomplete="username"
                            placeholder="you@example.com"
                            required
                            autofocus
                            :aria-invalid="Boolean(form.errors.email)"
                            aria-describedby="email-error"
                        />
                    </div>
                    <p v-if="form.errors.email" id="email-error" class="form-error">{{ form.errors.email }}</p>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-wrap" :class="{ 'has-error': form.errors.password }">
                        <Icon name="lock" :size="18" aria-hidden="true" />
                        <input
                            id="password"
                            v-model="form.password"
                            :type="showPassword ? 'text' : 'password'"
                            name="password"
                            autocomplete="current-password"
                            placeholder="Enter your password"
                            required
                            :aria-invalid="Boolean(form.errors.password)"
                            aria-describedby="password-error"
                        />
                        <button
                            class="password-toggle"
                            type="button"
                            :aria-label="showPassword ? 'Hide password' : 'Show password'"
                            @click="showPassword = !showPassword"
                        >
                            <Icon name="eye-off" v-if="showPassword" :size="18" />
                            <Icon name="eye" v-else :size="18" />
                        </button>
                    </div>
                    <p v-if="form.errors.password" id="password-error" class="form-error">{{ form.errors.password }}</p>
                </div>

                <div class="form-options">
                    <label class="remember-label">
                        <input v-model="form.remember" type="checkbox" name="remember" />
                        <span class="custom-checkbox" aria-hidden="true"></span>
                        <span>Remember me</span>
                    </label>
                    <a href="/forgot-password" class="forgot-link">Forgot password?</a>
                </div>

                <button class="submit-button" type="submit" :disabled="form.processing">
                    <span>{{ form.processing ? 'Signing in...' : 'Sign in to dashboard' }}</span>
                    <Icon name="arrow-right" v-if="!form.processing" :size="18" aria-hidden="true" />
                    <span v-else class="spinner" aria-hidden="true"></span>
                </button>
            </form>

            <div class="secure-note">
                <Icon name="shield-check" :size="16" aria-hidden="true" />
                <span>Secure access to your business workspace</span>
            </div>
            <footer class="login-footer"><span>Madina Scale</span><i>•</i><span>Enterprise Resource Planning</span></footer>
        </section>
        <p class="page-caption">Precision <span>•</span> Reliability <span>•</span> Growth</p>
    </main>
</template>

<style scoped>
:global(*) { box-sizing: border-box; }
:global(html), :global(body), :global(#app) { min-width: 320px; min-height: 100%; margin: 0; }
:global(body) { font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
:global(button), :global(input) { font: inherit; }

.login-page {
    --green: #078746;
    --green-dark: #056638;
    min-height: 100vh;
    min-height: 100svh;
    width: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 16px;
    padding: 22px 16px;
    overflow-x: hidden;
    color: #17251d;
    background: radial-gradient(ellipse at 50% 0%, rgba(8, 135, 70, .09), transparent 42%), #f4f8f5;
}

.login-card {
    width: min(100%, 430px);
    padding: 30px 34px 25px;
    border: 1px solid #e5ece7;
    border-radius: 20px;
    background: #fff;
    box-shadow: 0 18px 55px rgba(24, 61, 39, .09), 0 2px 8px rgba(24, 61, 39, .035);
}

.brand-header { display: flex; align-items: center; gap: 12px; padding-bottom: 25px; border-bottom: 1px solid #edf1ee; }
.brand-logo { display: block; width: 49px; height: 49px; flex: 0 0 49px; object-fit: contain; border-radius: 11px; }
.brand-copy { min-width: 0; }
.brand-name { color: #14251c; font-size: 19px; line-height: 1.15; font-weight: 850; letter-spacing: -.045em; }
.brand-name span { color: var(--green); }
.brand-tagline { margin-top: 5px; color: #7a8980; font-size: 8px; line-height: 1.3; font-weight: 800; letter-spacing: .13em; }

.heading { margin: 25px 0 23px; }
.eyebrow { display: inline-flex; align-items: center; gap: 8px; color: var(--green); font-size: 9px; font-weight: 850; letter-spacing: .16em; }
.eyebrow-line { width: 19px; height: 3px; border-radius: 5px; background: currentColor; }
.heading h1 { margin: 12px 0 7px; color: #14251c; font-size: 32px; line-height: 1.12; font-weight: 850; letter-spacing: -.055em; }
.heading h1 span { color: var(--green); }
.heading p { margin: 0; color: #7c8981; font-size: 12px; line-height: 1.6; }

.login-form { display: flex; flex-direction: column; gap: 16px; }
.form-group { display: flex; flex-direction: column; gap: 7px; }
.form-group > label { color: #34443a; font-size: 11px; font-weight: 750; }
.input-wrap { height: 45px; display: flex; align-items: center; gap: 10px; padding: 0 12px; border: 1px solid #dce5df; border-radius: 10px; color: #7e8c83; background: #fff; transition: border-color .18s ease, box-shadow .18s ease; }
.input-wrap:focus-within { border-color: var(--green); box-shadow: 0 0 0 3px rgba(8, 139, 73, .09); }
.input-wrap.has-error { border-color: #dc4545; }
.input-wrap > svg { flex: 0 0 auto; }
.input-wrap input { width: 100%; min-width: 0; height: 100%; padding: 0; border: 0; outline: 0; color: #18271f; background: transparent; font-size: 12px; }
.input-wrap input::placeholder { color: #a0aca4; }
.password-toggle { display: grid; place-items: center; flex: 0 0 28px; width: 28px; height: 28px; padding: 0; border: 0; border-radius: 7px; color: #738177; background: transparent; cursor: pointer; }
.password-toggle:hover { color: var(--green); background: #edf7f0; }
.form-error { margin: 0; color: #c83434; font-size: 11px; line-height: 1.4; }
.form-options { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-top: -1px; }
.remember-label { display: inline-flex; align-items: center; gap: 8px; color: #58665d; font-size: 11px; cursor: pointer; }
.remember-label input { position: absolute; width: 1px; height: 1px; opacity: 0; }
.custom-checkbox { display: grid; place-items: center; width: 16px; height: 16px; border: 1px solid #cbd8cf; border-radius: 4px; background: #fff; }
.remember-label input:checked + .custom-checkbox { border-color: var(--green); background: var(--green); }
.remember-label input:checked + .custom-checkbox::after { content: ""; width: 7px; height: 4px; margin-top: -2px; border-left: 2px solid #fff; border-bottom: 2px solid #fff; transform: rotate(-45deg); }
.remember-label input:focus-visible + .custom-checkbox { outline: 3px solid rgba(8, 139, 73, .2); outline-offset: 2px; }
.forgot-link { color: var(--green); font-size: 11px; font-weight: 750; text-decoration: none; }
.forgot-link:hover { text-decoration: underline; }
.submit-button { display: flex; align-items: center; justify-content: center; gap: 11px; width: 100%; min-height: 46px; margin-top: 2px; padding: 0 16px; border: 0; border-radius: 10px; color: #fff; background: linear-gradient(105deg, #079b50, #056c3b); box-shadow: 0 8px 18px rgba(7, 139, 73, .16); font-size: 12px; font-weight: 800; cursor: pointer; transition: transform .18s ease, box-shadow .18s ease; }
.submit-button:hover:not(:disabled) { transform: translateY(-1px); box-shadow: 0 11px 22px rgba(7, 139, 73, .22); }
.submit-button:focus-visible { outline: 3px solid rgba(8, 139, 73, .22); outline-offset: 3px; }
.submit-button:disabled { cursor: wait; opacity: .72; }
.spinner { width: 15px; height: 15px; border: 2px solid rgba(255,255,255,.45); border-top-color: #fff; border-radius: 50%; animation: spin .7s linear infinite; }
.secure-note { display: flex; align-items: center; justify-content: center; gap: 7px; margin-top: 20px; color: #849087; font-size: 10px; }
.secure-note svg { color: var(--green); flex: 0 0 auto; }
.login-footer { display: flex; justify-content: center; flex-wrap: wrap; gap: 7px; margin-top: 16px; color: #9aa59d; font-size: 10px; }
.login-footer i { color: #91c5a2; font-style: normal; }
.page-caption { margin: 0; color: #9aa69e; font-size: 9px; font-weight: 750; letter-spacing: .16em; text-transform: uppercase; }
.page-caption span { padding: 0 5px; color: #8bc49d; }

@keyframes spin { to { transform: rotate(360deg); } }
@media (max-height: 700px) and (min-width: 561px) {
    .login-page { gap: 8px; padding-top: 10px; padding-bottom: 10px; }
    .login-card { padding: 22px 30px 18px; }
    .brand-header { padding-bottom: 16px; }
    .brand-logo { width: 42px; height: 42px; flex-basis: 42px; }
    .heading { margin: 17px 0 16px; }
    .heading h1 { font-size: 28px; margin-top: 9px; }
    .login-form { gap: 12px; }
    .input-wrap { height: 41px; }
    .submit-button { min-height: 42px; }
    .secure-note { margin-top: 14px; }
    .login-footer { margin-top: 11px; }
}
@media (max-width: 560px) {
    .login-page { justify-content: center; padding: 18px 13px; }
    .login-card { width: min(100%, 420px); padding: 25px 22px 22px; border-radius: 17px; }
    .brand-header { padding-bottom: 20px; }
    .heading { margin: 22px 0 21px; }
    .heading h1 { font-size: 29px; }
    .page-caption { font-size: 8px; }
}
@media (max-width: 360px) {
    .login-card { padding-right: 17px; padding-left: 17px; }
    .brand-name { font-size: 17px; }
    .form-options { align-items: flex-start; }
}
@media (prefers-reduced-motion: reduce) {
    *, *::before, *::after { animation-duration: .01ms !important; transition-duration: .01ms !important; }
}
</style>
