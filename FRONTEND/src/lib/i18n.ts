type Locale = 'en' | 'bn' | 'ar'

const translations: Record<Locale, Record<string, string>> = {
  en: {
    'dashboard': 'Dashboard',
    'settings': 'Settings',
    'logout': 'Logout',
    'cancel': 'Cancel',
    'save': 'Save',
    'delete': 'Delete',
    'edit': 'Edit',
    'create': 'Create',
    'loading': 'Loading...',
    'error': 'Error',
    'success': 'Success',
    'no_data': 'No data available',
    'confirm_delete': 'Are you sure?',
  },
  bn: {
    'dashboard': 'ড্যাশবোর্ড',
    'settings': 'সেটিংস',
    'logout': 'লগআউট',
    'cancel': 'বাতিল',
    'save': 'সংরক্ষণ করুন',
    'delete': 'মুছুন',
    'edit': 'সম্পাদনা',
    'create': 'তৈরি করুন',
    'loading': 'লোড হচ্ছে...',
    'error': 'ত্রুটি',
    'success': 'সফল',
    'no_data': 'কোন ডেটা পাওয়া যায়নি',
    'confirm_delete': 'আপনি কি নিশ্চিত?',
  },
  ar: {
    'dashboard': 'لوحة التحكم',
    'settings': 'الإعدادات',
    'logout': 'تسجيل الخروج',
    'cancel': 'إلغاء',
    'save': 'حفظ',
    'delete': 'حذف',
    'edit': 'تعديل',
    'create': 'إنشاء',
    'loading': 'جاري التحميل...',
    'error': 'خطأ',
    'success': 'نجح',
    'no_data': 'لا توجد بيانات',
    'confirm_delete': 'هل أنت متأكد؟',
  },
}

let currentLocale: Locale = (localStorage.getItem('locale') as Locale) || 'en'

export function setLocale(locale: Locale) {
  if (translations[locale]) {
    currentLocale = locale
    localStorage.setItem('locale', locale)
    document.documentElement.lang = locale
    document.documentElement.dir = locale === 'ar' ? 'rtl' : 'ltr'
  }
}

export function getLocale(): Locale {
  return currentLocale
}

export function t(key: string): string {
  return translations[currentLocale][key] || translations['en'][key] || key
}

export function isRTL(): boolean {
  return currentLocale === 'ar'
}

export function formatDate(date: Date): string {
  const f = new Intl.DateTimeFormat(currentLocale === 'ar' ? 'ar-AE' : (currentLocale === 'bn' ? 'bn-BD' : 'en-US'))
  return f.format(date)
}

export function formatCurrency(amount: number, currency = 'USD'): string {
  const f = new Intl.NumberFormat(currentLocale === 'ar' ? 'ar-AE' : (currentLocale === 'bn' ? 'bn-BD' : 'en-US'), {
    style: 'currency',
    currency,
  })
  return f.format(amount)
}

export const SUPPORTED_LOCALES: Locale[] = ['en', 'bn', 'ar']
