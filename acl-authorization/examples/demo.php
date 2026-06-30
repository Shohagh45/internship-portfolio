<?php

declare(strict_types=1);

/**
 * Runnable demo for the RBAC access control. No database needed.
 *
 *   php examples/demo.php
 */

require __DIR__ . '/../src/PermissionRepository.php';
require __DIR__ . '/../src/ArrayPermissionRepository.php';
require __DIR__ . '/../src/AccessControl.php';

use Portfolio\AclAuthorization\AccessControl;
use Portfolio\AclAuthorization\ArrayPermissionRepository;

// Feature / action / role catalogs (normally rows in the database).
$features = ['Clients' => 1, 'Reports' => 2, 'Social Map' => 3];
$actions  = ['create' => 10, 'read' => 11, 'update' => 12, 'delete' => 13];

// Role 5 = case worker: can read/update clients and read reports.
$grants = [
    [1, 11, 5], // Clients.read
    [1, 12, 5], // Clients.update
    [2, 11, 5], // Reports.read
];

$repo = new ArrayPermissionRepository($features, $actions, $grants);
$acl  = new AccessControl($repo); // enforcement on

$caseWorker = 5;

function check(AccessControl $acl, string $feature, string $action, int $role): void
{
    $ok = $acl->isAllowed($feature, $action, $role);
    printf("  %-22s %-8s -> %s\n", $feature, $action, $ok ? 'ALLOW' : 'DENY');
}

echo "== Case worker (role 5), enforcement ON ==\n";
check($acl, 'Clients', 'read', $caseWorker);    // granted
check($acl, 'Clients', 'delete', $caseWorker);  // not granted -> DENY
check($acl, 'Reports', 'read', $caseWorker);    // granted
check($acl, 'Reports', 'update', $caseWorker);  // not granted -> DENY

echo "\n== Tolerant name + synonym resolution ==\n";
check($acl, 'social-map', 'read', $caseWorker); // normalized name, but no grant -> DENY
check($acl, 'Clients', 'edit', $caseWorker);    // 'edit' synonym -> update -> ALLOW
check($acl, 'Clients', 'aanmaken', $caseWorker);// Dutch 'create' synonym, no grant -> DENY

echo "\n== Unknown feature fails closed ==\n";
check($acl, 'Billing', 'read', $caseWorker);    // unknown feature -> DENY

echo "\n== Kill-switch via ACL_DISABLED env var ==\n";
putenv('ACL_DISABLED=true');
$bypass = AccessControl::fromEnv($repo);
check($bypass, 'Clients', 'delete', $caseWorker); // disabled -> ALLOW everything
putenv('ACL_DISABLED');
