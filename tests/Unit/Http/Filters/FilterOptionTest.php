<?php

use App\Http\Filters\FilterOption;

it('builds a multi-select descriptor by default', function () {
    $options = [['value' => 'Draft', 'label' => 'Draft']];

    expect(FilterOption::select('status', 'Status', $options)->toArray())->toBe([
        'key' => 'status',
        'label' => 'Status',
        'type' => 'select',
        'multiple' => true,
        'options' => $options,
    ]);
});

it('builds a single-select descriptor when multiple is false', function () {
    $options = [['value' => 'read', 'label' => 'Read']];

    expect(FilterOption::select('read', 'Status', $options, multiple: false)->toArray())->toBe([
        'key' => 'read',
        'label' => 'Status',
        'type' => 'select',
        'multiple' => false,
        'options' => $options,
    ]);
});

it('builds a daterange descriptor without multiple or options keys', function () {
    expect(FilterOption::dateRange('created_at', 'Created')->toArray())->toBe([
        'key' => 'created_at',
        'label' => 'Created',
        'type' => 'daterange',
    ]);
});

it('maps a list of options to their array descriptors in order', function () {
    $list = FilterOption::toArrayList([
        FilterOption::select('status', 'Status', [['value' => 'Draft', 'label' => 'Draft']]),
        FilterOption::dateRange('created_at', 'Created'),
    ]);

    expect($list)->toBe([
        [
            'key' => 'status',
            'label' => 'Status',
            'type' => 'select',
            'multiple' => true,
            'options' => [['value' => 'Draft', 'label' => 'Draft']],
        ],
        [
            'key' => 'created_at',
            'label' => 'Created',
            'type' => 'daterange',
        ],
    ]);
});
