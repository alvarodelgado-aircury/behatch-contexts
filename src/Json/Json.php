<?php
declare(strict_types=1);

namespace Behatch\Json;

use Behat\Gherkin\Node\PyStringNode;
use Symfony\Component\PropertyAccess\PropertyAccessor;

class Json
{
    protected mixed $content;

    /**
     * @throws \JsonException
     */
    public function __construct(PyStringNode|string $content)
    {
        $this->content = $this->decode((string)$content);
    }

    public function getContent(): mixed
    {
        return $this->content;
    }

    public function read($expression, PropertyAccessor $accessor)
    {
        if (\is_array($this->content)) {
            $expression = \preg_replace('/^root/', '', $expression);
        } else {
            $expression = \preg_replace('/^root./', '', $expression);
        }

        // If root asked, we return the entire content
        if (\strlen(\trim($expression)) <= 0) {
            return $this->content;
        }

        return $accessor->getValue($this->content, $expression);
    }

    /**
     * @throws \JsonException
     */
    public function encode($pretty = true): bool|string
    {
        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

        if (true === $pretty && \defined('JSON_PRETTY_PRINT')) {
            $flags |= JSON_PRETTY_PRINT;
        }

        return \json_encode($this->content, JSON_THROW_ON_ERROR | $flags);
    }

    public function __toString(): string
    {
        return $this->encode(false);
    }

    /**
     * @throws \JsonException
     * @throws \Exception
     */
    private function decode(PyStringNode|string $content): mixed
    {
        try {
            return \json_decode((string) $content, null, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new \Exception("The string '$content' is not valid json");
        }
    }
}
