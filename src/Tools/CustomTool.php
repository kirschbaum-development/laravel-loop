<?php

namespace Kirschbaum\Loop\Tools;

use Closure;
use Kirschbaum\Loop\Concerns\Makeable;
use Kirschbaum\Loop\Contracts\Tool;
use Prism\Prism\Contracts\Schema;
use Prism\Prism\Tool as PrismTool;

class CustomTool implements Tool
{
    use Makeable;

    private PrismTool $prismTool;

    public function __construct(
        public readonly string $name,
        public readonly string $description,
    ) {
        $this->prismTool = app(PrismTool::class)
            ->as($this->getName())
            ->for($this->description);
    }

    public function build(): PrismTool
    {
        return $this->prismTool;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function using(Closure|callable $handler): self
    {
        $this->prismTool->using(fn: $handler);

        return $this;
    }

    public function withArrayParameter(
        string $name,
        string $description,
        Schema $items,
        bool $required = true,
    ): self {
        $this->prismTool->withArrayParameter($name, $description, $items, $required);

        return $this;
    }

    public function withBooleanParameter(string $name, string $description, bool $required = true): self
    {
        $this->prismTool->withBooleanParameter($name, $description, $required);

        return $this;
    }

    /**
     * @param  array<int, string|int|float>  $options
     */
    public function withEnumParameter(
        string $name,
        string $description,
        array $options,
        bool $required = true,
    ): self {
        $this->prismTool->withEnumParameter($name, $description, $options, $required);

        return $this;
    }

    public function withNumberParameter(string $name, string $description, bool $required = true): self
    {
        $this->prismTool->withNumberParameter($name, $description, $required);

        return $this;
    }

    /**
     * @param  array<int, Schema>  $properties
     * @param  array<int, string>  $requiredFields
     */
    public function withObjectParameter(
        string $name,
        string $description,
        array $properties,
        array $requiredFields = [],
        bool $allowAdditionalProperties = false,
        bool $required = true,
    ): self {

        $this->prismTool->withObjectParameter($name, $description, $properties, $requiredFields, $allowAdditionalProperties, $required);

        return $this;
    }

    public function withParameter(Schema $parameter, bool $required = true): self
    {
        $this->prismTool->withParameter($parameter, $required);

        return $this;
    }

    public function withStringParameter(string $name, string $description, bool $required = true): self
    {
        $this->prismTool->withStringParameter($name, $description, $required);

        return $this;
    }
}
