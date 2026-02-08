<?php

declare(strict_types=1);

namespace Darkwood\IaExceptionBundle\EventSubscriber;

use Darkwood\IaExceptionBundle\Model\ExceptionAiAnalysis;
use Darkwood\IaExceptionBundle\Service\ExceptionAiAnalyzer;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Twig\Environment;

/**
 * Listens to KernelEvents::EXCEPTION and augments 500 errors with AI analysis when enabled.
 * Falls back to default Symfony behavior on any failure. Never prevents 500 response.
 */
final class IaExceptionSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ExceptionAiAnalyzer $analyzer,
        private readonly Environment $twig,
        private readonly bool $enabled,
        /** @var list<int> */
        private readonly array $onlyStatusCodes,
        private readonly ?string $errorIdGenerator = null,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException', 0],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        if (!$this->enabled) {
            return;
        }

        $throwable = $event->getThrowable();
        $request = $event->getRequest();

        // Resolve HTTP status – use flatten exception when available
        $statusCode = $this->resolveStatusCode($throwable, $request);
        if (!\in_array($statusCode, $this->onlyStatusCodes, true)) {
            return;
        }

        $analysis = null;
        try {
            $analysis = $this->analyzer->analyze($throwable);
        } catch (\Throwable) {
            // Fallback: do not set response, let Symfony handle it
            return;
        }

        if ($analysis === null) {
            return;
        }

        $errorId = $this->generateErrorId($request);
        $wantsJson = $this->wantsJson($request);

        try {
            if ($wantsJson) {
                $response = $this->createJsonResponse($analysis, $errorId, $statusCode);
            } else {
                $response = $this->createHtmlResponse($analysis, $errorId, $statusCode, $throwable);
            }
            $event->setResponse($response);
        } catch (\Throwable) {
            // Fallback: do not set response
        }
    }

    private function getStatusText(int $statusCode): string
    {
        return match ($statusCode) {
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            500 => 'Internal Server Error',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            default => 'Error',
        };
    }

    private function resolveStatusCode(\Throwable $throwable, Request $request): int
    {
        if (class_exists(\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface::class)
            && $throwable instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) {
            return $throwable->getStatusCode();
        }

        return 500;
    }

    private function wantsJson(Request $request): bool
    {
        $accept = $request->headers->get('Accept', '');
        return str_contains($accept, 'application/json');
    }

    private function generateErrorId(Request $request): string
    {
        if ($this->errorIdGenerator !== null && $request->attributes->has('_controller')) {
            // Custom generator would be injected as a callable – for MVP we use a simple ID
        }
        return bin2hex(random_bytes(8));
    }

    private function createJsonResponse(
        ExceptionAiAnalysis $analysis,
        string $errorId,
        int $statusCode
    ): JsonResponse {
        $payload = [
            'error_id' => $errorId,
            'english_exception' => $analysis->englishException,
            'probable_causes' => $analysis->probableCauses,
            'suggested_fixes' => $analysis->suggestedFixes,
            'confidence' => $analysis->confidence,
        ];

        return new JsonResponse($payload, $statusCode, [
            'Content-Type' => 'application/json',
        ]);
    }

    private function createHtmlResponse(
        ExceptionAiAnalysis $analysis,
        string $errorId,
        int $statusCode,
        \Throwable $throwable
    ): Response {
        $content = $this->twig->render('@DarkwoodIaException/error500.html.twig', [
            'error_id' => $errorId,
            'analysis' => $analysis,
            'exception_class' => $throwable::class,
            'exception_message' => $throwable->getMessage(),
            'status_code' => $statusCode,
            'status_text' => $this->getStatusText($statusCode),
        ]);

        return new Response($content, $statusCode, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);
    }
}
