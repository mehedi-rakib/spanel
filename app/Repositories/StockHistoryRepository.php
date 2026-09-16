<?php

namespace App\Repositories;

use App\Contracts\Repositories\StockHistoryRepositoryInterface;
use App\Models\StockHistory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

class StockHistoryRepository implements StockHistoryRepositoryInterface
{
    public function __construct(
        private readonly StockHistory $stockHistory,
    )
    {
    }

    public function add(array $data): string|object
    {
        return $this->stockHistory->create($data);
    }

    public function getFirstWhere(array $params, array $relations = []): ?Model
    {
        return $this->stockHistory->where($params)->with($relations)->first();
    }

    public function getList(array $orderBy = [], array $relations = [], int|string $dataLimit = DEFAULT_DATA_LIMIT, int $offset = null): Collection|LengthAwarePaginator
    {
        $query = $this->stockHistory->with($relations)
            ->when(!empty($orderBy), function ($query) use ($orderBy) {
                $query->orderBy(array_key_first($orderBy), array_values($orderBy)[0]);
            });

        return $dataLimit == 'all' ? $query->get() : $query->paginate($dataLimit);
    }

    public function getListWhere(array $orderBy = [], string $searchValue = null, array $filters = [], array $relations = [], int|string $dataLimit = DEFAULT_DATA_LIMIT, int $offset = null): Collection|LengthAwarePaginator
    {
        $query = $this->stockHistory
            ->with($relations)
            ->when(!empty($filters['product_id']), function ($query) use ($filters) {
                return $query->where('product_id', $filters['product_id']);
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
            ->when($searchValue, function ($query) use ($searchValue) {
                return $query->whereHas('product', function ($query) use ($searchValue) {
                    $query->where('name', 'like', "%{$searchValue}%")
                        ->orWhere('code', 'like', "%{$searchValue}%");
                });
            })
            ->when(!empty($orderBy), function ($query) use ($orderBy) {
                $query->orderBy(array_key_first($orderBy), array_values($orderBy)[0]);
            }, function ($query) {
                $query->orderBy('id', 'desc');
            });

        $filters += ['searchValue' => $searchValue];
        return $dataLimit == 'all' ? $query->get() : $query->paginate($dataLimit)->appends($filters);
    }

    public function update(string $id, array $data): bool
    {
        return $this->stockHistory->where('id', $id)->update($data);
    }

    public function delete(array $params): bool
    {
        return $this->stockHistory->where($params)->delete();
    }
}
