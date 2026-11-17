export type StorefrontLocale = 'en' | 'bn'

export function useStorefrontLocale() {
  const savedLocale = useCookie<StorefrontLocale>('madina-storefront-locale', {
    default: () => 'en',
    maxAge: 60 * 60 * 24 * 365,
    sameSite: 'lax',
  })
  const locale = useState<StorefrontLocale>('madina-storefront-locale-state', () => savedLocale.value === 'bn' ? 'bn' : 'en')

  watch(locale, value => {
    savedLocale.value = value
  })

  const isBangla = computed(() => locale.value === 'bn')
  const t = (english: string, bangla: string) => isBangla.value ? bangla : english

  return { locale, isBangla, t }
}
