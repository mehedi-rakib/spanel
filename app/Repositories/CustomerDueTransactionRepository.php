<?php

namespace App\Repositories;

use App\Contracts\Repositories\CustomerDueTransactionRepositoryInterface;
use App\Models\CustomerDueTransaction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

class CustomerDueTransactionRepository implements CustomerDueTransactionRepositoryInterface
{
    public function __construct(
        private readonly CustomerDueTransaction $dueTransaction,
    )
    {
    }

    public function add(array $data): string|object
    {
        return $this->dueTransaction->create($data);
    }

    public function getFirstWhere(array $params, array $relations = []): ?Model
    {
        return $this->dueTransaction->where($params)->with($relations)->first();
    }

    public function getList(array $orderBy = [], array $relations = [], int|string $dataLimit = DEFAULT_DATA_LIMIT, int $offset = null): Collection|LengthAwarePaginator
    {
        $query = $this->dueTransaction->with($relations)
            ->when(!empty($orderBy), function ($query) use ($orderBy) {
                $query->orderBy(array_key_first($orderBy), array_values($orderBy)[0]);
            });

        return $dataLimit == 'all' ? $query->get() : $query->paginate($dataLimit);
    }

    public function getListWhere(array $orderBy = [], string $searchValue = null, array $filters = [], array $relations = [], int|string $dataLimit = DEFAULT_DATA_LIMIT, int $offset = null): Collection|LengthAwarePaginator
    {
        $query = $this->dueTransaction
            ->with($relations)
            ->when(!empty($filters['user_id']), function ($query) use ($filters) {
                return $query->where('user_id', $filters['user_id']);
            })
            ->when(!empty($filters['type']), function ($query) use ($filters) {
                return $query->where('type', $filters['type']);
            })
            ->when(!empty($filters['from_date']), function ($query) use ($filters) {
                return $query->whereDate('created_at', '>=', $filters['from_date']);
            })
            ->when(!empty($filters['to_date']), function ($query) use ($filters) {
                return $query->whereDate('created_at', '<=', $filters['to_date']);
            })
            ->when(!empty($orderBy), function ($query) use ($orderBy) {
                $query->orderBy(array_key_first($orderBy), array_values($orderBy)[0]);
            }, function ($query) {
                $query->orderBy('id', 'desc');
            });

        return $dataLimit == 'all' ? $query->get() : $query->paginate($dataLimit);
    }

    public function update(string $id, array $data): bool
    {
        return $this->dueTransaction->where('id', $id)->update($data);
    }

    public function delete(array $params): bool
    {
        return $this->dueTransaction->where($params)->delete();
    }
}
