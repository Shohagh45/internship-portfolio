<?php

declare(strict_types=1);

namespace Portfolio\ConfigDrivenCrud;

use Closure;
use InvalidArgumentException;

/**
 * Config-driven CRUD engine.
 *
 * A back-office has dozens of near-identical "manage this lookup list" screens
 * (labels, products, categories, contact types, …). Instead of a hand-written
 * controller + model per entity, each one is described once in a dictionary and
 * a single engine performs overview / create / update / delete for all of them.
 *
 * Each dictionary entry:
 *   [
 *     'table'        => 'labels',
 *     'id_field'     => 'id',
 *     'columns'      => ['list' => [...], 'create' => [...], 'update' => [...]],
 *     'defaults'     => ['status' => 1],
 *     'order_by'     => 'name ASC',
 *     'status_field' => 'status',
 *     'log_messages' => ['create' => '...', 'update' => '...', 'delete' => '...'],
 *   ]
 */
final class DictionaryCrud
{
    /**
     * @param array<string,array<string,mixed>> $dictionary
     * @param Closure(string):void|null $logger  Receives an audit message per write.
     */
    public function __construct(
        private array $dictionary,
        private CrudRepository $repo,
        private ?Closure $logger = null,
    ) {
    }

    /** @return array<int,array<string,mixed>> */
    public function overview(string $entity): array
    {
        $cfg = $this->config($entity);
        return $this->repo->list($cfg['table'], $cfg['columns']['list'], $cfg['order_by'] ?? ($cfg['id_field'] . ' ASC'));
    }

    /** @return array<string,mixed>|null */
    public function find(string $entity, int|string $id): ?array
    {
        $cfg = $this->config($entity);
        return $this->repo->find($cfg['table'], $cfg['id_field'], $id);
    }

    /** @param array<string,mixed> $input @return int|string new id */
    public function create(string $entity, array $input): int|string
    {
        $cfg  = $this->config($entity);
        // Whitelist: only configured create-columns are written (mass-assignment safe).
        $data = array_merge($cfg['defaults'] ?? [], $this->whitelist($input, $cfg['columns']['create']));

        $id = $this->repo->insert($cfg['table'], $data);
        $this->log($cfg, 'create');
        return $id;
    }

    /** @param array<string,mixed> $input */
    public function update(string $entity, int|string $id, array $input): void
    {
        $cfg = $this->config($entity);
        // Never allow the id column itself to be overwritten via input.
        $allowed = array_values(array_diff($cfg['columns']['update'], [$cfg['id_field']]));
        $data = $this->whitelist($input, $allowed);

        $this->repo->update($cfg['table'], $cfg['id_field'], $id, $data);
        $this->log($cfg, 'update');
    }

    public function delete(string $entity, int|string $id): void
    {
        $cfg = $this->config($entity);
        $this->repo->delete($cfg['table'], $cfg['id_field'], $id);
        $this->log($cfg, 'delete');
    }

    /** @return array<string,mixed> */
    private function config(string $entity): array
    {
        if (!isset($this->dictionary[$entity])) {
            throw new InvalidArgumentException("Unknown entity: {$entity}");
        }
        return $this->dictionary[$entity];
    }

    /**
     * @param array<string,mixed> $input
     * @param string[] $allowed
     * @return array<string,mixed>
     */
    private function whitelist(array $input, array $allowed): array
    {
        return array_intersect_key($input, array_flip($allowed));
    }

    /** @param array<string,mixed> $cfg */
    private function log(array $cfg, string $action): void
    {
        if ($this->logger === null) {
            return;
        }
        $message = $cfg['log_messages'][$action] ?? ucfirst($action) . ' ' . $cfg['table'];
        ($this->logger)($message);
    }
}
