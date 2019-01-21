<?php

namespace Preferred\Infrastructure\Abstracts;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Preferred\Infrastructure\Contracts\BaseRepository;
use Ramsey\Uuid\Uuid;

abstract class AbstractEloquentRepository implements BaseRepository
{
    /**
     * @var Model
     */
    protected $model;

    /** @var bool */
    protected $withoutGlobalScopes = false;

    /** @var array */
    protected $with = [];

    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    public function with(array $with = [])
    {
        $this->with = $with;
        return $this;
    }

    public function withoutGlobalScopes()
    {
        $this->withoutGlobalScopes = true;
        return $this;
    }

    public function store(array $data)
    {
        return $this->model->with([])->create($data);
    }

    public function update(Model $model, array $data)
    {
        return tap($model)->update($data);
    }

    public function findByCriteria(array $criteria = []): Collection
    {
        if (!$this->withoutGlobalScopes) {
            return $this->model->with($this->with)
                ->where($criteria)
                ->orderBy('created_at', 'desc')
                ->get();
        }

        return $this->model->with($this->with)
            ->withoutGlobalScopes()
            ->where($criteria)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function findByFilters()
    {
        return $this->model->with($this->with)->paginate();
    }

    public function findOneById($id)
    {
        if (!Uuid::isValid($id)) {
            throw (new ModelNotFoundException)->setModel(get_class($this->model));
        }

        if (!empty($this->with) || auth()->check()) {
            return $this->findOneBy(['id' => $id]);
        }

        return Cache::remember($id, 60, function () use ($id) {
            return $this->findOneBy(['id' => $id]);
        });
    }

    public function findOneBy(array $criteria)
    {
        if (!$this->withoutGlobalScopes) {
            $first = $this->model->with($this->with)
                ->withoutGlobalScopes()
                ->where($criteria)
                ->orderBy('created_at', 'desc')
                ->first();

            if (!empty($first) && !empty($first->user_id) && auth()->check() && ($first->user_id != auth()->id())) {
                throw new AuthorizationException('Forbidden', Response::HTTP_FORBIDDEN);
            } elseif (empty($first)) {
                throw (new ModelNotFoundException)->setModel(get_class($this->model));
            }

            return $first;
        }

        return $this->model->with($this->with)->withoutGlobalScopes()->where($criteria)->firstOrFail();
    }
}