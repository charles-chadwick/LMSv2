<?php

namespace App\Enums\Concerns;

trait HasSelectOptions
{
    /**
     * Value/label option pairs for select-style filter and form controls.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case): array => ['value' => $case->value, 'label' => $case->value],
            self::cases(),
        );
    }
}
