<?php

namespace Spatie\ModelStates;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

trait HasStates
{
    private array $stateCasts = [];

    public static function bootHasStates(): void
    {
        self::creating(function (Model $model) {
            /**
             * @var \Spatie\ModelStates\HasStates $model
             */
            foreach ($model->getStateConfigs() as $field => $stateConfig) {
                if ($model->{$field} !== null) {
                    continue;
                }

                if ($stateConfig->defaultStateClass === null) {
                    continue;
                }

                $model->{$field} = $stateConfig->defaultStateClass;
            }
        });
    }

    public static function getStates(): Collection
    {
        /** @var \Illuminate\Database\Eloquent\Model|\Spatie\ModelStates\HasStates $model */
        $model = new static();

        return collect($model->getStateConfigs())
            ->map(function (StateConfig $stateConfig) {
                return $stateConfig->baseStateClass::getStateMapping()->keys();
            });
    }

    public static function getDefaultStates(): Collection
    {
        /** @var \Illuminate\Database\Eloquent\Model|\Spatie\ModelStates\HasStates $model */
        $model = new static();

        return collect($model->getStateConfigs())
            ->map(function (StateConfig $stateConfig) {
                $defaultStateClass = $stateConfig->defaultStateClass;

                if ($defaultStateClass === null) {
                    return null;
                }

                return $defaultStateClass::getMorphClass();
            });
    }

    public static function getDefaultStateFor(string $fieldName): ?string
    {
        return static::getDefaultStates()[$fieldName] ?? null;
    }

    public static function getStatesFor(string $fieldName): Collection
    {
        return collect(static::getStates()[$fieldName] ?? []);
    }

    public function scopeWhereState(Builder $builder, string $column, $states): Builder
    {
        if (! is_array($states)) {
            $states = [$states];
        }

        $field = Arr::last(explode('.', $column));

        return $builder->whereIn($column, $this->getStateNamesForQuery($field, $states));
    }

    public function scopeWhereNotState(Builder $builder, string $column, $states): Builder
    {
        if (! is_array($states)) {
            $states = [$states];
        }

        $field = Arr::last(explode('.', $column));

        return $builder->whereNotIn($column, $this->getStateNamesForQuery($field, $states));
    }

    /**
     * @return array|\Spatie\ModelStates\StateConfig[]
     */
    private function getStateConfigs(): array
    {
        $casts = $this->getCasts();

        $states = [];

        foreach ($casts as $field => $state) {
            if (! is_subclass_of($state, State::class)) {
                continue;
            }

            /**
             * @var \Spatie\ModelStates\State $state
             * @var \Illuminate\Database\Eloquent\Model $this
             */
            $states[$field] = $state::config();
        }

        return $states;
    }

    private function getStateNamesForQuery(string $field, array $states): Collection
    {
        /** @var \Spatie\ModelStates\StateConfig|null $stateConfig */
        $stateConfig = $this->getStateConfigs()[$field];

        return $stateConfig->baseStateClass::getStateMapping()
            ->filter(function (string $className, string $morphName) use ($states) {
                return in_array($className, $states)
                    || in_array($morphName, $states);
            })
            ->keys();
    }
}
