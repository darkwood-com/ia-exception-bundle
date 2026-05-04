<?php

declare(strict_types=1);

namespace Darkwood\IaExceptionBundle\Controller;

use Darkwood\IaExceptionBundle\Service\ExceptionAiAnalyzer;
use Darkwood\IaExceptionBundle\Service\ExceptionContextStore;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

/**
 * Serves async AI analysis for a given error_id (context must have been stored by the exception subscriber).
 */
final class AiExceptionController
{
    public function __construct(
        private readonly ExceptionContextStore $contextStore,
        private readonly ExceptionAiAnalyzer $analyzer,
        private readonly Environment $twig,
        private readonly bool $enabled,
    ) {
    }

    public function __invoke(Request $request, string $error_id): Response
    {
        if (!$this->enabled) {
            return new JsonResponse(['error' => 'AI exception analysis is disabled.'], Response::HTTP_FORBIDDEN);
        }

        $context = $this->contextStore->get($error_id);
        if ($context === null) {
            return new JsonResponse(['error' => 'Unknown or expired error context.'], Response::HTTP_NOT_FOUND);
        }

        $analysis = $this->analyzer->analyzeFromContext($context);
        if ($analysis === null) {
            $html = $this->renderFallbackHtml($error_id);
            return new Response($html, Response::HTTP_OK, [
                'Content-Type' => 'text/html; charset=UTF-8',
            ]);
        }

        $accept = $request->headers->get('Accept', '');
        if (str_contains($accept, 'text/html')) {
            $html = $this->twig->render('@DarkwoodIaException/_ai_analysis_block.html.twig', [
                'analysis' => $analysis,
                'error_id' => $error_id,
            ]);
            return new Response($html, Response::HTTP_OK, [
                'Content-Type' => 'text/html; charset=UTF-8',
            ]);
        }

        return new JsonResponse([
            'error_id' => $error_id,
            'english_exception' => $analysis->englishException,
            'probable_causes' => $analysis->probableCauses,
            'suggested_fixes' => $analysis->suggestedFixes,
            'confidence' => $analysis->confidence,
        ], Response::HTTP_OK);
    }

    private function renderFallbackHtml(string $errorId): string
    {
        return $this->twig->render('@DarkwoodIaException/_ai_analysis_fallback.html.twig', [
            'error_id' => $errorId,
        ]);
    }
}
