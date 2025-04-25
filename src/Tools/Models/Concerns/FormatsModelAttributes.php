<?php

namespace Kirschbaum\Loop\Tools\Models\Concerns;

use Illuminate\Database\Eloquent\Model;

trait FormatsModelAttributes
{
    protected function formatModelAttributes(Model $model, bool $detailed = false): string
    {
        $attributes = $model->getAttributes();
        $formatted = [];

        foreach ($attributes as $key => $value) {
            if (in_array($key, ['id', 'created_at', 'updated_at']) && ! $detailed) {
                continue;
            }

            if ($value instanceof \DateTime) {
                $value = $value->format('Y-m-d H:i:s');
            }

            $formatted[] = "{$key}: {$value}";
        }

        return implode(', ', $formatted);
    }
}
