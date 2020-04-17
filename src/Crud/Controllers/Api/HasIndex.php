<?php

namespace Fjord\Crud\Controllers\Api;

use Illuminate\Http\Request;
use Fjord\Support\IndexTable;

trait HasIndex
{
    /**
     * Load index table items.
     *
     * @param Request $request
     * @return array $items
     */
    public function indexTable(Request $request)
    {
        return IndexTable::get($this->config()->indexQuery, $request);
    }
}
