<?php

namespace Kirschbaum\Loop;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Kirschbaum\Loop\Contracts\Tool;
use Kirschbaum\Loop\Contracts\Toolkit;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Prism;
use Prism\Prism\Text\Response;
use Prism\Prism\Tool as PrismTool;
use Prism\Prism\ValueObjects\Messages\AssistantMessage;
use Prism\Prism\ValueObjects\Messages\UserMessage;

class Loop
{
    protected string $context = '';

    public function __construct(protected LoopTools $loopTools) {}

    public function setup(): void {}

    public function context(string $context): static
    {
        $this->context .= "\n\n".$context;

        return $this;
    }

    public function tool(Tool $tool): static
    {
        $this->loopTools->registerTool($tool);

        return $this;
    }

    public function toolkit(Toolkit $toolkit): static
    {
        $this->loopTools->registerToolkit($toolkit);

        return $this;
    }

    /**
     * @param  Collection<array-key, mixed>  $messages
     */
    public function ask(string $question, Collection $messages): Response
    {
        $prompt = sprintf(
            "
                You are a helpful assistant. You will have many tools available to you. You need to give informations about the data and ask the user what you need to give him what he needs. \n\n
                Today is %s. Current month is %s. Current day is %s. Database being used is %s. \n\n
                When using the tools, always pass all the parameters listed in the tool. If you don't have all the information, ask the user for it. If it's optional, pass null. \n
                When a field is tagged with a access_type read, it means that the field is automatically calculated and is not stored in the database. \n
                When referencing an ID, try to fetch the resource of that ID from the database and give additional informations about it. \n\n
                When giving the final output, please compress the information to the minimum needed to answer the question. No need to explain what math you did unless explicitly asked. \n\n
                Parameter names in tools never include the $ symbol. \n\n
                %s \n\n
                You are logged in as %s (User ID: %s)
            ",
            now()->format('Y-m-d'),
            now()->format('F'),
            now()->format('d'),
            config()->string('database.default'),
            $this->context,
            Auth::user()?->name, /** @phpstan-ignore  property.notFound */
            Auth::user()?->id,
        );
        dump($prompt);

        $messages = $messages
            ->reject(fn ($message) => empty($message['message']))
            ->map(function ($message) {
                return $message['user'] === 'AI'
                    ? new AssistantMessage($message['message'])
                    : new UserMessage($message['message']);
            })->toArray();

        $messages[] = new UserMessage($question);

        return Prism::text()
            // ->using(Provider::Anthropic, 'claude-3-5-sonnet-latest')
            ->using(Provider::OpenAI, 'gpt-4o-mini')
            ->withMaxSteps(10)
            ->withMessages($messages)
            ->withSystemPrompt($prompt)
            ->withTools($this->getPrismTools())
            ->asText();
    }

    public function getPrismTools(): Collection
    {
        return $this->loopTools
            ->getTools()
            ->toBase()
            ->map(fn (Tool $tool) => $tool->build());
    }

    public function getPrismTool(string $name): PrismTool
    {
        return $this->loopTools
            ->getTools()
            ->getTool($name)
            ->build();
    }
}
