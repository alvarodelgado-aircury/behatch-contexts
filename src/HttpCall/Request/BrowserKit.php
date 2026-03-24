<?php
declare(strict_types=1);

namespace Behatch\HttpCall\Request;

use Behat\Mink\Mink;
use Symfony\Component\BrowserKit\AbstractBrowser;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class BrowserKit
{
    /**
     * Headers merged into each request. Stored here because BrowserKitDriver does not
     * expose a reliable way to read them back from the client after reset/session changes.
     *
     * @var array<string, string>
     */
    private array $requestHeaders = [];

    protected Mink $mink;

    public function __construct(Mink $mink)
    {
        $this->mink = $mink;
    }

    public function getMethod(): string
    {
        return $this->getRequest()->getMethod();
    }

    public function getUri(): string
    {
        return $this->getRequest()->getUri();
    }

    /**
     * @return array<string, mixed>
     */
    public function getServer(): array
    {
        return $this->getRequest()->getServer();
    }

    /**
     * @return array<string, mixed>
     */
    public function getParameters(): array
    {
        return $this->getRequest()->getParameters();
    }

    protected function getRequest(): object
    {
        $client = $this->mink->getSession()->getDriver()->getClient();
        if (\method_exists($client, 'getInternalRequest')) {
            return $client->getInternalRequest();
        }

        return $client->getRequest();
    }

    public function getContent(): string
    {
        return $this->mink->getSession()->getPage()->getContent();
    }

    /**
     * @param array<string, mixed> $parameters
     * @param array<string, mixed> $files
     * @param array<string, string> $headers Server parameters for the request (e.g. HTTP_* headers)
     */
    public function send(
        string $method,
        string $url,
        array $parameters = [],
        array $files = [],
        ?string $content = null,
        array $headers = []
    ): \Behat\Mink\Element\DocumentElement {
        foreach ($files as &$file) {
            if (\is_string($file)) {
                $file = new UploadedFile($file, basename($file));
            }
        }
        unset($file);

        $files = $this->normalizeFilesForHttpBrowser($files);

        $client = $this->mink->getSession()->getDriver()->getClient();
        if (!$client instanceof AbstractBrowser) {
            throw new \RuntimeException(\sprintf('Expected %s, got %s.', AbstractBrowser::class, $client::class));
        }

        $mergedHeaders = \array_merge($headers, $this->requestHeaders);

        $client->followRedirects(false);
        $client->request($method, $url, $parameters, $files, $mergedHeaders, $content);
        $client->followRedirects(true);
        $this->resetHttpHeaders();

        return $this->mink->getSession()->getPage();
    }

    public function setHttpHeader(string $name, string $value): void
    {
        $contentHeaders = ['CONTENT_LENGTH' => true, 'CONTENT_MD5' => true, 'CONTENT_TYPE' => true];
        $name = \str_replace('-', '_', \strtoupper($name));

        if (!isset($contentHeaders[$name])) {
            $name = 'HTTP_' . $name;
        }

        $this->requestHeaders[$name] = $value;
    }

    /**
     * @return array<string, array<int, string>|string>
     */
    public function getHttpHeaders(): array
    {
        return \array_change_key_case(
            $this->mink->getSession()->getResponseHeaders(),
            CASE_LOWER
        );
    }

    public function getHttpHeader(string $name): string
    {
        $values = $this->getHttpRawHeader($name);

        return implode(', ', $values);
    }

    /**
     * @return list<string>
     */
    public function getHttpRawHeader(string $name): array
    {
        $name = strtolower($name);
        $headers = $this->getHttpHeaders();

        if (isset($headers[$name])) {
            $value = $headers[$name];
            if (!\is_array($headers[$name])) {
                $value = [$headers[$name]];
            }
        } else {
            throw new \OutOfBoundsException(
                "The header '$name' doesn't exist"
            );
        }

        return $value;
    }

    protected function resetHttpHeaders(): void
    {
        $this->requestHeaders = [];

        $client = $this->mink->getSession()->getDriver()->getClient();
        if ($client instanceof AbstractBrowser) {
            $client->setServerParameters([]);
        }
    }

    /**
     * Symfony HttpBrowser expects PHP $_FILES-shaped arrays; UploadedFile objects are not handled.
     *
     * @param array<string, mixed> $files
     *
     * @return array<string, mixed>
     */
    private function normalizeFilesForHttpBrowser(array $files): array
    {
        $normalized = [];
        foreach ($files as $key => $file) {
            if ($file instanceof UploadedFile) {
                $normalized[$key] = [
                    'name' => $file->getClientOriginalName(),
                    'type' => $file->getMimeType(),
                    'tmp_name' => $file->getRealPath() ?: $file->getPathname(),
                    'error' => $file->getError(),
                    'size' => $file->getSize(),
                ];
            } else {
                $normalized[$key] = $file;
            }
        }

        return $normalized;
    }
}
