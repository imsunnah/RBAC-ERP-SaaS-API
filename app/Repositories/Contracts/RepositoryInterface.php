<?php

namespace App\Repositories\Contracts;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

interface RepositoryInterface
{
    public function all(array $columns = ['*'], array $relations = []): Collection;

    public function paginate(int $perPage = 15, array $columns = ['*'], array $relations = []): LengthAwarePaginator;

    public function findById(string $id, array $columns = ['*'], array $relations = []): ?Model;

    public function findByIdOrFail(string $id, array $columns = ['*'], array $relations = []): Model;

    public function findByUuid(string $uuid, array $columns = ['*'], array $relations = []): ?Model;

    public function findByUuidOrFail(string $uuid, array $columns = ['*'], array $relations = []): Model;

    public function create(array $data): Model;

    public function update(Model $model, array $data): bool;

    public function delete(Model $model): bool;
}
