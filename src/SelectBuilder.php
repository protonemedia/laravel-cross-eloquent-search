<?php declare(strict_types=1);

namespace ProtoneMedia\LaravelCrossEloquentSearch;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use ProtoneMedia\LaravelCrossEloquentSearch\Grammars\SearchGrammarInterface;
use ProtoneMedia\LaravelCrossEloquentSearch\ValueObjects\ModelOrder;

class SelectBuilder
{
    public function __construct(
        private readonly SearchGrammarInterface $grammar,
        private readonly Collection $models,
        private readonly ?ModelOrder $modelOrder = null
    ) {}

    /**
     * Build select statements for a specific model.
     */
    public function buildSelects(ModelToSearchThrough $currentModel): array
    {
        return $this->models->flatMap(function (ModelToSearchThrough $modelToSearchThrough) use ($currentModel) {
            $qualifiedKeyName = $qualifiedOrderByColumnName = $modelOrderKey = 'null';

            if ($modelToSearchThrough === $currentModel) {
                $prefix = $modelToSearchThrough->getModel()->getConnection()->getTablePrefix();

                $qualifiedKeyName = $prefix . $modelToSearchThrough->getQualifiedKeyName();
                $qualifiedOrderByColumnName = $prefix . $modelToSearchThrough->getQualifiedOrderByColumnName();

                if ($this->modelOrder?->hasOrder()) {
                    $modelOrderKey = $this->modelOrder->getOrderFor(
                        get_class($modelToSearchThrough->getModel())
                    );
                }
            }

            return array_filter([
                DB::raw("{$qualifiedKeyName} as {$this->grammar->wrap($modelToSearchThrough->getModelKey())}"),
                DB::raw("{$qualifiedOrderByColumnName} as {$this->grammar->wrap($modelToSearchThrough->getModelKey('order'))}"),
                $this->modelOrder?->hasOrder() 
                    ? DB::raw("{$modelOrderKey} as {$this->grammar->wrap($modelToSearchThrough->getModelKey('model_order'))}") 
                    : null,
            ]);
        })->all();
    }
}