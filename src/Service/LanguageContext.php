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

    public function resolveAdminLocale(Request $request, string $fallback = 'tr'): string
    {
        return $this->resolveLocale($request, self::ADMIN_SESSION_KEY, $fallback);
    }

    public function resolveSiteLocale(Request $request, string $fallback = 'tr'): string
    {
        return $this->resolveLocale($request, self::SITE_SESSION_KEY, $fallback);
    }

    /**
     * @return array<string, string>
     */
    public function getLocaleLabelMap(): array
    {
        $map = [];
        foreach ($this->getActiveLocales() as $locale) {
            $map[$locale->getCode()] = $locale->getName();
        }

        return $map;
    }

    private function resolveLocale(Request $request, string $sessionKey, string $fallback): string
    {
        $allowed = array_keys($this->getLocaleLabelMap());
        if ($allowed === []) {
            return $fallback;
        }

        $queryLocale = trim($request->query->getString('lang'));
        if ($queryLocale !== '' && in_array($queryLocale, $allowed, true)) {
            if ($request->hasSession()) {
                $request->getSession()->set($sessionKey, $queryLocale);
            }

            return $queryLocale;
        }

        if ($request->isMethod('POST')) {
            $postedLocale = trim($request->request->getString('lang'));
            if ($postedLocale !== '' && in_array($postedLocale, $allowed, true)) {
                if ($request->hasSession()) {
                    $request->getSession()->set($sessionKey, $postedLocale);
                }

                return $postedLocale;
            }
        }

        if ($request->hasSession()) {
            $sessionLocale = (string) $request->getSession()->get($sessionKey, '');
            if ($sessionLocale !== '' && in_array($sessionLocale, $allowed, true)) {
                return $sessionLocale;
            }
        }

        if (in_array($fallback, $allowed, true)) {
            return $fallback;
        }

        return $allowed[0];
    }
}
