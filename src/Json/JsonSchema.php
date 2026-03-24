<?php
declare(strict_types=1);

namespace Behatch\Json;

use JsonSchema\SchemaStorage;
use JsonSchema\Validator;

class JsonSchema extends Json
{
    private ?string $uri;

    public function __construct($content, ?string $uri = null)
    {
        $this->uri = $uri;

        parent::__construct($content);
    }

    public function resolve(SchemaStorage $resolver): static
    {
        if (!$this->hasUri()) {
            return $this;
        }

        $this->content = $resolver->resolveRef($this->uri);

        return $this;
    }

    /**
     * @throws \Exception
     */
    public function validate(Json $json, Validator $validator): bool
    {
        $validator->check($json->getContent(), $this->getContent());

        if (!$validator->isValid()) {
            $msg = "JSON does not validate. Violations:" . PHP_EOL;
            foreach ($validator->getErrors() as $error) {
                $msg .= sprintf("  - [%s] %s" . PHP_EOL, $error['property'], $error['message']);
            }
            throw new \Exception($msg);
        }

        return true;
    }

    private function hasUri(): bool
    {
        return $this->uri !== null;
    }
}
