<?php declare(strict_types=1);

namespace ProtoneMedia\LaravelCrossEloquentSearch;

use ProtoneMedia\LaravelCrossEloquentSearch\ValueObjects\OrderDirection;
use ProtoneMedia\LaravelCrossEloquentSearch\ValueObjects\ModelOrder;

class SearchConfiguration
{
    public function __construct(
        private OrderDirection $orderDirection = OrderDirection::ASC,
        private ?ModelOrder $modelOrder = null,
        private bool $beginWithWildcard = false,
        private bool $endWithWildcard = true,
        private bool $ignoreCase = false,
        private bool $soundsLike = false,
        private bool $parseTerm = true,
        private int $perPage = 15,
        private string $pageName = 'page',
        private ?int $page = null,
        private bool $simplePaginate = false,
        private ?string $includeModelTypeWithKey = null
    ) {}

    /**
     * Create a default configuration instance.
     */
    public static function default(): self
    {
        return new self();
    }

    // Order direction methods
    public function orderDirection(): OrderDirection
    {
        return $this->orderDirection;
    }

    public function setOrderDirection(OrderDirection $direction): void
    {
        $this->orderDirection = $direction;
    }

    // Model order methods
    public function modelOrder(): ?ModelOrder
    {
        return $this->modelOrder;
    }

    public function setModelOrder(?ModelOrder $modelOrder): void
    {
        $this->modelOrder = $modelOrder;
    }

    // Wildcard methods
    public function beginWithWildcard(): bool
    {
        return $this->beginWithWildcard;
    }

    public function setBeginWithWildcard(bool $state): void
    {
        $this->beginWithWildcard = $state;
    }

    public function endWithWildcard(): bool
    {
        return $this->endWithWildcard;
    }

    public function setEndWithWildcard(bool $state): void
    {
        $this->endWithWildcard = $state;
    }

    // Case sensitivity
    public function ignoreCase(): bool
    {
        return $this->ignoreCase;
    }

    public function setIgnoreCase(bool $state): void
    {
        $this->ignoreCase = $state;
    }

    // Sounds like
    public function soundsLike(): bool
    {
        return $this->soundsLike;
    }

    public function setSoundsLike(bool $state): void
    {
        $this->soundsLike = $state;
    }

    // Term parsing
    public function parseTerm(): bool
    {
        return $this->parseTerm;
    }

    public function setParseTerm(bool $state): void
    {
        $this->parseTerm = $state;
    }

    // Pagination methods
    public function perPage(): int
    {
        return $this->perPage;
    }

    public function setPerPage(int $perPage): void
    {
        $this->perPage = $perPage;
    }

    public function pageName(): string
    {
        return $this->pageName;
    }

    public function setPageName(string $pageName): void
    {
        $this->pageName = $pageName;
    }

    public function page(): ?int
    {
        return $this->page;
    }

    public function setPage(?int $page): void
    {
        $this->page = $page;
    }

    public function simplePaginate(): bool
    {
        return $this->simplePaginate;
    }

    public function setSimplePaginate(bool $state): void
    {
        $this->simplePaginate = $state;
    }

    public function isPaginated(): bool
    {
        return $this->pageName !== '';
    }

    // Model type inclusion
    public function includeModelTypeWithKey(): ?string
    {
        return $this->includeModelTypeWithKey;
    }

    public function setIncludeModelTypeWithKey(?string $key): void
    {
        $this->includeModelTypeWithKey = $key;
    }

    // Convenience methods
    public function isOrderingByRelevance(): bool
    {
        return $this->orderDirection->isRelevance();
    }
}