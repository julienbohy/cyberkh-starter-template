<?php

declare(strict_types=1);

namespace App\Pagination;

use Doctrine\ORM\Query;
use Doctrine\ORM\Tools\Pagination\Paginator;

/**
 * One page of the results of a query, for the Pagination component.
 *
 * @template T of object
 */
final readonly class Page
{
    /**
     * @param list<T> $items
     */
    private function __construct(
        public array $items,
        public int $number,
        public int $pageCount,
        public int $total,
    ) {}

    /**
     * A page out of range is the last one.
     *
     * @template U of object
     *
     * @param Query<mixed, U> $query
     *
     * @return self<U>
     */
    public static function of(Query $query, int $number, int $pageSize): self
    {
        $paginator = new Paginator($query);
        $total = \count($paginator);
        $pageCount = max(1, (int) ceil($total / $pageSize));
        $number = min(max(1, $number), $pageCount);

        $query->setFirstResult(($number - 1) * $pageSize)->setMaxResults($pageSize);
        /** @var list<U> $items */
        $items = iterator_to_array($paginator, false);

        return new self($items, $number, $pageCount, $total);
    }
}
