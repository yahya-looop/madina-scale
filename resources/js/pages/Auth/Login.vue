<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post('/login', {
        onFinish: () => {
            form.reset('password');
        },
    });
};
</script>

<template>
    <Head title="Login" />

    <div class="login-page">
        <div class="login-shell">
            <section class="login-brand">
                <div class="brand-mark">M<span>↗</span></div>

                <div class="brand-copy">
                    <h1>MADINA SCALE</h1>
                    <p>WEIGHING A BETTER TOMORROW</p>
                </div>
            </section>

            <section class="login-card">
                <div class="login-header">
                    <p class="eyebrow">ERP CONTROL CENTER</p>

                    <h2>Welcome back</h2>

                    <p>
                        Sign in to access your Madina Scale enterprise
                        dashboard.
                    </p>
                </div>

                <form @submit.prevent="submit" class="login-form">
                    <div class="form-group">
                        <label for="email">Email address</label>

                        <input
                            id="email"
                            v-model="form.email"
                            type="email"
                            name="email"
                            autocomplete="email"
                            placeholder="you@example.com"
                            required
                            autofocus
                        />

                        <span
                            v-if="form.errors.email"
                            class="form-error"
                        >
                            {{ form.errors.email }}
                        </span>
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>

                        <input
                            id="password"
                            v-model="form.password"
                            type="password"
                            name="password"
                            autocomplete="current-password"
                            placeholder="Enter your password"
                            required
                        />

                        <span
                            v-if="form.errors.password"
                            class="form-error"
                        >
                            {{ form.errors.password }}
                        </span>
                    </div>

                    <label class="remember">
                        <input
                            v-model="form.remember"
                            type="checkbox"
                            name="remember"
                        />

                        <span>Remember me</span>
                    </label>

                    <div
                        v-if="form.errors.email && !form.errors.password"
                        class="form-error general-error"
                    >
                        {{ form.errors.email }}
                    </div>

                    <button
                        type="submit"
                        class="login-button"
                        :disabled="form.processing"
                    >
                        <span v-if="form.processing">
                            Signing in...
                        </span>

                        <span v-else>
                            Sign in to dashboard
                        </span>
                    </button>
                </form>
            </section>

            <footer class="login-footer">
                <span>Madina Scale</span>
                <span>•</span>
                <span>Enterprise Resource Planning</span>
            </footer>
        </div>
    </div>
</template>

<style scoped>
.login-page {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 40px 24px;
    background:
        radial-gradient(
            circle at top left,
            rgba(15, 23, 42, 0.06),
            transparent 35%
        ),
        #f5f7fa;
    color: #111827;
}

.login-shell {
    width: 100%;
    max-width: 460px;
}

.login-brand {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 14px;
    margin-bottom: 28px;
}

.brand-mark {
    width: 48px;
    height: 48px;
    display: grid;
    place-items: center;
    border-radius: 14px;
    background: #111827;
    color: white;
    font-size: 22px;
    font-weight: 800;
    letter-spacing: -0.04em;
}

.brand-mark span {
    color: #f97316;
    font-size: 15px;
    margin-left: 1px;
}

.brand-copy h1 {
    margin: 0;
    font-size: 17px;
    font-weight: 800;
    letter-spacing: 0.08em;
}

.brand-copy p {
    margin: 4px 0 0;
    color: #6b7280;
    font-size: 9px;
    font-weight: 700;
    letter-spacing: 0.12em;
}

.login-card {
    padding: 34px;
    border: 1px solid #e5e7eb;
    border-radius: 20px;
    background: #ffffff;
    box-shadow:
        0 24px 60px rgba(15, 23, 42, 0.08),
        0 4px 14px rgba(15, 23, 42, 0.04);
}

.login-header {
    margin-bottom: 28px;
}

.eyebrow {
    margin: 0 0 9px;
    color: #f97316;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 0.12em;
}

.login-header h2 {
    margin: 0;
    font-size: 30px;
    line-height: 1.15;
    letter-spacing: -0.03em;
}

.login-header > p:last-child {
    margin: 10px 0 0;
    color: #6b7280;
    font-size: 14px;
    line-height: 1.6;
}

.login-form {
    display: flex;
    flex-direction: column;
    gap: 19px;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.form-group label {
    color: #374151;
    font-size: 13px;
    font-weight: 700;
}

.form-group input {
    width: 100%;
    box-sizing: border-box;
    height: 46px;
    padding: 0 14px;
    border: 1px solid #d1d5db;
    border-radius: 10px;
    outline: none;
    background: #fff;
    color: #111827;
    font: inherit;
    transition:
        border-color 0.15s ease,
        box-shadow 0.15s ease;
}

.form-group input::placeholder {
    color: #9ca3af;
}

.form-group input:focus {
    border-color: #111827;
    box-shadow: 0 0 0 3px rgba(17, 24, 39, 0.08);
}

.form-error {
    color: #dc2626;
    font-size: 12px;
    line-height: 1.4;
}

.remember {
    display: flex;
    align-items: center;
    gap: 9px;
    color: #4b5563;
    font-size: 13px;
    cursor: pointer;
}

.remember input {
    width: 15px;
    height: 15px;
    accent-color: #111827;
}

.general-error {
    margin-top: -8px;
}

.login-button {
    width: 100%;
    height: 48px;
    border: 0;
    border-radius: 10px;
    background: #111827;
    color: #ffffff;
    font: inherit;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
    transition:
        transform 0.15s ease,
        opacity 0.15s ease,
        background 0.15s ease;
}

.login-button:hover:not(:disabled) {
    background: #1f2937;
    transform: translateY(-1px);
}

.login-button:disabled {
    cursor: not-allowed;
    opacity: 0.6;
}

.login-footer {
    display: flex;
    justify-content: center;
    gap: 7px;
    margin-top: 22px;
    color: #9ca3af;
    font-size: 11px;
}

@media (max-width: 560px) {
    .login-page {
        padding: 24px 16px;
    }

    .login-card {
        padding: 26px 20px;
        border-radius: 16px;
    }

    .login-header h2 {
        font-size: 26px;
    }
}
</style>
