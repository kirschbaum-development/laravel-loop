<?php

namespace Kirschbaum\Loop;

use Prism\Prism\Prism;
use App\Models\TimeEntry;
use Illuminate\Support\Str;
use App\Models\BudgetPeriod;
use Prism\Prism\Facades\Tool;
use Filament\Facades\Filament;
use Prism\Prism\Text\Response;
use Prism\Prism\Enums\Provider;
use Filament\Resources\Resource;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Pluralizer;
use Illuminate\Support\Facades\Auth;
use Prism\Prism\Schema\NumberSchema;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;
use Kirschbaum\Loop\Tools\StripeTool;
use Prism\Prism\Schema\BooleanSchema;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\Model;
use Stripe\Exception\ApiErrorException;
use App\Filament\Resources\UserResource;
use App\Filament\Resources\BudgetResource;
use App\Filament\Resources\HolidayResource;
use App\Filament\Resources\InvoiceResource;
use App\Filament\Resources\ProjectResource;
use App\Filament\Resources\AllocationResource;
use App\Filament\Resources\TeamMemberResource;
use Kirschbaum\Loop\Tools\Toolkit;
use Prism\Prism\ValueObjects\Messages\UserMessage;
use Prism\Prism\ValueObjects\Messages\AssistantMessage;

class Loop
{
    protected Collection $tools;
    protected static string $context = "";

    /**
     * @var \Kirschbaum\Loop\Tools\Toolkit[]
     */
    protected static array $toolkits = [];

    public static function additionalContext(string $context): void
    {
        static::$context .= "\n\n" . $context;
    }

    public static function register(Toolkit $toolkit): void
    {
        static::$toolkits[] = $toolkit;
    }

    public static function toolkit(Toolkit $toolkit): void
    {
        static::$toolkits[] = $toolkit;
    }

    public function setup(): void
    {
        //
    }

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
            config('database.default'),
            static::$context,
            Auth::user()->name,
            Auth::user()->id,
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
            ->withTools($this->tools->toArray())
            ->asText();
    }

    public function getTools(): Collection
    {
        return collect(static::$toolkits)->map(
            fn (Toolkit $toolkit) => $toolkit->getTools()
        )->flatten();
    }

    public function getTool(string $name): object
    {
        return collect(static::$toolkits)->map(
            fn (Toolkit $toolkit) => $toolkit->getTool($name)
        )->filter()->first();
    }
}
