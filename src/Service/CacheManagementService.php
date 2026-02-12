<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class CacheManagementService
{
    private const SCAN_BATCH_SIZE = 200;
    private const MAX_KEYS_PER_POOL = 1000;

    public function __construct(
        #[Autowire(service: 'cache.app')]
        private readonly AdapterInterface $appCachePool,
        #[Autowire(service: 'doctrine.result_cache_pool')]
        private readonly AdapterInterface $doctrineResultCachePool,
        #[Autowire(service: 'cache.default_redis_provider')]
        private readonly object $redisConnection,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public function getPoolChoices(): array
    {
        $choices = [];

        foreach ($this->getManagedPools() as $poolName => $poolData) {
            $choices[$poolName] = $poolData['label'];
        }

        return $choices;
    }

    /**
     * @return list<array{pool: string, poolLabel: string, rawKey: string, displayKey: string, ttl: int}>
     */
    public function listCacheKeys(?string $poolName = null, string $query = ''): array
    {
        $normalizedQuery = strtolower(trim($query));
        $rows = [];

        foreach ($this->selectPools($poolName) as $poolData) {
            $namespace = $poolData['namespace'];
            if ($namespace === '') {
                continue;
            }

            $rawKeys = $this->scanByPattern($namespace . '*', self::MAX_KEYS_PER_POOL);
            sort($rawKeys, \SORT_STRING);

            foreach ($rawKeys as $rawKey) {
                $displayKey = $this->extractDisplayKey($rawKey, $namespace);

                if ($normalizedQuery !== '') {
                    $haystack = strtolower($displayKey . ' ' . $rawKey);
                    if (!str_contains($haystack, $normalizedQuery)) {
                        continue;
                    }
                }

                $rows[] = [
                    'pool' => $poolData['name'],
                    'poolLabel' => $poolData['label'],
                    'rawKey' => $rawKey,
                    'displayKey' => $displayKey,
                    'ttl' => $this->resolveTtl($rawKey),
                ];
            }
        }

        return $rows;
    }

    /**
     * @param list<string> $rawKeys
     */
    public function deleteRawKeys(array $rawKeys): int
    {
        $keys = $this->filterManagedKeys($rawKeys);
        if ($keys === []) {
            return 0;
        }

        $deleted = $this->deleteUsingCommand('unlink', $keys);
        if ($deleted > 0) {
            return $deleted;
        }

        return $this->deleteUsingCommand('del', $keys);
    }

    public function clearPools(?string $poolName = null): int
    {
        $cleared = 0;

        foreach ($this->selectPools($poolName) as $poolData) {
            if ($poolData['pool']->clear()) {
                ++$cleared;
            }
        }

        return $cleared;
    }

    /**
     * @return array<string, array{name: string, label: string, pool: AdapterInterface, namespace: string}>
     */
    private function getManagedPools(): array
    {
        $pools = [
            'cache.app' => [
                'name' => 'cache.app',
                'label' => 'Application Cache',
                'pool' => $this->appCachePool,
            ],
            'doctrine.result_cache_pool' => [
                'name' => 'doctrine.result_cache_pool',
                'label' => 'Doctrine Result Cache',
                'pool' => $this->doctrineResultCachePool,
            ],
        ];

        foreach ($pools as $poolName => $poolData) {
            $pools[$poolName]['namespace'] = $this->resolveRootNamespace($poolData['pool']) ?? '';
        }

        return $pools;
    }

    /**
     * @return array<string, array{name: string, label: string, pool: AdapterInterface, namespace: string}>
     */
    private function selectPools(?string $poolName): array
    {
        $allPools = $this->getManagedPools();
        $normalizedPool = trim((string) $poolName);

        if ($normalizedPool !== '' && isset($allPools[$normalizedPool])) {
            return [$normalizedPool => $allPools[$normalizedPool]];
        }

        return $allPools;
    }

    private function resolveRootNamespace(AdapterInterface $pool): ?string
    {
        $namespace = $this->readObjectProperty($pool, 'rootNamespace');
        if (!is_string($namespace) || $namespace === '') {
            return null;
        }

        return $namespace;
    }

    private function readObjectProperty(object $object, string $propertyName): mixed
    {
        $reflection = new \ReflectionObject($object);

        while ($reflection instanceof \ReflectionClass) {
            if ($reflection->hasProperty($propertyName)) {
                $property = $reflection->getProperty($propertyName);
                $property->setAccessible(true);

                return $property->getValue($object);
            }

            $reflection = $reflection->getParentClass();
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function scanByPattern(string $pattern, int $limit): array
    {
        if (class_exists(\Predis\ClientInterface::class) && $this->redisConnection instanceof \Predis\ClientInterface) {
            return $this->scanByPatternWithPredis($pattern, $limit);
        }

        return $this->scanByPatternWithRedisExtension($pattern, $limit);
    }

    /**
     * @return list<string>
     */
    private function scanByPatternWithPredis(string $pattern, int $limit): array
    {
        $keys = [];
        $cursor = '0';

        do {
            $response = $this->redisConnection->scan($cursor, 'MATCH', $pattern, 'COUNT', self::SCAN_BATCH_SIZE);
            if (!is_array($response) || count($response) !== 2) {
                break;
            }

            $cursor = (string) $response[0];
            $batch = is_array($response[1]) ? $response[1] : [];

            foreach ($batch as $key) {
                if (!is_string($key) || $key === '') {
                    continue;
                }

                $keys[$key] = true;
                if (count($keys) >= $limit) {
                    break 2;
                }
            }
        } while ($cursor !== '0');

        return array_values(array_keys($keys));
    }

    /**
     * @return list<string>
     */
    private function scanByPatternWithRedisExtension(string $pattern, int $limit): array
    {
        $keys = [];
        $cursor = null;

        do {
            $batch = $this->redisConnection->scan($cursor, $pattern, self::SCAN_BATCH_SIZE);

            if ($batch === false || $batch === null) {
                if ($cursor === null || $cursor === 0 || $cursor === '0') {
                    break;
                }

                continue;
            }

            foreach ($batch as $key) {
                if (!is_string($key) || $key === '') {
                    continue;
                }

                $keys[$key] = true;
                if (count($keys) >= $limit) {
                    break 2;
                }
            }
        } while ($cursor !== 0 && $cursor !== '0');

        return array_values(array_keys($keys));
    }

    private function extractDisplayKey(string $rawKey, string $namespace): string
    {
        if (!str_starts_with($rawKey, $namespace)) {
            return $rawKey;
        }

        $suffix = substr($rawKey, strlen($namespace));
        if ($suffix === '' || $suffix === false) {
            return $rawKey;
        }

        $parts = explode(':', $suffix, 2);

        return count($parts) === 2 ? $parts[1] : $suffix;
    }

    private function resolveTtl(string $rawKey): int
    {
        try {
            $ttl = $this->redisConnection->ttl($rawKey);
        } catch (\Throwable) {
            return -3;
        }

        if (is_int($ttl)) {
            return $ttl;
        }

        if (is_string($ttl) && is_numeric($ttl)) {
            return (int) $ttl;
        }

        return -3;
    }

    /**
     * @param list<string> $rawKeys
     * @return list<string>
     */
    private function filterManagedKeys(array $rawKeys): array
    {
        $allowedNamespaces = array_values(array_filter(array_map(
            static fn (array $poolData): string => $poolData['namespace'],
            $this->getManagedPools(),
        )));

        $normalized = [];
        foreach ($rawKeys as $rawKey) {
            $key = trim($rawKey);
            if ($key === '') {
                continue;
            }

            foreach ($allowedNamespaces as $namespace) {
                if (str_starts_with($key, $namespace)) {
                    $normalized[$key] = true;
                    break;
                }
            }
        }

        return array_values(array_keys($normalized));
    }

    /**
     * @param list<string> $keys
     */
    private function deleteUsingCommand(string $command, array $keys): int
    {
        if ($keys === [] || !method_exists($this->redisConnection, $command)) {
            return 0;
        }

        try {
            $deleted = $this->redisConnection->{$command}(...$keys);
            if (is_int($deleted)) {
                return $deleted;
            }
            if (is_string($deleted) && is_numeric($deleted)) {
                return (int) $deleted;
            }
        } catch (\Throwable) {
        }

        try {
            $deleted = $this->redisConnection->{$command}($keys);
            if (is_int($deleted)) {
                return $deleted;
            }
            if (is_string($deleted) && is_numeric($deleted)) {
                return (int) $deleted;
            }
        } catch (\Throwable) {
        }

        return 0;
    }
}
