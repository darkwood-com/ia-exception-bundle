<?php

declare(strict_types=1);

namespace Darkwood\IaExceptionBundle\Service;

use Darkwood\IaExceptionBundle\Model\ExceptionContext;
use Psr\Cache\CacheItemPoolInterface;

use function is_array;

/**
 * Stores and retrieves exception context in cache for async AI analysis (keyed by error_id).
 */
final class ExceptionContextStore
{
    private const CACHE_KEY_PREFIX = 'darkwood_ia_exception_context_';

    public function __construct(
        private readonly CacheItemPoolInterface $cache,
        private readonly int $ttlSeconds,
    ) {}

    public function store(string $errorId, ExceptionContext $context): void
    {
        $item = $this->cache->getItem(self::CACHE_KEY_PREFIX . $errorId);
        $item->set($context->toArray());
        $item->expiresAfter($this->ttlSeconds);
        $this->cache->save($item);
    }

    public function get(string $errorId): ?ExceptionContext
    {
        $item = $this->cache->getItem(self::CACHE_KEY_PREFIX . $errorId);
        if (!$item->isHit()) {
            return null;
        }
        $data = $item->get();
        if (!is_array($data)) {
            return null;
        }

        return ExceptionContext::fromArray($data);
    }
}
