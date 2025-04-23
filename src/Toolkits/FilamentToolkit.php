<?php

namespace Kirschbaum\Loop\Toolkits;

use Exception;
use Filament\Facades\Filament;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Support\Contracts\TranslatableContentDriver;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Columns\Column;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\BaseFilter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Kirschbaum\Loop\Collections\ToolCollection;
use Kirschbaum\Loop\Concerns\Makeable;
use Kirschbaum\Loop\Contracts\Toolkit;
use Kirschbaum\Loop\Enums\Mode;
use Kirschbaum\Loop\Exceptions\LoopMcpException;
use Kirschbaum\Loop\Tools\Filament\DescribeFilamentResourceTool;
use Kirschbaum\Loop\Tools\Filament\GetFilamentResourceDataTool;
use Kirschbaum\Loop\Tools\Filament\ListFilamentResourcesTool;
use Livewire\Component as LivewireComponent;
use Prism\Prism\Facades\Tool as PrismTool;
use Symfony\Component\HttpFoundation\Exception\JsonException;

class FilamentToolkit implements Toolkit
{
    use Makeable;

    /**
     * @param Resource[] $resources
     */
    public function __construct(
        public readonly array $resources = [],
        public readonly Mode $mode = Mode::ReadOnly,
    ) {
    }

    public static function make(...$args): static
    {
        return new self(...$args);
    }

    public function getTools(): ToolCollection
    {
        return new ToolCollection([
            ListFilamentResourcesTool::make(
                resources: $this->resources
            ),
            DescribeFilamentResourceTool::make(),
            GetFilamentResourceDataTool::make(),
        ]);
    }
}