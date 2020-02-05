<?php
namespace App\Http;

use App\Exception;
use App\Settings;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Slim\App;
use Slim\Exception\HttpException;
use stdClass;
use Throwable;

class ErrorHandler extends \Slim\Handlers\ErrorHandler
{
    /** @var LoggerInterface */
    protected $logger;

    /** @var Settings */
    protected $settings;

    /** @var bool */
    protected $returnJson = false;

    /** @var bool */
    protected $showDetailed = false;

    /** @var string */
    protected $loggerLevel = LogLevel::ERROR;

    public function __construct(
        App $app,
        LoggerInterface $logger,
        Settings $settings
    ) {
        $this->logger = $logger;
        $this->settings = $settings;

        parent::__construct($app->getCallableResolver(), $app->getResponseFactory());
    }

    public function __invoke(
        ServerRequestInterface $request,
        Throwable $exception,
        bool $displayErrorDetails,
        bool $logErrors,
        bool $logErrorDetails
    ): ResponseInterface {
        if ($exception instanceof Exception) {
            $this->loggerLevel = $exception->getLoggerLevel();
        } elseif ($exception instanceof HttpException) {
            $this->loggerLevel = LogLevel::WARNING;
        }

        $this->showDetailed = (!$this->settings->isProduction() && !in_array($this->loggerLevel,
                [LogLevel::DEBUG, LogLevel::INFO, LogLevel::NOTICE], true));
        $this->returnJson = $this->_shouldReturnJson($request);

        return parent::__invoke($request, $exception, $displayErrorDetails, $logErrors, $logErrorDetails);
    }

    /**
     * @return bool
     */
    public function returnJson(): bool
    {
        return $this->returnJson;
    }

    /**
     * @param bool $returnJson
     */
    public function setReturnJson(bool $returnJson): void
    {
        $this->returnJson = $returnJson;
    }

    /**
     * @return bool
     */
    public function showDetailed(): bool
    {
        return $this->showDetailed;
    }

    /**
     * @param bool $showDetailed
     */
    public function setShowDetailed(bool $showDetailed): void
    {
        $this->showDetailed = $showDetailed;
    }

    /**
     * @param ServerRequestInterface $req
     *
     * @return bool
     */
    protected function _shouldReturnJson(ServerRequestInterface $req): bool
    {
        $xhr = $req->getHeaderLine('X-Requested-With') === 'XMLHttpRequest';

        if ($xhr || $this->settings->isCli() || $this->settings->isTesting()) {
            return true;
        }

        if ($req->hasHeader('Accept')) {
            $accept = $req->getHeader('Accept');
            if (in_array('application/json', $accept)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    protected function writeToErrorLog(): void
    {
        $context = [
            'file' => $this->exception->getFile(),
            'line' => $this->exception->getLine(),
            'code' => $this->exception->getCode(),
        ];

        if ($this->exception instanceof Exception) {
            $context['context'] = $this->exception->getLoggingContext();
            $context = array_merge($context, $this->exception->getExtraData());
        }

        if ($this->showDetailed) {
            $context['trace'] = array_slice($this->exception->getTrace(), 0, 5);
        }

        $this->logger->log($this->loggerLevel, $this->exception->getMessage(), [
            'file' => $this->exception->getFile(),
            'line' => $this->exception->getLine(),
            'code' => $this->exception->getCode(),
        ]);
    }

    /**
     * @inheritDoc
     */
    protected function respond(): ResponseInterface
    {
        // Special handling for cURL requests.
        $ua = $this->request->getHeaderLine('User-Agent');

        if (false !== stripos($ua, 'curl')) {
            $response = $this->responseFactory->createResponse($this->statusCode);

            $response->getBody()
                ->write('Error: ' . $this->exception->getMessage() . ' on ' . $this->exception->getFile() . ' L' . $this->exception->getLine());

            return $response;
        }

        if ($this->returnJson) {
            $api_response = $this->getErrorApiResponse(
                $this->exception->getCode(),
                $this->exception->getMessage(),
                ($this->showDetailed) ? $this->exception->getTrace() : []
            );

            return $this->withJson(
                $this->responseFactory->createResponse(500),
                $api_response
            );
        }

        return parent::respond();
    }

    /**
     * @param int $code
     * @param string $message
     * @param array $stack_trace
     *
     * @return stdClass
     */
    protected function getErrorApiResponse($code = 500, $message = 'General Error', $stack_trace = []): stdClass
    {
        $api = new stdClass;
        $api->success = false;
        $api->code = (int)$code;
        $api->message = (string)$message;
        $api->stack_trace = (array)$stack_trace;

        return $api;
    }

    protected function withJson(ResponseInterface $response, $data): ResponseInterface
    {
        $json = (string)json_encode($data);
        $response->getBody()->write($json);

        return $response->withHeader('Content-Type', 'application/json;charset=utf-8');
    }

}
