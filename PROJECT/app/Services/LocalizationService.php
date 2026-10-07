<?php

namespace App\Services;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;

class LocalizationService
{
    protected const SUPPORTED_LOCALES = ['en', 'bn', 'ar'];
    protected const SUPPORTED_TIMEZONES = ['UTC', 'Asia/Dhaka', 'Asia/Kolkata', 'Asia/Dubai'];
    protected const DATE_FORMATS = [
        'en' => 'Y-m-d',
        'bn' => 'd-m-Y',
        'ar' => 'd/m/Y',
    ];
    protected const TIME_FORMATS = [
        'en' => 'H:i:s',
        'bn' => 'H:i:s',
        'ar' => 'H:i:s',
    ];

    public static function setLocale(string $locale): void
    {
        if (!in_array($locale, self::SUPPORTED_LOCALES)) {
            $locale = 'en';
        }
        App::setLocale($locale);
        Cache::put('locale', $locale, now()->addYear());
    }

    public static function getLocale(): string
    {
        return Cache::get('locale', 'en');
    }

    public static function translate(string $key, array $params = []): string
    {
        return trans($key, $params);
    }

    public static function isRTL(): bool
    {
        return self::getLocale() === 'ar';
    }

    public static function formatDate(\DateTime $date): string
    {
        $format = self::DATE_FORMATS[self::getLocale()] ?? 'Y-m-d';
        return $date->format($format);
    }

    public static function formatTime(\DateTime $time): string
    {
        $format = self::TIME_FORMATS[self::getLocale()] ?? 'H:i:s';
        return $time->format($format);
    }

    public static function formatCurrency(float $amount, string $currency = 'USD'): string
    {
        $locale = self::getLocale();
        $numberFormatter = numfmt_create($locale === 'ar' ? 'ar_AE' : ($locale === 'bn' ? 'bn_BD' : 'en_US'), 1);
        return numfmt_format_currency($numberFormatter, $amount, $currency);
    }

    public static function getSupportedLocales(): array
    {
        return self::SUPPORTED_LOCALES;
    }

    public static function getSupportedTimezones(): array
    {
        return self::SUPPORTED_TIMEZONES;
    }
}
