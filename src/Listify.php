<?php
namespace Czim\Listify;

use Czim\Listify\Contracts\ListifyInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder as QueryBuilder;
use InvalidArgumentException;

/**
 * Class Listify
 *
 * @method static QueryBuilder|Model listifyScope()
 * @method static QueryBuilder|Model inList()
 */
trait Listify
{

    /**
     * Listify configuration
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
     * The current raw scope string
     *
     * @var string
     */
    protected $stringScopeValue;

    /**
     * Whether the original attributes are currently loaded (swapped state)
     *
     * @var bool
     */
    protected $originalAttributesLoaded = false;

    /**
     * Container for temporarily swapped attributes
     *
     * @var mixed[]
     */
    protected $swappedAttributes = [];


    public static function bootListify()
    {
        static::deleting(function ($model) {
            /** @var Listify $model */
            $model->setListifyPosition($model->getOriginal()[ $model->positionColumn() ]);
        });

        static::deleted(function ($model) {
            /** @var Listify $model */
            $model->decrementPositionsOfItemsBelow();
        });

        static::updating(function ($model) {
            /** @var Listify $model */
            $model->handleListifyScopeChange();
        });

        static::updated(function ($model) {
            /** @var Listify $model */
            $model->updateListifyPositions();
        });

        static::creating(function ($model) {
            /** @var Listify $model */
            $model->performConfiguredAddMethod($model);
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
    public function initListify(array $options = [])
    {
        $this->listifyConfig = array_replace($this->listifyConfig, $options);
    }

    /**
     * Sets a listify config value.
     *
     * @param string
     * @param mixed
     * @return $this
     */
    public function setListifyConfig($key, $value)
    {
        switch ($key) {

            case 'add_new_at':
                if ( ! method_exists($this, "addToList{$value}")) {
                    throw new \UnexpectedValueException("No method exists for applying the add_new_at value '{$value}'.");
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
    public function listifyTop()
    {
        return (int) $this->getListifyConfigValue('top_of_list');
    }

    /**
     * Returns the name of the position column.
     *
     * @return string
     */
    public function positionColumn()
    {
        return $this->getListifyConfigValue('column');
    }

    /**
     * Returns the listify scope definition.
     *
     * @return mixed
     */
    public function scopeName()
    {
        return $this->getListifyConfigValue('scope');
    }

    /**
     * Returns the position indicator to add new entries to.
     *
     * @return string   bottom or top
     */
    public function addNewAt()
    {
        return $this->getListifyConfigValue('add_new_at');
    }

    /**
     * Returns a config value by key, or null if it does not exist
     *
     * @param string $key
     * @return null|mixed
     */
    protected function getListifyConfigValue($key)
    {
        return array_key_exists($key, $this->listifyConfig) ? $this->listifyConfig[ $key ] : null;
    }


    // ------------------------------------------------------------------------------
    //      Scopes
    // ------------------------------------------------------------------------------

    /**
     * Applies the listify scope to a query builder instance.
     * Eloquent scope method.
     *
     * @param \Illuminate\Database\Eloquent\Builder|QueryBuilder $query
     * @return \Illuminate\Database\Eloquent\Builder|QueryBuilder
     */
    public function scopeListifyScope($query)
    {
        return $query->whereRaw($this->scopeCondition());
    }

    /**
     * Applies conditions to a query in order to retrieve only the records that are in a list.
     * Eloquent scope method.
     *
     * @param \Illuminate\Database\Eloquent\Builder|QueryBuilder|Listify $query
     * @return \Illuminate\Database\Eloquent\Builder|QueryBuilder|Listify
     */
    public function scopeInList($query)
    {
        return $query->listifyScope()->whereNotNull($this->getTable() . "." . $this->positionColumn());
    }


    // ------------------------------------------------------------------------------
    //      Position / list modification
    // ------------------------------------------------------------------------------

    /**
     * Returns the record's current list position.
     *
     * @return integer
     */
    public function getListifyPosition()
    {
        return $this->getAttribute($this->positionColumn());
    }

    /**
     * Sets the record's current list position directly.
     * No checks or changes are applied.
     *
     * @param integer $position
     * @return $this
     */
    public function setListifyPosition($position)
    {
        $this->setAttribute($this->positionColumn(), $position);

        return $this;
    }

    /**
     * @param Model|Listify $model
     */
    protected function performConfiguredAddMethod($model)
    {
        if ( ! $model->addNewAt()) return;

        $addMethod = 'addToList' . $model->addNewAt();

        if ( ! method_exists($model, $addMethod)) {
            throw new \BadMethodCallException("Method '{$addMethod}' on listify model does not exist");
        }

        $model->$addMethod();
    }

    /**
     * Adds record to the top of the list.
     * Note that this does not save the model.
     */
    protected function addToListTop()
    {
        if ($this->isInList()) return;

        $this->incrementPositionsOfAllItems();

        $this->setListifyPosition($this->listifyTop());
    }

    /**
     * Adds record to the bottom of the list.
     * Note that this does not save the model.
     */
    protected function addToListBottom()
    {
        if ($this->isInList()) return;

        $newPosition = $this->getBottomPositionValue();

        if (null === $newPosition) {
            $newPosition = $this->listifyTop();
        } else {
            $newPosition += 1;
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
    public function insertAt($position = null)
    {
        if (null === $position) {
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
        if ( ! $this->lowerItem()) return $this;

        $this->getConnection()->transaction(function() {

            $this->lowerItem()->decrement($this->positionColumn());

            $this->increment($this->positionColumn());
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
        if ( ! $this->higherItem()) return $this;

        $this->getConnection()->transaction(function() {

            $this->higherItem()->increment($this->positionColumn());

            $this->decrement($this->positionColumn());
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
        if ($this->isNotInList()) return $this;

        $this->getConnection()->transaction(function() {

            $this->decrementPositionsOfItemsBelow();

            $bottomPosition = $this->getBottomPositionValue($this);
            $newPosition    = (null === $bottomPosition) ? $this->listifyTop() : $bottomPosition + 1;

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
        if ($this->isNotInList()) return $this;

        $this->getConnection()->transaction(function() {

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
        if ( ! $this->isInList()) return $this;

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
    public function incrementPosition($count = 1)
    {
        if ($this->isNotInList()) return $this;

        $this->setListifyPosition($this->getListifyPosition() + $count);

        return $this;
    }

    /**
     * Decrease the position of the record without affecting other record positions.
     *
     * @param integer $count default 1
     * @return $this
     */
    public function decrementPosition($count = 1)
    {
        if ($this->isNotInList()) return $this;

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
    public function isFirst()
    {
        if ($this->isNotInList()) return false;

        return ($this->getListifyPosition() == $this->listifyTop());
    }

    /**
     * Returns whether the record is at the bottom of the list.
     *
     * @return boolean      also false if the record is not in a list
     */
    public function isLast()
    {
        if ($this->isNotInList()) return false;

        return ($this->getListifyPosition() == $this->getBottomPositionValue());
    }

    /**
     * Returns the previous record on the list, the one above the current record.
     * Note that higher means with a lower position.
     *
     * @return null|static
     */
    public function higherItem()
    {
        if ($this->isNotInList()) return null;

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
    public function higherItems($limit = null)
    {
        if ($this->isNotInList()) return null;

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
        if ($this->isNotInList()) return null;

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
    public function lowerItems($limit = null)
    {
        if ($this->isNotInList()) return null;

        $query = $this->listifyScopedQuery()
            ->where($this->positionColumn(), '>', $this->getListifyPosition())
            ->orderBy($this->getTable() . '.' . $this->positionColumn());

        if (null !== $limit) {
            $query->take($limit);
        }

        return $query->get();
    }


    /**
     * Returns whether the record is in a list
     *
     * @return boolean
     */
    public function isInList()
    {
        return ($this->getListifyPosition() !== null);
    }

    /**
     * Returns whether the record is not in a list
     *
     * @return boolean
     */
    public function isNotInList()
    {
        return ! $this->isInList();
    }

    /**
     * Returns the default position to set for new records
     *
     * @return null|integer
     */
    public function defaultPosition()
    {
        return null;
    }

    /**
     * Returns whether the records position is equal to the default
     *
     * @return boolean
     */
    public function isDefaultPosition()
    {
        return ($this->getListifyPosition() === $this->defaultPosition());
    }

    /**
     * Sets a new position for the record and saves it
     *
     * @param integer|null $position        null removes the item from the list
     * @return bool
     */
    public function setListPosition($position)
    {
        $this->setListifyPosition($position);

        return $this->save();
    }


    // ------------------------------------------------------------------------------
    //      Scope checking
    // ------------------------------------------------------------------------------

    /**
     * Remembers what normalized string scope is now active
     */
    protected function rememberCurrentlyUsedScope()
    {
        $this->stringScopeValue = $this->normalizeListifyScope($this->scopeName());
    }

    /**
     * Returns whether the scope has changed since it was last applied
     * Note that this also updates the last known scope, resetting the changed state back to false
     *
     * @return boolean
     */
    protected function hasListifyScopeChanged()
    {
        $scope = $this->normalizeListifyScope($this->scopeName(), true);

        if ($scope instanceof BelongsTo) {
            // for BelongsTo scopes, use a cleaner way to check for differences
            return ($this->getOriginal()[ $scope->getForeignKey() ] != $this->getAttribute( $scope->getForeignKey()));
        }

        if (null === $this->stringScopeValue) {

            $this->stringScopeValue = $scope;
            return false;
        }

        return ($scope != $this->stringScopeValue);
    }

    /**
     * Normalizes the currently applicable list scope to a string or BelongsTo relation instance
     *
     * @param mixed   $scope
     * @param boolean $doNotResolveBelongsTo        if true, does not stringify a BelongsTo instance
     * @return string|BelongsTo
     */
    protected function normalizeListifyScope($scope, $doNotResolveBelongsTo = false)
    {
        if ($scope instanceof BelongsTo) {
            if ($doNotResolveBelongsTo) return $scope;

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
    protected function scopeCondition()
    {
        $this->stringScopeValue = $this->normalizeListifyScope($this->scopeName());

        return $this->stringScopeValue;
    }


    /**
     * Returns a fresh query builder after re-applying the listify scope condition
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function listifyScopedQuery()
    {
        $model = new static;

        $model->setListifyConfig('scope', $this->scopeCondition());

        return $model->listifyScope();
    }

    /**
     * Checks and handles when the model's list scope has changed.
     * Makes sure that the old list positions are adjusted and the model
     * is correctly placed in the new list.
     */
    protected function handleListifyScopeChange()
    {
        if ( ! $this->hasListifyScopeChanged()) return;

        $this->rememberCurrentlyUsedScope();

        // swap the attributes so the record is scoped in its old scope
        $this->swapChangedAttributes();

        if ($this->lowerItem()) {
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
    protected function insertAtPositionAndReorder($position)
    {
        if ($this->isInList()) {
            // if the record is already in the list

            $oldPosition = $this->getListifyPosition();

            if ($position == $oldPosition) return;

            $this->reorderPositionsOnItemsBetween($oldPosition, $position);

        } else {

            $this->incrementPositionsOfItemsBelow($position);
        }

        $this->setListPosition($position);
    }

    /**
     * Reorders the positions of all items between two affected positions
     *
     * @param integer      $positionBefore
     * @param integer      $positionAfter
     * @param null|integer $ignoreId
     */
    protected function reorderPositionsOnItemsBetween($positionBefore, $positionAfter, $ignoreId = null)
    {
        if ($positionBefore == $positionAfter) return;

        $ignoreIdCondition = '1 = 1';

        if ($ignoreId) {
            $ignoreIdCondition = $this->getPrimaryKey() . ' != ' . $ignoreId;
        }

        if ($positionBefore < $positionAfter) {
            // increment the in-between positions

            $this->listifyScopedQuery()
                ->where($this->positionColumn(), '>', $positionBefore)
                ->where($this->positionColumn(), '<=', $positionAfter)
                ->whereRaw($ignoreIdCondition)
                ->decrement($this->positionColumn());

        } else {
            // decrement the in-between positions

            $this->listifyScopedQuery()
                ->where($this->positionColumn(), '>=', $positionAfter)
                ->where($this->positionColumn(), '<', $positionBefore)
                ->whereRaw($ignoreIdCondition)
                ->increment($this->positionColumn());
        }
    }

    /**
     * Increments the position values of records with a higher position value than the given
     * Note that 'below' means a higher position value.
     *
     * @param null|integer $position    if null, uses this record's current position
     */
    protected function decrementPositionsOfItemsBelow($position = null)
    {
        if ($this->isNotInList()) return;

        if (null === $position) {
            $position = $this->getListifyPosition();
        }

        $this->listifyScopedQuery()
            ->where($this->positionColumn(), '>', $position)
            ->decrement($this->positionColumn());
    }

    /**
     * Increments the position values of records with a lower position value than the given
     * Note that 'above' means a lower position value.
     *
     * @param null|integer $position    if null, uses this record's current position
     */
    protected function incrementPositionsOfItemsAbove($position = null)
    {
        if ($this->isNotInList()) return;

        if (null === $position) {
            $position = $this->getListifyPosition();
        }

        $this->listifyScopedQuery()
            ->where($this->positionColumn(), '<', $position)
            ->increment($this->positionColumn());
    }

    /**
     * Increments the position values of records with a lower position value than the given
     * Note that 'below' means a smaller position value.
     *
     * @param integer $position    if null, uses this record's current position
     */
    protected function incrementPositionsOfItemsBelow($position)
    {
        $this->listifyScopedQuery()
            ->where($this->positionColumn(), '>=', $position)
            ->increment($this->positionColumn());
    }

    /**
     * Increments the position values of all records in the current list scope
     */
    protected function incrementPositionsOfAllItems()
    {
        $this->listifyScopedQuery()->increment($this->positionColumn());
    }

    /**
     * Updates all record positions based on changed position of the record in the current list
     */
    protected function updateListifyPositions()
    {
        $oldPosition = $this->getOriginal()[ $this->positionColumn() ];
        $newPosition = $this->getListifyPosition();

        // check for duplicate positions and resolve them if required

        if (null === $newPosition) return;

        $count = $this->listifyScopedQuery()
            ->where($this->positionColumn(), $newPosition)
            ->count();

        if ($count < 2) return;

        // reorder positions while excluding the current model
        $this->reorderPositionsOnItemsBetween($oldPosition, $newPosition, $this->id);
    }


    /**
     * Returns the value of the bottom position
     *
     * @param null|Model|ListifyInterface $exclude   a model whose value to exclude in determining the position
     * @return integer|null
     */
    protected function getBottomPositionValue($exclude = null)
    {
        $item = $this->getBottomItem($exclude);

        if ( ! $item) return null;

        return $item->getListifyPosition();
    }

    /**
     * Returns the bottom item
     *
     * @param null|Model|ListifyInterface $exclude  a model to exclude as a match
     * @return null|Model|ListifyInterface
     */
    protected function getBottomItem(Model $exclude = null)
    {
        $query = $this->listifyScopedQuery()
            ->whereNotNull($this->getTable() . '.' . $this->positionColumn())
            ->orderBy($this->getTable() . "." . $this->positionColumn(), "DESC")
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
     * Extracts a raw WHERE clause string from a QueryBuilder instance
     *
     * @todo make this more reliable, less clunky
     *
     * @param QueryBuilder $query A Query Builder instance
     * @return string
     */
    protected function getConditionStringFromQueryBuilder(QueryBuilder $query)
    {
        $initialQueryChunks = explode('where ', $query->toSql());

        if (count($initialQueryChunks) == 1) {
            throw new InvalidArgumentException(
                'The query builder instance must have a where clause to build a condition string from'
            );
        }

        $queryChunks = explode('?', $initialQueryChunks[1]);
        $bindings    = $query->getBindings();
        $whereString = '';

        for ($i = 0; $i < count($queryChunks); $i++) {

            // "boolean"
            // "integer"
            // "double" (for historical reasons "double" is returned in case of a float, and not simply "float")
            // "string"
            // "array"
            // "object"
            // "resource"
            // "NULL"
            // "unknown type"

            $whereString .= $queryChunks[ $i ];

            if (isset($bindings[ $i ])) {

                if (gettype($bindings[ $i ]) === 'string') {
                    $whereString .= '"' . $bindings[ $i ] . '"';
                }
            }
        }

        return $whereString;
    }

    /**
     * Makes a raw where condition string from a BelongsTo relation instance
     *
     * @param BelongsTo $relation
     * @return string
     */
    protected function getConditionStringFromBelongsTo(BelongsTo $relation)
    {
        $id = $this->getAttribute( $relation->getForeignKey() );

        // todo: this should be allowed, and revert to being interpreted as an 'unlisted' item
        if (null === $id) {
            return null;
            throw new \InvalidArgumentException("BelongsTo Foreign key value is null");
        }

        return '`' . $relation->getForeignKey() . '` = ' . (int) $id;
    }

    /**
     * Returns the fully qualified primary key column for the model
     *
     * @return string
     */
    protected function getPrimaryKey()
    {
        return $this->getConnection()->getTablePrefix() . $this->getQualifiedKeyName();
    }

    /**
     * Swaps changed (original) attributes with current attributes
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
