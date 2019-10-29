<?php
namespace Czim\Listify;

use BadMethodCallException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * @method QueryBuilder|Model listifyScope()
 * @method QueryBuilder|Model inList()
 * @mixin \Eloquent|\Illuminate\Database\Eloquent\Model
 */
trait Listify
{

    /**
     * Listify configuration.
     *
     * @var mixed[]
     */
    protected $listifyConfig = [
        'top_of_list' => 1,
        'column'      => 'position',
        'scope'       => '1 = 1',
        'add_new_at'  => 'bottom',
    ];

    /**
     * @var string
     */
    protected $defaultScope = '1 = 1';

    /**
     * The current raw scope string.
     *
     * @var null|string
     */
    protected $stringScopeValue;

    /**
     * Whether the raw scope, if null, was explicitly set due to an active null-scope.
     *
     * @var boolean
     */
    protected $stringScopeNullExplicitlySet = false;

    /**
     * Whether the original attributes are currently loaded (swapped state).
     *
     * @var bool
     */
    protected $originalAttributesLoaded = false;

    /**
     * Container for temporarily swapped attributes.
     *
     * @var mixed[]
     */
    protected $swappedAttributes = [];


    public static function bootListify(): void
    {
        $desiredPositionOnCreate = null;

        static::deleting(function (Model $model) {
            /** @var Listify $model */
            $model->setListifyPosition(Arr::get($model->getOriginal(), $model->positionColumn()));
        });

        static::deleted(function (Model $model) {
            /** @var Listify $model */
            $model->decrementPositionsOfItemsBelow();
        });

        static::updating(function (Model $model) {
            /** @var Listify $model */
            $model->handleListifyScopeChange();
        });

        static::updated(function (Model $model) {
            /** @var Listify $model */
            $model->updateListifyPositions();
        });

        static::creating(function (Model $model) use (&$desiredPositionOnCreate) {
            /** @var Listify $model */
            $desiredPositionOnCreate = $model->getListifyPosition();
            $model->setListifyPosition(null);

            $model->performConfiguredAddMethod($model);
        });

        static::created(function (Model $model) use (&$desiredPositionOnCreate) {
            /** @var Listify $model */
            if ($desiredPositionOnCreate) {
                $model->insertAt($desiredPositionOnCreate);
            }
        });
    }


    // ------------------------------------------------------------------------------
    //      Listify init and configuration
    // ------------------------------------------------------------------------------

    /**
     * Sets up and overrides listify config.
     *
     * @param array $options
     */
    public function initListify(array $options = []): void
    {
        $this->listifyConfig = array_replace($this->listifyConfig, $options);
    }

    /**
     * Sets a listify config value.
     *
     * @param string $key
     * @param mixed  $value
     * @return $this
     */
    public function setListifyConfig(string $key, $value)
    {
        switch ($key) {

            case 'add_new_at':
                if ( ! method_exists($this, "addToList{$value}")) {
                    throw new \UnexpectedValueException("No method exists for applying the add_new_at value '{$value}'.");
                }
                break;

            case 'scope':
                $normalizedScope = $this->normalizeListifyScope($value);

                if (null !== $normalizedScope && ! is_string($normalizedScope)) {
                    throw new \InvalidArgumentException("Given list scope does not resolve to a usable where clause");
                }
                break;

            // default omitted on purpose
        }

        $this->listifyConfig[ $key ] = $value;


        if ($key === 'scope') {
            $this->rememberCurrentlyUsedScope();
        }

        return $this;
    }

    /**
     * Returns the position to use as 'top of the list'.
     *
     * @return integer
     */
    public function listifyTop(): int
    {
        return (int) $this->getListifyConfigValue('top_of_list');
    }

    /**
     * Returns the name of the position column.
     *
     * @return string
     */
    public function positionColumn(): string
    {
        return $this->getListifyConfigValue('column');
    }

    /**
     * Returns the listify scope definition.
     *
     * @return mixed
     */
    public function getScopeName()
    {
        return $this->getListifyConfigValue('scope');
    }

    /**
     * Returns the position indicator to add new entries to.
     *
     * @return string   bottom or top
     */
    public function addNewAt(): string
    {
        return $this->getListifyConfigValue('add_new_at');
    }

    /**
     * Returns a config value by key, or null if it does not exist.
     *
     * @param string $key
     * @return null|mixed
     */
    protected function getListifyConfigValue(string $key)
    {
        return array_key_exists($key, $this->listifyConfig)
            ?   $this->listifyConfig[ $key ]
            :   null;
    }


    // ------------------------------------------------------------------------------
    //      Scopes
    // ------------------------------------------------------------------------------

    /**
     * Applies the listify scope to a query builder instance.
     * Eloquent scope method.
     *
     * @param Builder|QueryBuilder $query
     * @return Builder|QueryBuilder
     */
    public function scopeListifyScope(Builder $query): Builder
    {
        if (method_exists($this, 'cleanListifyScopedQuery')) {
            $this->cleanListifyScopedQuery($query);
        }

        return $query->whereRaw($this->listifyScopeCondition());
    }

    /**
     * Applies conditions to a query in order to retrieve only the records that are in a list.
     * Eloquent scope method.
     *
     * @param Builder|QueryBuilder|Listify $query
     * @return Builder|QueryBuilder|Listify
     */
    public function scopeInList(Builder $query): Builder
    {
        return $query->listifyScope()->whereNotNull($this->getTable() . '.' . $this->positionColumn());
    }


    // ------------------------------------------------------------------------------
    //      Position / list modification
    // ------------------------------------------------------------------------------

    /**
     * Returns the record's current list position.
     *
     * @return null|integer
     */
    public function getListifyPosition(): ?int
    {
        if ($this->excludeFromList()) {
            return null;
        }

        return $this->getAttribute($this->positionColumn());
    }

    /**
     * Sets the record's current list position directly.
     * No checks or changes are applied.
     *
     * @param null|integer $position
     * @return $this
     */
    public function setListifyPosition(?int $position)
    {
        $this->setAttribute($this->positionColumn(), $position);

        return $this;
    }

    /**
     * Adds the record to a list in the configured standard approach,
     * unless it should be (kept) excluded from any list.
     *
     * @param Model|Listify $model
     */
    protected function performConfiguredAddMethod(Model $model): void
    {
        if ( ! $model->addNewAt() || $this->excludeFromList()) return;

        $addMethod = 'addToList' . $model->addNewAt();

        if ( ! method_exists($model, $addMethod)) {
            throw new BadMethodCallException("Method '{$addMethod}' on listify model does not exist");
        }

        $model->$addMethod();
    }

    /**
     * Adds record to the top of the list.
     * Note that this does not save the model.
     */
    protected function addToListTop(): void
    {
        if ($this->isInList()) {
            return;
        }

        $this->incrementPositionsOfAllItems();

        $this->setListifyPosition($this->listifyTop());
    }

    /**
     * Adds record to the bottom of the list.
     * Note that this does not save the model.
     */
    protected function addToListBottom(): void
    {
        if ($this->isInList()) {
            return;
        }

        $newPosition = $this->getBottomPositionValue();

        if ($newPosition === null) {
            $newPosition = $this->listifyTop();
        } else {
            $newPosition++;
        }

        $this->setListifyPosition($newPosition);
    }

    /**
     * Inserts record at the given position.
     * This re-arranges the affected surrounding records.
     *
     * @param integer $position default is listifyTop()
     * @return $this
     */
    public function insertAt(?int $position = null)
    {
        if ($position === null) {
            $position = $this->listifyTop();
        }

        $this->insertAtPositionAndReorder($position);

        return $this;
    }

    /**
     * Moves the record down a number of positions (meaning the position value is incremented).
     * This re-arranges the affected surrounding records.
     *
     * @return $this
     */
    public function moveLower()
    {
        $lowerItem = $this->lowerItem();

        if ( ! $lowerItem) {
            return $this;
        }

        $this->getConnection()->transaction(function() use ($lowerItem) {

            $this->increment($this->positionColumn());

            // Decrement is not guaranteed to work with global scopes
            $lowerItem->{$this->positionColumn()}--;
            $lowerItem->save();
        });

        return $this;
    }

    /**
     * Moves the record up a number of positions (meaning the position value is decremented).
     * This re-arranges the affected surrounding records.
     *
     * @return $this
     */
    public function moveHigher()
    {
        $higherItem = $this->higherItem();

        if ( ! $higherItem) return $this;

        $this->getConnection()->transaction(function () use ($higherItem) {

            $this->decrement($this->positionColumn());

            // Increment is not guaranteed to work with global scopes,
            $higherItem->{$this->positionColumn()}++;
            $higherItem->save();
        });

        return $this;
    }

    /**
     * Moves the record to the bottom of the list.
     * This re-arranges the affected surrounding records.
     *
     * @return $this
     */
    public function moveToBottom()
    {
        if ($this->isNotInList()) {
            return $this;
        }

        $this->getConnection()->transaction(function () {

            $this->decrementPositionsOfItemsBelow();

            $bottomPosition = $this->getBottomPositionValue($this);
            $newPosition    = $bottomPosition === null ? $this->listifyTop() : $bottomPosition + 1;

            $this->setListPosition($newPosition);
        });

        return $this;
    }

    /**
     * Moves the record to the top of the list.
     * This re-arranges the affected surrounding records.
     *
     * @return $this
     */
    public function moveToTop()
    {
        if ($this->isNotInList()) {
            return $this;
        }

        $this->getConnection()->transaction(function () {

            $this->incrementPositionsOfItemsAbove();

            $this->setListPosition($this->listifyTop());
        });

        return $this;
    }

    /**
     * Removes the record from the list.
     * This re-arranges the affected surrounding records.
     *
     * @return $this
     */
    public function removeFromList()
    {
        if ( ! $this->isInList()) {
            return $this;
        }

        $this->decrementPositionsOfItemsBelow();
        $this->setListPosition(null);

        return $this;
    }

    /**
     * Increase the position of the record without affecting other record positions.
     * Note: does not save the position!
     *
     * @param integer $count default 1
     * @return $this
     */
    public function incrementPosition(int $count = 1)
    {
        if ($this->isNotInList()) {
            return $this;
        }

        $this->setListifyPosition($this->getListifyPosition() + $count);

        return $this;
    }

    /**
     * Decrease the position of the record without affecting other record positions.
     *
     * @param integer $count default 1
     * @return $this
     */
    public function decrementPosition(int $count = 1)
    {
        if ($this->isNotInList()) {
            return $this;
        }

        $newPosition = $this->getListifyPosition() - $count;

        if ($newPosition < $this->listifyTop()) {
            $newPosition = $this->listifyTop();
        }

        $this->setListifyPosition($newPosition);

        return $this;
    }


    /**
     * Returns whether the record is at the top of the list.
     *
     * @return boolean      also false if the record is not in a list
     */
    public function isFirst(): bool
    {
        if ($this->isNotInList()) {
            return false;
        }

        return $this->getListifyPosition() === $this->listifyTop();
    }

    /**
     * Returns whether the record is at the bottom of the list.
     *
     * @return boolean      also false if the record is not in a list
     */
    public function isLast(): bool
    {
        if ($this->isNotInList()) {
            return false;
        }

        return $this->getListifyPosition() === $this->getBottomPositionValue();
    }

    /**
     * Returns the previous record on the list, the one above the current record.
     * Note that higher means with a lower position.
     *
     * @return null|static
     */
    public function higherItem()
    {
        if ($this->isNotInList()) {
            return null;
        }

        return $this->listifyScopedQuery()
            ->where($this->positionColumn(), '<', $this->getListifyPosition())
            ->orderBy($this->getTable() . '.' . $this->positionColumn(), 'DESC')
            ->first();
    }

    /**
     * Returns a number of previous records on the list, above the current record.
     *
     * @param null|integer $limit the maximum number of records to retrieve
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function higherItems(?int $limit = null): Collection
    {
        if ($this->isNotInList()) {
            return null;
        }

        $query = $this->listifyScopedQuery()
            ->where($this->positionColumn(), '<', $this->getListifyPosition())
            ->orderBy($this->getTable() . '.' . $this->positionColumn(), 'DESC');

        if (null !== $limit) {
            $query->take($limit);
        }

        return $query->get();
    }


    /**
     * Returns the next record on the list, the one below the current record.
     *
     * @return null|static
     */
    public function lowerItem()
    {
        if ($this->isNotInList()) {
            return null;
        }

        return $this->listifyScopedQuery()
            ->where($this->positionColumn(), '>', $this->getListifyPosition())
            ->orderBy($this->getTable() . '.' . $this->positionColumn())
            ->first();
    }

    /**
     * Returns a number of next records on the list, below the current record.
     *
     * @param null|integer $limit the maximum number of records to retrieve
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function lowerItems(?int $limit = null): Collection
    {
        if ($this->isNotInList()) {
            return null;
        }

        $query = $this->listifyScopedQuery()
            ->where($this->positionColumn(), '>', $this->getListifyPosition())
            ->orderBy($this->getTable() . '.' . $this->positionColumn());

        if (null !== $limit) {
            $query->take($limit);
        }

        return $query->get();
    }


    /**
     * Returns whether the record is in a list.
     *
     * @return boolean
     */
    public function isInList(): bool
    {
        return $this->getListifyPosition() !== null;
    }

    /**
     * Returns whether the record is not in a list.
     *
     * @return boolean
     */
    public function isNotInList(): bool
    {
        return ! $this->isInList();
    }

    /**
     * Returns the default position to set for new records.
     *
     * @return null|integer
     */
    public function defaultPosition(): ?int
    {
        return null;
    }

    /**
     * Returns whether the records position is equal to the default.
     *
     * @return boolean
     */
    public function isDefaultPosition(): bool
    {
        return $this->getListifyPosition() === $this->defaultPosition();
    }

    /**
     * Sets a new position for the record and saves it.
     *
     * @param integer|null $position        null removes the item from the list
     * @return bool
     */
    public function setListPosition(?int $position): bool
    {
        $this->setListifyPosition($position);

        return $this->save();
    }


    // ------------------------------------------------------------------------------
    //      Scope checking
    // ------------------------------------------------------------------------------

    /**
     * Remembers what normalized string scope is now active.
     */
    protected function rememberCurrentlyUsedScope(): void
    {
        $this->stringScopeValue = $this->normalizeListifyScope($this->getScopeName());
    }

    /**
     * Returns whether the scope has changed since it was last applied.
     * Note that this also updates the last known scope, resetting the changed state back to false
     *
     * @return boolean
     */
    protected function hasListifyScopeChanged(): bool
    {
        $scope = $this->normalizeListifyScope($this->getScopeName(), true);

        if ($scope instanceof BelongsTo) {
            // for BelongsTo scopes, use a cleaner way to check for differences
            $foreignKey = $scope->getForeignKeyName();
            return (Arr::get($this->getOriginal(), $foreignKey) != $this->getAttribute($foreignKey));
        }

        if ($this->stringScopeValue === null && ! $this->stringScopeNullExplicitlySet) {

            // if the known previous scope is null, make sure this isn't the result of a
            // variable scope that returned null - if that is the case, the scope has changed
            if (null !== $scope && $this->hasVariableListifyScope()) {

                $this->swapChangedAttributes();
                $previousScope = $this->normalizeListifyScope($this->getScopeName());
                $this->swapChangedAttributes();

                if ($previousScope === null) {
                    return true;
                }
            }

            $this->stringScopeValue             = $scope;
            $this->stringScopeNullExplicitlySet = $scope === null;
            return false;
        }

        return $scope !== $this->stringScopeValue;
    }

    /**
     * Returns whether the record should be kept out of any lists.
     * This allows null-scope items to be handled when they occur.
     *
     * @return boolean
     */
    protected function excludeFromList(): bool
    {
        return $this->normalizeListifyScope($this->getScopeName()) === null;
    }

    /**
     * Returns whether the configured list scope is variable.
     *
     * @return bool
     */
    protected function hasVariableListifyScope(): bool
    {
        $scope = $this->getScopeName();

        return $scope instanceof BelongsTo || is_callable($scope);
    }

    /**
     * Normalizes the currently applicable list scope to a string
     * or BelongsTo relation instance.
     *
     * @param mixed   $scope
     * @param boolean $doNotResolveBelongsTo        if true, does not stringify a BelongsTo instance
     * @return string|BelongsTo
     */
    protected function normalizeListifyScope($scope, bool $doNotResolveBelongsTo = false)
    {
        if ($scope instanceof BelongsTo) {
            if ($doNotResolveBelongsTo) {
                return $scope;
            }

            return $this->getConditionStringFromBelongsTo($scope);
        }

        if ($scope instanceof QueryBuilder) {
            return $this->getConditionStringFromQueryBuilder($scope);
        }

        if (is_callable($scope)) {
            return $this->normalizeListifyScope( $scope($this) );
        }

        // scope can only be a string in this case
        return $scope;
    }

    /**
     * Returns string version of current listify scope.
     *
     * @return string
     */
    protected function listifyScopeCondition(): string
    {
        $this->stringScopeValue = $this->normalizeListifyScope($this->getScopeName());

        $this->stringScopeNullExplicitlySet = $this->stringScopeValue === null;

        return $this->stringScopeValue;
    }


    /**
     * Returns a fresh query builder after re-applying the listify scope condition.
     *
     * @return Builder
     */
    protected function listifyScopedQuery(): Builder
    {
        $model = new static;

        $model->setListifyConfig('scope', $this->listifyScopeCondition());

        // if set, call a method that may be used to remove global scopes
        // and other automatically active listify-scope breaking clauses
        if (method_exists($this, 'cleanListifyScopedQuery')) {
            $model = $this->cleanListifyScopedQuery($model);
        }

        return $model->whereRaw($this->listifyScopeCondition());
    }

    /**
     * Checks and handles when the model's list scope has changed.
     * Makes sure that the old list positions are adjusted and the model
     * is correctly placed in the new list.
     */
    protected function handleListifyScopeChange(): void
    {
        if ( ! $this->hasListifyScopeChanged()) {
            return;
        }

        $this->rememberCurrentlyUsedScope();

        // swap the attributes so the record is scoped in its old scope
        $this->swapChangedAttributes();

        if ( ! $this->excludeFromList() && $this->lowerItem()) {
            $this->decrementPositionsOfItemsBelow();
        }

        // swap the attributes back, so the item can be positioned in its new scope
        $this->swapChangedAttributes();

        // unset its position, so it will get the correct new position in its new scope
        $this->setListifyPosition(null);

        $this->performConfiguredAddMethod($this);
    }


    // ------------------------------------------------------------------------------
    //      Position reordering
    // ------------------------------------------------------------------------------

    /**
     * @param integer $position
     */
    protected function insertAtPositionAndReorder(int $position): void
    {
        if ($this->isInList()) {
            // if the record is already in the list

            $oldPosition = $this->getListifyPosition();

            if ($position === $oldPosition) {
                return;
            }

            $this->reorderPositionsOnItemsBetween($oldPosition, $position);

        } else {

            $this->incrementPositionsOfItemsBelow($position);
        }

        $this->setListPosition($position);
    }

    /**
     * Reorders the positions of all items between two affected positions.
     *
     * @param integer    $positionBefore
     * @param integer    $positionAfter
     * @param null|mixed $ignoreId
     */
    protected function reorderPositionsOnItemsBetween(int $positionBefore, int $positionAfter, $ignoreId = null): void
    {
        if ($positionBefore == $positionAfter) {
            return;
        }

        $ignoreConditionCallable = function ($query) use ($ignoreId) {
            /** @var Builder $query */
            return $query->where($this->getPrimaryKey(), '!=', $ignoreId);
        };

        if ($positionBefore < $positionAfter) {
            // increment the in-between positions

            $this->listifyScopedQuery()
                ->where($this->positionColumn(), '>', $positionBefore)
                ->where($this->positionColumn(), '<=', $positionAfter)
                ->when($ignoreId, $ignoreConditionCallable)
                ->decrement($this->positionColumn());

        } else {
            // decrement the in-between positions

            $this->listifyScopedQuery()
                ->where($this->positionColumn(), '>=', $positionAfter)
                ->where($this->positionColumn(), '<', $positionBefore)
                ->when($ignoreId, $ignoreConditionCallable)
                ->increment($this->positionColumn());
        }
    }

    /**
     * Increments the position values of records with a higher position value than the given.
     * Note that 'below' means a higher position value.
     *
     * @param null|integer $position    if null, uses this record's current position
     */
    protected function decrementPositionsOfItemsBelow(?int $position = null)
    {
        if ($this->isNotInList()) {
            return;
        }

        if ($position === null) {
            $position = $this->getListifyPosition();
        }

        $this->listifyScopedQuery()
            ->where($this->positionColumn(), '>', $position)
            ->decrement($this->positionColumn());
    }

    /**
     * Increments the position values of records with a lower position value than the given.
     * Note that 'above' means a lower position value.
     *
     * @param null|integer $position    if null, uses this record's current position
     */
    protected function incrementPositionsOfItemsAbove(?int $position = null): void
    {
        if ($this->isNotInList()) {
            return;
        }

        if ($position === null) {
            $position = $this->getListifyPosition();
        }

        $this->listifyScopedQuery()
            ->where($this->positionColumn(), '<', $position)
            ->increment($this->positionColumn());
    }

    /**
     * Increments the position values of records with a lower position value than the given.
     * Note that 'below' means a smaller position value.
     *
     * @param integer $position    if null, uses this record's current position
     */
    protected function incrementPositionsOfItemsBelow(int $position): void
    {
        $this->listifyScopedQuery()
            ->where($this->positionColumn(), '>=', $position)
            ->increment($this->positionColumn());
    }

    /**
     * Increments the position values of all records in the current list scope.
     */
    protected function incrementPositionsOfAllItems(): void
    {
        $this->listifyScopedQuery()->increment($this->positionColumn());
    }

    /**
     * Updates all record positions based on changed position of the record in the current list.
     */
    protected function updateListifyPositions(): void
    {
        $newPosition = $this->getListifyPosition();

        // check for duplicate positions and resolve them if required
        if ($newPosition === null) {
            return;
        }

        $count = $this->listifyScopedQuery()
            ->where($this->positionColumn(), $newPosition)
            ->count();

        if ($count < 2) {
            return;
        }

        $oldPosition = Arr::get($this->getOriginal(), $this->positionColumn(), false);

        if ($oldPosition === false) {
            return;
        }

        // reorder positions while excluding the current model
        $this->reorderPositionsOnItemsBetween($oldPosition, $newPosition, $this->id);
    }


    /**
     * Returns the value of the bottom position.
     *
     * @param null|Model|Listify $exclude   a model whose value to exclude in determining the position
     * @return integer|null
     */
    protected function getBottomPositionValue($exclude = null): ?int
    {
        $item = $this->getBottomItem($exclude);

        if ( ! $item) {
            return null;
        }

        return $item->getListifyPosition();
    }

    /**
     * Returns the bottom item.
     *
     * @param null|Model|Listify $exclude  a model to exclude as a match
     * @return null|Model|Listify
     */
    protected function getBottomItem(Model $exclude = null)
    {
        $query = $this->listifyScopedQuery()
            ->whereNotNull($this->getTable() . '.' . $this->positionColumn())
            ->orderBy($this->getTable() . '.' . $this->positionColumn(), 'desc')
            ->take(1);

        if ($exclude) {
            $query->where($this->getPrimaryKey(), '!=', $exclude->id);
        }

        return $query->first();
    }



    // ------------------------------------------------------------------------------
    //      String scope conditions
    // ------------------------------------------------------------------------------

    /**
     * Extracts a raw WHERE clause string from a QueryBuilder instance.
     * Note that this is practically identical to the original Listify.
     *
     * @param QueryBuilder $query A Query Builder instance
     * @return string
     */
    protected function getConditionStringFromQueryBuilder(QueryBuilder $query): string
    {
        $initialQueryChunks = explode('where ', $query->toSql());

        if (count($initialQueryChunks) === 1) {
            throw new InvalidArgumentException(
                'The query builder instance must have a where clause to build a condition string from'
            );
        }

        $queryChunks = explode('?', $initialQueryChunks[1]);
        $chunkCount  = count($queryChunks);
        $bindings    = $query->getBindings();
        $whereString = '';

        for ($i = 0; $i < $chunkCount; $i++) {

            $whereString .= $queryChunks[ $i ];

            if (isset($bindings[ $i ]) && is_string($bindings[ $i ])) {
                $whereString .= '"' . $bindings[ $i ] . '"';
            }
        }

        return $whereString;
    }

    /**
     * Makes a raw where condition string from a BelongsTo relation instance.
     *
     * @param BelongsTo $relation
     * @return null|string
     */
    protected function getConditionStringFromBelongsTo(BelongsTo $relation): ?string
    {
        $id = $this->getAttribute(
            $relation->getForeignKeyName()
        );

        // an empty foreign key will, as a null-scope, remove the item from any list
        if ($id === null) {
            return null;
        }

        return '`' . $relation->getForeignKeyName() . '` = ' . (int) $id;
    }

    /**
     * Returns the fully qualified primary key column for the model.
     *
     * @return string
     */
    protected function getPrimaryKey(): string
    {
        return $this->getConnection()->getTablePrefix() . $this->getQualifiedKeyName();
    }

    /**
     * Swaps changed (original) attributes with current attributes.
     *
     * @return $this
     */
    public function swapChangedAttributes()
    {
        if (false === $this->originalAttributesLoaded) {

            $this->swappedAttributes = $this->getAttributes();
            $this->fill($this->getOriginal());

            $this->originalAttributesLoaded = true;

            return $this;
        }

        if  ( ! count($this->swappedAttributes)) {
            $this->swappedAttributes = $this->getAttributes();
        }

        $this->fill($this->swappedAttributes);

        $this->originalAttributesLoaded = false;

        return $this;
    }

}
