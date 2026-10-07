import { setLocale, getLocale, SUPPORTED_LOCALES } from '../lib/i18n'

export function LanguageSwitcher() {
  const current = getLocale()

  const names: Record<string, string> = {
    en: 'English',
    bn: 'বাংলা',
    ar: 'العربية',
  }

  return (
    <div className="flex gap-1">
      {SUPPORTED_LOCALES.map((locale) => (
        <button
          key={locale}
          onClick={() => {
            setLocale(locale)
            window.location.reload()
          }}
          className={`px-2 py-1 text-xs font-medium ${
            current === locale ? 'bg-teal text-white' : 'bg-canvas text-ink-soft hover:text-ink'
          }`}
        >
          {names[locale]}
        </button>
      ))}
    </div>
  )
}
