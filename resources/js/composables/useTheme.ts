import { onBeforeUnmount, onMounted, ref } from 'vue';

export type ThemeMode = 'light' | 'dark' | 'system';

const STORAGE_KEY = 'madina-scale-theme';
const mode = ref<ThemeMode>('system');
let media: MediaQueryList | null = null;

function resolvedTheme(value: ThemeMode): 'light' | 'dark' {
    if (value !== 'system') return value;
    return media?.matches ? 'dark' : 'light';
}

function applyTheme(value: ThemeMode): void {
    document.documentElement.dataset.theme = resolvedTheme(value);
    document.documentElement.style.colorScheme = resolvedTheme(value);
}

function onSystemThemeChange(): void {
    if (mode.value === 'system') applyTheme('system');
}

export function useTheme() {
    onMounted(() => {
        const saved = localStorage.getItem(STORAGE_KEY) as ThemeMode | null;
        if (saved === 'light' || saved === 'dark' || saved === 'system')
            mode.value = saved;
        media = window.matchMedia('(prefers-color-scheme: dark)');
        media.addEventListener('change', onSystemThemeChange);
        applyTheme(mode.value);
    });

    onBeforeUnmount(() =>
        media?.removeEventListener('change', onSystemThemeChange),
    );

    function setTheme(value: ThemeMode): void {
        mode.value = value;
        localStorage.setItem(STORAGE_KEY, value);
        applyTheme(value);
    }

    return { mode, setTheme };
}
