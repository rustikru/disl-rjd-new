<?php
declare(strict_types=1);

namespace App\Services;

use Psr\Http\Message\ServerRequestInterface;

/** Временно скрывает и блокирует разделы, перечисленные в конфигурации. */
final class PageAvailability
{
    private array $disabledPages;
    private string $basePath;

    public function __construct(array $disabledPages, string $basePath = '')
    {
        $this->disabledPages = array_values(array_unique(array_filter(array_map(
            static fn(mixed $page): string => strtolower(trim((string) $page)),
            $disabledPages
        ))));
        $this->basePath = rtrim($basePath, '/');
    }

    public function hasDisabledPages(): bool
    {
        return $this->disabledPages !== [];
    }

    public function isDisabled(string $page): bool
    {
        $page = strtolower(trim($page));
        if (in_array($page, $this->disabledPages, true)) {
            return true;
        }
        if (isset(PageRegistry::all()[$page])) {
            foreach (PageRegistry::availabilityKeys($page) as $key) {
                if (in_array(strtolower(trim((string) $key)), $this->disabledPages, true)) {
                    return true;
                }
            }
        }
        return false;
    }

    /** Убирает отключённые отчёты из настроек клиентской части. */
    public function filterReports(array $reports): array
    {
        return array_filter(
            $reports,
            fn(array $definition, string $code): bool => !$this->anyDisabled(PageRegistry::availabilityKeys($code)),
            ARRAY_FILTER_USE_BOTH
        );
    }

    /** Убирает отключённые пункты и опустевшие группы из бокового меню. */
    public function filterNavigation(array $groups): array
    {
        foreach ($groups as &$group) {
            $group['items'] = array_values(array_filter(
                (array) ($group['items'] ?? []),
                function (array $item): bool {
                    $keys = [
                        (string) ($item['id'] ?? ''),
                        (string) ($item['page'] ?? ''),
                        (string) ($item['url'] ?? ''),
                    ];
                    $keys = array_merge($keys, PageRegistry::availabilityKeys((string)($item['page']??'')));
                    return !$this->anyDisabled($keys);
                }
            ));
        }
        unset($group);

        return array_values(array_filter(
            $groups,
            static fn(array $group): bool => ($group['items'] ?? []) !== []
        ));
    }

    /** Возвращает код отключённой страницы для текущего запроса. */
    public function disabledPageForRequest(ServerRequestInterface $request): ?string
    {
        if (!$this->hasDisabledPages()) {
            return null;
        }

        $path = $request->getUri()->getPath();
        if ($this->basePath !== '' && str_starts_with($path, $this->basePath)) {
            $path = substr($path, strlen($this->basePath));
        }
        $path = '/' . ltrim($path, '/');

        // В конфиге разрешено указать конкретный путь или его начало.
        foreach ($this->disabledPages as $disabledPage) {
            if (!str_starts_with($disabledPage, '/')) {
                continue;
            }
            if ($path === $disabledPage || str_starts_with($path, rtrim($disabledPage, '/') . '/')) {
                return $disabledPage;
            }
        }

        $keys = PageRegistry::availabilityKeysForPath($path, $request->getQueryParams());
        foreach ($keys as $key) {
            if ($this->isDisabled($key)) {
                return $key;
            }
        }

        return null;
    }

    private function anyDisabled(array $keys): bool
    {
        foreach ($keys as $key) {
            if ($key !== '' && $this->isDisabled($key)) {
                return true;
            }
            if (str_starts_with($key, '/') && $this->isDisabled(rtrim($key, '/'))) {
                return true;
            }
        }
        return false;
    }
}
