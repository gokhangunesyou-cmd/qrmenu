<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Locale;
use App\Repository\LocaleRepository;
use Symfony\Component\HttpFoundation\Request;

class LanguageContext
{
    public const ADMIN_SESSION_KEY = 'admin.content_locale';
    public const SITE_SESSION_KEY = 'site.content_locale';

    public function __construct(private readonly LocaleRepository $localeRepository)
    {
    }

    /**
     * @return Locale[]
     */
    public function getActiveLocales(): array
    {
        return $this->localeRepository->findAllActive();
    }

    /**
     * @param string[]|null $allowedLocales
     */
    public function resolveAdminLocale(Request $request, string $fallback = 'tr', ?array $allowedLocales = null): string
    {
        return $this->resolveLocale($request, self::ADMIN_SESSION_KEY, $fallback, $allowedLocales);
    }

    /**
     * @param string[]|null $allowedLocales
     */
    public function resolveSiteLocale(Request $request, string $fallback = 'tr', ?array $allowedLocales = null): string
    {
        return $this->resolveLocale($request, self::SITE_SESSION_KEY, $fallback, $allowedLocales);
    }

    /**
     * @param string[]|null $allowedLocales
     *
     * @return array<string, string>
     */
    public function getLocaleLabelMap(?array $allowedLocales = null): array
    {
        $activeMap = $this->getActiveLocaleMap();
        if ($allowedLocales === null) {
            return $activeMap;
        }

        $map = [];
        foreach ($allowedLocales as $allowedLocale) {
            $code = $this->normalizeLocaleCode((string) $allowedLocale);
            if ($code === '' || !isset($activeMap[$code])) {
                continue;
            }

            $map[$code] = $activeMap[$code];
        }

        return $map;
    }

    /**
     * @param string[]|null $allowedLocales
     */
    private function resolveLocale(Request $request, string $sessionKey, string $fallback, ?array $allowedLocales = null): string
    {
        $allowed = array_keys($this->getLocaleLabelMap($allowedLocales));
        if ($allowed === []) {
            return $this->normalizeLocaleCode($fallback) ?: 'tr';
        }

        $queryLocale = $this->normalizeLocaleCode($request->query->getString('lang'));
        if ($queryLocale !== '' && in_array($queryLocale, $allowed, true)) {
            if ($request->hasSession()) {
                $request->getSession()->set($sessionKey, $queryLocale);
            }

            return $queryLocale;
        }

        if ($request->isMethod('POST')) {
            $postedLocale = $this->normalizeLocaleCode($request->request->getString('lang'));
            if ($postedLocale !== '' && in_array($postedLocale, $allowed, true)) {
                if ($request->hasSession()) {
                    $request->getSession()->set($sessionKey, $postedLocale);
                }

                return $postedLocale;
            }
        }

        if ($request->hasSession()) {
            $sessionLocale = $this->normalizeLocaleCode((string) $request->getSession()->get($sessionKey, ''));
            if ($sessionLocale !== '' && in_array($sessionLocale, $allowed, true)) {
                return $sessionLocale;
            }
        }

        $normalizedFallback = $this->normalizeLocaleCode($fallback);
        if ($normalizedFallback !== '' && in_array($normalizedFallback, $allowed, true)) {
            return $normalizedFallback;
        }

        return $allowed[0];
    }

    /**
     * @return array<string, string>
     */
    private function getActiveLocaleMap(): array
    {
        $map = [];
        foreach ($this->getActiveLocales() as $locale) {
            $code = $this->normalizeLocaleCode($locale->getCode());
            if ($code === '') {
                continue;
            }

            $map[$code] = $locale->getName();
        }

        return $map;
    }

    private function normalizeLocaleCode(string $code): string
    {
        return strtolower(trim($code));
    }

}
