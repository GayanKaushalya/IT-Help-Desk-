<?php

declare(strict_types=1);

namespace App\Database\Query\Grammars;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\SqlServerGrammar;
use Illuminate\Support\Arr;

/**
 * Query grammar for SQL Server versions older than 2012.
 *
 * Laravel compiles offset pagination to `OFFSET ... ROWS FETCH NEXT ... ROWS ONLY`,
 * which was introduced in SQL Server 2012. The company instance runs 2008 R2, so
 * offset queries are rewritten using the ROW_NUMBER() windowing form instead.
 *
 * Queries without an offset are untouched: Laravel already compiles those to
 * `SELECT TOP n`, which 2008 R2 understands.
 */
class SqlServerLegacyGrammar extends SqlServerGrammar
{
    /**
     * Binding groups in the order they appear once the ORDER BY clause has been
     * relocated into the SELECT list.
     */
    private const REORDERED_BINDINGS = [
        'select', 'order', 'from', 'join', 'where', 'groupBy', 'having', 'union', 'unionOrder',
    ];

    /**
     * {@inheritdoc}
     */
    public function compileSelect(Builder $query)
    {
        $needsRewrite = $query->offset
            && ! $query->unions
            && ! isset($query->groupLimit)
            && is_null($query->aggregate);

        if (! $needsRewrite) {
            return parent::compileSelect($query);
        }

        if (is_null($query->columns)) {
            $query->columns = ['*'];
        }

        return $this->compileLegacyOffset($query, $this->compileComponents($query));
    }

    /**
     * {@inheritdoc}
     */
    protected function compileLimit(Builder $query, $limit)
    {
        // With an offset present the row window is applied by compileLegacyOffset.
        return $query->offset > 0 ? '' : parent::compileLimit($query, $limit);
    }

    /**
     * {@inheritdoc}
     */
    protected function compileOffset(Builder $query, $offset)
    {
        return '';
    }

    /**
     * Wrap the compiled query in a ROW_NUMBER() derived table.
     *
     * @param  array<string, string>  $components
     */
    protected function compileLegacyOffset(Builder $query, array $components): string
    {
        // SQL Server requires an ORDER BY inside OVER(); fall back to a constant
        // so unordered queries still compile.
        $orders = $components['orders'] ?? '';

        if ($orders === '') {
            $orders = 'order by (select 0)';
        }

        $row = $this->wrap('laravel_row');

        $components['columns'] .= ', row_number() over ('.$orders.') as '.$row;

        unset($components['orders'], $components['offset'], $components['limit']);

        // Moving the ordering into the SELECT list also moves its bindings ahead
        // of the WHERE bindings, so the binding order has to follow.
        if ($this->orderContainsExpression($query)) {
            $query->bindings = $this->reorderBindings($query);
        }

        $inner = $this->concatenate($components);

        return 'select * from ('.$inner.') as '.$this->wrap('laravel_paginated')
            .' where '.$row.' '.$this->compileRowConstraint($query)
            .' order by '.$row;
    }

    /**
     * Build the row-window predicate for the requested offset and limit.
     */
    protected function compileRowConstraint(Builder $query): string
    {
        $start = (int) $query->offset + 1;

        if ($query->limit > 0) {
            return 'between '.$start.' and '.((int) $query->offset + (int) $query->limit);
        }

        return '>= '.$start;
    }

    /**
     * Determine whether any ORDER BY clause carries its own bindings.
     */
    protected function orderContainsExpression(Builder $query): bool
    {
        foreach ((array) $query->orders as $order) {
            if ($this->isExpression($order['column'] ?? null)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function reorderBindings(Builder $query): array
    {
        return Arr::sort(
            $query->bindings,
            fn ($bindings, $key) => array_search($key, self::REORDERED_BINDINGS)
        );
    }
}
