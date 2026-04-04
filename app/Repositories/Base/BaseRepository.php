<?php

namespace App\Repositories\Base;

use App\Repositories\Contracts\RepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

abstract class BaseRepository implements RepositoryInterface
{
    public function __construct(protected Model $model)
    {
    }

    public function all(array $columns = ['*'], array $relations = []): Collection
    {
        return $this->model->select($columns)->with($relations)->get();
    }

    public function paginate(int $perPage = 15, array $columns = ['*'], array $relations = []): LengthAwarePaginator
    {
        return $this->model->select($columns)->with($relations)->paginate($perPage);
    }

    public function findById(string $id, array $columns = ['*'], array $relations = []): ?Model
    {
        return $this->model->select($columns)->with($relations)->find($id);
    }

    public function findByIdOrFail(string $id, array $columns = ['*'], array $relations = []): Model
    {
        return $this->model->select($columns)->with($relations)->findOrFail($id);
    }

    public function findByUuid(string $uuid, array $columns = ['*'], array $relations = []): ?Model
    {
        return $this->model->select($columns)->with($relations)->where('uuid', $uuid)->first();
    }

    public function findByUuidOrFail(string $uuid, array $columns = ['*'], array $relations = []): Model
    {
        return $this->model->select($columns)->with($relations)->where('uuid', $uuid)->firstOrFail();
    }

    public function create(array $data): Model
    {
        return $this->model->create($data);
    }

    public function update(Model $model, array $data): bool
    {
        return $model->update($data);
    }

    public function delete(Model $model): bool
    {
        return $model->delete();
    }
}
