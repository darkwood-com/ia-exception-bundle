<?php

declare(strict_types=1);

namespace Darkwood\IaExceptionBundle\Service;

use Darkwood\IaExceptionBundle\Model\ExceptionAiAnalysis;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

/**
 * Analyzes exceptions via Symfony AI Agent. All failures fallback silently.
 */
final class ExceptionAiAnalyzer
{
    private const SYSTEM_PROMPT = <<<'PROMPT'
You are an exception analysis assistant. Return ONLY valid JSON. No markdown, no prose, no code blocks.
Never include secrets or sensitive data. Use probabilistic language (e.g. "may", "might", "possibly").
These are hypotheses, not guarantees.

JSON schema:
{
  "english_exception": "string - clear human-readable explanation of the error",
  "probable_causes": ["string", "..."],
  "suggested_fixes": ["string", "..."],
  "confidence": 0.0-1.0,
  "safe_log_summary": "string - brief summary safe for logs, no secrets"
}
PROMPT;

    public function __construct(
        private readonly AgentInterface $agent,
        private readonly CacheItemPoolInterface $cache,
        private readonly int $cacheTtl,
        private readonly int $timeoutMs,
        private readonly bool $includeTrace,
    ) {
    }

    /**
     * Returns AI analysis or null on any failure (timeout, parse error, etc).
     */
    public function analyze(\Throwable $exception): ?ExceptionAiAnalysis
    {
        $fingerprint = $this->buildFingerprint($exception);

        if ($this->cacheTtl > 0) {
            $cached = $this->cache->getItem($fingerprint);
            if ($cached->isHit()) {
                $data = $cached->get();
                return $this->hydrate($data);
            }
        }

        try {
            $analysis = $this->callAgent($exception);
        } catch (\Throwable $e) {
            return null;
        }

        if ($analysis !== null && $this->cacheTtl > 0) {
            $item = $this->cache->getItem($fingerprint);
            $item->set($analysis->toArray());
            $item->expiresAfter($this->cacheTtl);
            $this->cache->save($item);
        }

        return $analysis;
    }

    private function buildFingerprint(\Throwable $exception): string
    {
        $parts = [
            $exception::class,
            $exception->getMessage(),
        ];

        if ($this->includeTrace) {
            $trace = $exception->getTraceAsString();
            $top = implode("\n", array_slice(explode("\n", $trace), 0, 10));
            $parts[] = hash('xxh128', $top);
        }

        return 'darkwood_ia_exception_' . hash('xxh128', implode("\0", $parts));
    }

    private function callAgent(\Throwable $exception): ?ExceptionAiAnalysis
    {
        $userContent = $this->buildUserContent($exception);

        $messages = new MessageBag(
            Message::forSystem(self::SYSTEM_PROMPT),
            Message::ofUser($userContent)
        );

        // Timeout enforced via AI platform's http_client; configure timeout there
        $result = $this->agent->call($messages);

        $content = $result->getContent();
        if (!\is_string($content) || trim($content) === '') {
            return null;
        }

        $content = $this->extractJson($content);
        $data = json_decode($content, true);
        if (!\is_array($data)) {
            return null;
        }

        return $this->hydrate($data);
    }

    private function buildUserContent(\Throwable $exception): string
    {
        $parts = [
            'Exception: ' . $exception::class,
            'Message: ' . $exception->getMessage(),
            'File: ' . $exception->getFile(),
            'Line: ' . (string) $exception->getLine(),
        ];

        if ($this->includeTrace) {
            $parts[] = 'Trace:';
            $parts[] = $exception->getTraceAsString();
        }

        return implode("\n", $parts);
    }

    private function extractJson(string $content): string
    {
        $content = trim($content);
        if (str_starts_with($content, '```')) {
            $content = preg_replace('/^```\w*\s*/', '', $content);
            $content = preg_replace('/\s*```\s*$/', '', $content);
        }
        return trim($content);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function hydrate(array $data): ?ExceptionAiAnalysis
    {
        $englishException = $data['english_exception'] ?? '';
        $probableCauses = $data['probable_causes'] ?? [];
        $suggestedFixes = $data['suggested_fixes'] ?? [];
        $confidence = isset($data['confidence']) ? (float) $data['confidence'] : 0.0;
        $safeLogSummary = $data['safe_log_summary'] ?? '';

        if (!\is_string($englishException) || $englishException === '') {
            return null;
        }
        if (!\is_array($probableCauses)) {
            $probableCauses = [];
        }
        $probableCauses = array_values(array_filter(array_map('strval', $probableCauses)));
        if (!\is_array($suggestedFixes)) {
            $suggestedFixes = [];
        }
        $suggestedFixes = array_values(array_filter(array_map('strval', $suggestedFixes)));
        $confidence = max(0, min(1, $confidence));
        if (!\is_string($safeLogSummary)) {
            $safeLogSummary = '';
        }

        return new ExceptionAiAnalysis(
            englishException: $englishException,
            probableCauses: $probableCauses,
            suggestedFixes: $suggestedFixes,
            confidence: $confidence,
            safeLogSummary: $safeLogSummary ?: $englishException
        );
    }
}
