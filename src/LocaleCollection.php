<?php

namespace Wotz\LocaleCollection;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

/**
 * @template TKey of array-key
 * @template TLocale of Locale
 *
 * @extends Collection<TKey, TLocale>
 */
class LocaleCollection extends Collection
{
    public function getCurrent(): ?Locale
    {
        return $this->firstLocale(app()->currentLocale());
    }

    public function fallback(): Locale
    {
        // If the user has a preferred locale cookie, use that
        if (Cookie::has('locale') && $this->isAllowed(Cookie::get('locale'))) {
            return $this->firstLocale(Cookie::get('locale'));
        }

        // first get locales for current url
        $locales = $this->where(fn (Locale $locale) => $locale->url() === request()->root());

        // if no locales for the current url are found, get all locales
        if ($locales->count() === 0) {
            $locales = $this;
        }

        $preferredBrowserLocale = request()->getPreferredLanguage();

        // if we have a matching browser locale with country (e.g. nl-BE)
        // else if we have a matching browser locale without country (e.g. nl)
        // else if we have a matching browser locale that starts with the preferred browser locale
        // else if we have a matching preferred browser locale that starts with the browser locale
        // else if there is a fallback locale (config('app.fallback_locale))
        // else return first available locale
        return $locales->firstWhere(fn (Locale $locale) => $locale->browserLocaleWithCountry() === $preferredBrowserLocale) ?:
            $locales->firstWhere(fn (Locale $locale) => $locale->browserLocale() === $preferredBrowserLocale) ?:
            $locales->firstWhere(fn (Locale $locale) => Str::startsWith($preferredBrowserLocale, $locale->browserLocaleWithCountry())) ?:
            $locales->firstWhere(fn (Locale $locale) => Str::startsWith($locale->browserLocaleWithCountry(), $preferredBrowserLocale)) ?:
            $locales->firstLocale(app()->getFallbackLocale()) ?:
            $locales->first();
    }

    /**
     * Set the current locale
     *
     * @return $this
     */
    public function setCurrent(string $currentLocale, string $url): static
    {
        $localeObject = $this->firstLocaleWithUrl($currentLocale, $url);

        if ($localeObject) {
            app()->setLocale($localeObject->locale());
        }

        return $this;
    }

    public function isAllowed(string $localeToFind): bool
    {
        return $this->contains(fn (Locale $locale) => $locale->locale() === $localeToFind);
    }

    public function firstLocale(?string $localeToFind): ?Locale
    {
        if (! $localeToFind) {
            return null;
        }

        return $this->firstWhere(fn (Locale $locale) => $locale->locale() === $localeToFind);
    }

    public function firstLocaleWithUrl(string $localeToFind, string $url): ?Locale
    {
        return $this->firstWhere(fn (Locale $locale) => $locale->urlWithLocale() === app('url')->format($url, $localeToFind));
    }
}
