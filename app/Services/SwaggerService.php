<?php

namespace App\Services;

use App\Docs\RequestHeader;
use App\Helpers\Version;
use Arr;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use ReflectionClass;
use ReflectionException;
use Str;
use Symfony\Component\HttpFoundation\Response;

class SwaggerService
{
    private static array $data = [];
    private static bool $fired = false;

    private array $parameters = [];

    public function __construct(private readonly Request $request, private readonly Response $response)
    {
        self::$fired = true;
    }

    public function processData(): void
    {
        $this->processMiddlewares();
        $this->processHeadersExamples();

        if (!Arr::exists(self::$data, $this->getCollectionPath())) {
            Arr::set(self::$data, $this->getCollectionPath(), [
            'deprecated' => $this->request->route()->action['meta']['deprecated'] ?? false,
            'operationId' => $this->request->route()->getName(),
            'responses' => [],
            'parameters' => $this->parameters,
            ]);
        }

        $currentResponsePath = sprintf(
            '%s.responses.%s',
            $this->getCollectionPath(),
            $this->response->getStatusCode(),
        );

        $response = Arr::exists(self::$data, $currentResponsePath) ? Arr::get(self::$data, $currentResponsePath) : [];

        $response['headers'] = array_merge(
            $response['headers'] ?? [],
            Arr::except(
                $this->response->headers->all(),
                ['date', 'content-type']
            ),
        );

        $contentType = $this->response->headers->get('Content-Type');

        if (isset($response['content'][$contentType]['examples'])) {
            $response['content'][$contentType]['examples'][Str::uuid()->getUrn()] = [
                'value' => $this->response->getContent()
            ];
        } elseif (!isset($response['content'][$contentType])) {
            $response['content'][$contentType] = ['example' => ['value' =>$this->response->getContent()]];
        } else {
            $response['content'][$contentType]['examples'][Str::uuid()->getUrn()] = [
                'value' => $this->response->getContent()
            ];

            unset($response['content'][$contentType]['example']);
        }

        Arr::set(self::$data, $currentResponsePath, $response);
    }

    public static function dumpData(): void
    {
        if (!self::$fired) {
            return;
        }

        file_put_contents(storage_path('/documentation.json'), json_encode(array_merge([
            'paths' => self::$data
        ], self::getSwaggerHeader())));
    }

    private static function getSwaggerHeader(): array
    {
        return [
            'openapi' => '3.0.1',
            'info' => [
                'title' => 'Cattr API Documentation',
                'contact' => [
                    'name' => 'Amazingcat LLC',
                    'email' => 'hi@cattr.app',
                ],
                'version' => (string) app(Version::class),
            ],
            'servers' => [
                [
                    'url' => 'http://localhost:8000',
                    'description' => 'Local server served by Artisan',
                ],
                [
                    'url' => 'https://demo.cattr.app/api',
                    'description' => 'Demo Cattr server',
                ],
            ],
        ];
    }

    private function processMiddlewares(): void
    {
        $router = app(Router::class);
        $middlewares = $router->resolveMiddleware(
            array_unique(
                array_map(
                    static fn($e) => explode(':', $e, 2)[0],
                    array_merge($router->getMiddleware(), $this->request->route()->middleware()),
                )
            )
        );

        foreach ($middlewares as $middleware) {
            $this->parameters = array_merge(
                $this->parameters,
                rescue(
                    /**
                    * @throws ReflectionException
                    */
                    static fn() =>
                        array_map(
                            static fn($el) => $el->newInstance()->dump(),
                            (new ReflectionClass($middleware))->getAttributes(RequestHeader::class),
                        ),
                    [],
                )
            );
        }
    }

    private function getCollectionPath(): string
    {
        return sprintf('/%s.%s', $this->request->route()?->uri(), strtolower($this->request->method()));
    }

    private function processHeadersExamples(): void
    {
        foreach ($this->request->headers as $header => $value) {
            var_dump($header, $value);
        }
        die;
    }
}
