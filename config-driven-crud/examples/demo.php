<?php

declare(strict_types=1);

/**
 * Runnable demo for the config-driven CRUD engine. No database needed.
 *
 *   php examples/demo.php
 */

require __DIR__ . '/../src/CrudRepository.php';
require __DIR__ . '/../src/DictionaryCrud.php';

use Portfolio\ConfigDrivenCrud\ArrayCrudRepository;
use Portfolio\ConfigDrivenCrud\DictionaryCrud;

// One dictionary entry per lookup list. Adding a new managed list = adding one
// entry here; no new controller or model code.
$dictionary = [
    'labels' => [
        'table'        => 'labels',
        'id_field'     => 'id',
        'columns'      => [
            'list'   => ['id', 'name', 'status'],
            'create' => ['name', 'status'],
            'update' => ['id', 'name', 'status'],
        ],
        'defaults'     => ['status' => 1],
        'order_by'     => 'name ASC',
        'status_field' => 'status',
        'log_messages' => ['create' => 'Create label', 'update' => 'Update label', 'delete' => 'Delete label'],
    ],
    'products' => [
        'table'        => 'products',
        'id_field'     => 'id',
        'columns'      => [
            'list'   => ['id', 'name', 'status'],
            'create' => ['name', 'status'],
            'update' => ['id', 'name', 'status'],
        ],
        'defaults'     => ['status' => 1],
        'order_by'     => 'name ASC',
        'status_field' => 'status',
        'log_messages' => ['create' => 'Create product', 'update' => 'Update product', 'delete' => 'Delete product'],
    ],
];

$audit = [];
$crud  = new DictionaryCrud(
    $dictionary,
    new ArrayCrudRepository(),
    function (string $message) use (&$audit) { $audit[] = $message; }
);

echo "== Create (defaults applied, input whitelisted) ==\n";
// Note the bogus 'is_admin' field — it is silently dropped, not written.
$id1 = $crud->create('labels', ['name' => 'Urgent', 'is_admin' => true]);
$id2 = $crud->create('labels', ['name' => 'Follow-up', 'status' => 0]);
$crud->create('products', ['name' => 'Home visit']);
echo "Created label ids: {$id1}, {$id2}\n";
echo "First label row: " . json_encode($crud->find('labels', $id1)) . "  (status defaulted to 1, no is_admin)\n";

echo "\n== Overview (sorted by name, only listed columns) ==\n";
foreach ($crud->overview('labels') as $row) {
    echo "  " . json_encode($row) . "\n";
}

echo "\n== Update ==\n";
$crud->update('labels', $id2, ['name' => 'Follow-up (later)', 'status' => 1, 'id' => 999]); // id change ignored
echo "Updated row: " . json_encode($crud->find('labels', $id2)) . "  (id unchanged)\n";

echo "\n== Delete ==\n";
$crud->delete('labels', $id1);
echo "Labels after delete: " . count($crud->overview('labels')) . "\n";

echo "\n== Same engine, different entity ==\n";
foreach ($crud->overview('products') as $row) {
    echo "  " . json_encode($row) . "\n";
}

echo "\n== Audit log produced by the engine ==\n";
foreach ($audit as $line) {
    echo "  - {$line}\n";
}
