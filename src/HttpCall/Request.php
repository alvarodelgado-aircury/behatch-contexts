<?php
declare(strict_types=1);

namespace Behatch\HttpCall;

use Behat\Mink\Mink;

class Request
{
    private Mink $mink;

    private ?Request\BrowserKit $client = null;

    public function __construct(Mink $mink)
    {
        $this->mink = $mink;
    }

    /**
     * @param array<int, mixed> $arguments
     * @return mixed
     */
    public function __call(string $name, array $arguments)
    {
        return \call_user_func_array([$this->getClient(), $name], $arguments);
    }

    private function getClient(): Request\BrowserKit
    {
        return $this->client ??= new Request\BrowserKit($this->mink);
    }
}
