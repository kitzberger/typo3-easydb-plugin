<?php
declare(strict_types=1);

namespace Easydb\Typo3Integration\Backend;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Exception;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class AjaxDispatcher implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    private const requestHandlers = [
        DefaultRequestHandler::class,
        CorsRequestHandler::class,
    ];

    public function dispatchRequest(ServerRequestInterface $request, ?ResponseInterface $response = null): ResponseInterface
    {
        if ($response === null) {
            $response = new JsonResponse();
        }
        foreach ($this->resolveRequestHandlers($request) as $requestHandler) {
            $response = $requestHandler->handleRequest($request, $response);
        }

        return $response;
    }

    /**
     * Fetches the request handler that suits the best based on the priority and the interface
     *
     * @param ServerRequestInterface $request
     * @throws Exception
     * @return RequestHandlerInterface[]
     */
    private function resolveRequestHandlers(ServerRequestInterface $request): array
    {
        $suitableRequestHandlers = [];
        foreach (self::requestHandlers as $requestHandlerClassName) {
            /** @var DefaultRequestHandler|CorsRequestHandler $requestHandler */
            $requestHandler = GeneralUtility::makeInstance($requestHandlerClassName);
            if ($requestHandler->canHandleRequest($request)) {
                $priority = $requestHandler->getPriority();
                if (isset($suitableRequestHandlers[$priority])) {
                    $this->logger->error('easydb: Multiple request handlers share the same priority', [
                        'priority' => $priority,
                        'handler' => $requestHandlerClassName,
                    ]);
                    throw new Exception('More than one request handler with the same priority can handle the request, but only one handler may be active at a time!', 1176471352);
                }
                $suitableRequestHandlers[$priority] = $requestHandler;
            }
        }
        if (empty($suitableRequestHandlers)) {
            $this->logger->error('easydb: No suitable request handler found', [
                'method' => $request->getMethod(),
                'uri' => (string)$request->getUri(),
            ]);
            throw new Exception('No suitable request handler found.', 1225418233);
        }
        ksort($suitableRequestHandlers);
        return $suitableRequestHandlers;
    }
}
