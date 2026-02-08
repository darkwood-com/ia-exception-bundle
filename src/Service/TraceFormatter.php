<?php

declare(strict_types=1);

namespace Darkwood\IaExceptionBundle\Service;

use Symfony\Component\ErrorHandler\Exception\FlattenException;

/**
 * Formats exception traces for display in Symfony-style HTML.
 */
final class TraceFormatter
{
    public function __construct(
        private readonly ?object $fileLinkFormat = null,
    ) {
    }

    /**
     * @return array<int, array{class: string, message: string, trace: array<int, array{method_call: string, file: ?string, line: ?int, file_link: string|false, code_excerpt: string, is_vendor: bool, style: string}>}>
     */
    public function format(FlattenException $exception): array
    {
        $result = [];
        foreach ($exception->toArray() as $index => $e) {
            $trace = [];
            foreach ($e['trace'] as $i => $frame) {
                $methodCall = $this->formatMethodCall($frame);
                $file = $frame['file'] ?? null;
                $line = $frame['line'] ?? null;
                $fileLink = false;
                if ($file && $line && $this->fileLinkFormat && method_exists($this->fileLinkFormat, 'format')) {
                    $fileLink = $this->fileLinkFormat->format($file, $line) ?: false;
                }
                $codeExcerpt = $file && $line ? $this->getFileExcerpt($file, $line) : '';
                $isVendor = $file && (str_contains($file, '/vendor/') || str_contains($file, '/var/cache/'));

                $style = 'compact';
                if (!$isVendor && $methodCall) {
                    $style = $i === 0 ? 'expanded' : '';
                }

                $trace[] = [
                    'method_call' => $methodCall,
                    'file' => $file,
                    'line' => $line,
                    'file_link' => $fileLink,
                    'code_excerpt' => $codeExcerpt,
                    'is_vendor' => $isVendor,
                    'style' => $style,
                ];
            }

            $result[] = [
                'class' => $e['class'],
                'message' => $e['message'],
                'trace' => $trace,
            ];
        }

        return $result;
    }

    /**
     * @param array{class?: string, type?: string, function?: string, args?: array} $frame
     */
    private function formatMethodCall(array $frame): string
    {
        $class = $frame['class'] ?? '';
        $type = $frame['type'] ?? '';
        $function = $frame['function'] ?? '';
        if (!$function) {
            return '';
        }
        $args = isset($frame['args']) ? $this->formatArgsAsText($frame['args']) : '';
        $raw = $class . $type . $function . '(' . $args . ')';
        return htmlspecialchars($raw, \ENT_COMPAT | \ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * @param array<int|string, mixed> $args
     */
    private function formatArgsAsText(array $args): string
    {
        $parts = [];
        foreach ($args as $key => $item) {
            if (!\is_array($item) || !isset($item[0], $item[1])) {
                $parts[] = '…';
                continue;
            }
            [$type, $value] = $item;
            $formatted = match ($type) {
                'object' => 'object(' . (\is_string($value) ? $value : '?') . ')',
                'array' => 'array(' . (\is_array($value) ? $this->formatArgsAsText($value) : (string) $value) . ')',
                'null' => 'null',
                'boolean' => var_export($value, true),
                'integer', 'float' => (string) $value,
                'string' => "'" . addslashes(substr((string) $value, 0, 50)) . (strlen((string) $value) > 50 ? '…' : '') . "'",
                default => '…',
            };
            $parts[] = \is_int($key) ? $formatted : "'" . $key . "' => " . $formatted;
        }
        return implode(', ', $parts);
    }

    private function getFileExcerpt(string $file, int $line, int $context = 5): string
    {
        if (!is_file($file) || !is_readable($file)) {
            return '';
        }
        $content = @file_get_contents($file);
        if (false === $content) {
            return '';
        }
        $lines = explode("\n", $content);
        $start = max($line - $context, 1);
        $end = min($line + $context, \count($lines));
        $out = [];
        for ($i = $start; $i <= $end; $i++) {
            $escaped = htmlspecialchars($lines[$i - 1] ?? '', \ENT_COMPAT | \ENT_SUBSTITUTE, 'UTF-8');
            $selected = $i === $line ? ' class="selected"' : '';
            $out[] = '<li' . $selected . '><code>' . $escaped . '</code></li>';
        }
        return '<ol start="' . $start . '">' . implode("\n", $out) . '</ol>';
    }
}
