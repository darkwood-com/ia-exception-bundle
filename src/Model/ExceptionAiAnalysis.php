<?php

declare(strict_types=1);

namespace Darkwood\IaExceptionBundle\Model;

/**
 * DTO for AI-generated exception analysis.
 */
final readonly class ExceptionAiAnalysis
{
    public function __construct(
        public string $englishException,
        /** @var list<string> */
        public array $probableCauses,
        /** @var list<string> */
        public array $suggestedFixes,
        public float $confidence,
        public string $safeLogSummary,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'english_exception' => $this->englishException,
            'probable_causes' => $this->probableCauses,
            'suggested_fixes' => $this->suggestedFixes,
            'confidence' => $this->confidence,
            'safe_log_summary' => $this->safeLogSummary,
        ];
    }
}
