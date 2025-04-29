<?php

namespace Kirschbaum\Loop\Tools\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

trait ProvidesModelColumns
{
    /**
     * @return array<array-key, object{
     *     name: string,
     *     type: string,
     *     nullable: bool,
     *     has_default: bool,
     *     default: mixed,
     *     extra: string,
     * }>
     */
    protected function getTableColumns(string $modelClass): array
    {
        /** @var Model $model */
        $model = new $modelClass;
        $table = $model->getTable();

        $columns = DB::select("SHOW COLUMNS FROM {$table}");
        $columnsArray = [];

        foreach ($columns as $column) {
            $columnsArray[$column->Field] = (object) [
                'name' => $column->Field,
                'type' => $column->Type,
                'nullable' => $column->Null === 'YES',
                'has_default' => $column->Default !== null || $column->Extra === 'auto_increment',
                'default' => $column->Default,
                'extra' => $column->Extra,
            ];
        }

        return $columnsArray;
    }
}
