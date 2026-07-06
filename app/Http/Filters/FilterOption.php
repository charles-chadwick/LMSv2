<?php

namespace App\Http\Filters;

final class FilterOption
{
    /**
     * @param  list<array{value: string, label: string}>|null  $options
     */
    private function __construct(
        private string $key,
        private string $label,
        private string $type,
        private ?array $options,
        private ?bool $multiple,
    ) {}

    /**
     * @param  list<array{value: string, label: string}>  $options
     */
    public static function select(string $key, string $label, array $options, bool $multiple = true): self
    {
        return new self($key, $label, 'select', $options, $multiple);
    }

    public static function dateRange(string $key, string $label): self
    {
        return new self($key, $label, 'daterange', null, null);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $descriptor = [
            'key' => $this->key,
            'label' => $this->label,
            'type' => $this->type,
        ];

        if ($this->type === 'select') {
            $descriptor['multiple'] = $this->multiple;
            $descriptor['options'] = $this->options;
        }

        return $descriptor;
    }

    /**
     * @param  list<self>  $options
     * @return list<array<string, mixed>>
     */
    public static function toArrayList(array $options): array
    {
        return array_map(fn (self $option): array => $option->toArray(), $options);
    }
}
