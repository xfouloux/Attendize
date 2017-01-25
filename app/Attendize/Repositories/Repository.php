<?php

namespace App\Attendize\Repositories;

use Illuminate\Database\Eloquent\Model;

abstract class Repository
{
    const DEFAULT_SORT_ORDER = 'desc';

    protected $model;

    protected function __construct(Model $model)
    {
        $this->model = $model;
    }

    public function find($id)
    {
        return ($this->model)::scope()->findOrfail($id);
    }

    /**
     * @return Model
     */
    public function getModel()
    {
        return $this->model;
    }
}