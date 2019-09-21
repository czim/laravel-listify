<?php
namespace Czim\Listify\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

interface ListifyInterface
{

    /**
     * Sets up and overrides listify config.
     *
     * @param array $options
     */
    public function initListify(array $options = []): void;

    /**
     * Sets a listify config value.
     *
     * @param string $key
     * @param mixed  $value
     * @return $this
     */
    public function setListifyConfig(string $key, $value);



    /**
     * Applies the listify scope to a query builder instance.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeListifyScope(Builder $query): Builder;

    /**
     * Applies conditions to a query in order to retrieve only the records that are in a list.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeInList(Builder $query): Builder;



    /**
     * Returns the position to use as 'top of the list'.
     *
     * @return int
     */
    public function listifyTop(): int;

    /**
     * Returns the name of the position column.
     *
     * @return string
     */
    public function positionColumn(): string;

    /**
     * Returns the listify scope definition.
     *
     * @return mixed
     */
    public function getScopeName();

    /**
     * Returns the position indicator to add new entries to.
     *
     * @return string   bottom or top
     */
    public function addNewAt(): string;


    /**
     * Returns the record's current list position
     *
     * @return null|integer
     */
    public function getListifyPosition(): ?int;

    /**
     * Sets the record's current list position directly.
     * No checks or changes are applied.
     *
     * @param integer $position
     * @return $this
     */
    public function setListifyPosition(?int $position);


    /**
     * Inserts record at the given position.
     * This re-arranges the affected surrounding records.
     *
     * @param integer $position     default is listifyTop()
     * @return $this
     */
    public function insertAt(?int $position = null);

    /**
     * Moves the record down a position.
     * This re-arranges the affected surrounding records.
     *
     * @return $this
     */
    public function moveLower();

    /**
     * Moves the record up a position.
     * This re-arranges the affected surrounding records.
     *
     * @return $this
     */
    public function moveHigher();

    /**
     * Moves the record to the bottom of the list.
     * This re-arranges the affected surrounding records.
     *
     * @return $this
     */
    public function moveToBottom();

    /**
     * Moves the record to the top of the list.
     * This re-arranges the affected surrounding records.
     *
     * @return $this
     */
    public function moveToTop();

    /**
     * Removes the record from the list.
     * This re-arranges the affected surrounding records.
     *
     * @return $this
     */
    public function removeFromList();

    /**
     * Increase the position of the record without affecting other record positions.
     *
     * @param integer $count    default 1
     * @return $this
     */
    public function incrementPosition(int $count = 1);

    /**
     * Decrease the position of the record without affecting other record positions.
     *
     * @param integer $count    default 1
     * @return $this
     */
    public function decrementPosition(int $count = 1);


    /**
     * Returns whether the record is at the top of the list.
     *
     * @return boolean      also false if the record is not in a list
     */
    public function isFirst(): bool;

    /**
     * Returns whether the record is at the bottom of the list.
     *
     * @return boolean      also false if the record is not in a list
     */
    public function isLast(): bool;

    /**
     * Returns the previous record on the list, the one above the current record.
     * Note that higher means with a lower position.
     *
     * @return null|static
     */
    public function higherItem();

    /**
     * Returns a number of previous records on the list, above the current record.
     *
     * @param null|integer $limit    the maximum number of records to retrieve
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function higherItems(?int $limit = null): Collection;

    /**
     * Returns the next record on the list, the one below the current record.
     *
     * @return null|static
     */
    public function lowerItem();

    /**
     * Returns a number of next records on the list, below the current record.
     *
     * @param null|integer $limit    the maximum number of records to retrieve
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function lowerItems(?int $limit = null): Collection;


    /**
     * Returns whether the record is in a list
     *
     * @return boolean
     */
    public function isInList(): bool;

    /**
     * Returns whether the record is not in a list
     *
     * @return boolean
     */
    public function isNotInList(): bool;

    /**
     * Returns the default position to set for new records
     *
     * @return null|integer
     */
    public function defaultPosition(): ?int;

    /**
     * Returns whether the records position is equal to the default
     *
     * @return boolean
     */
    public function isDefaultPosition(): bool;

    /**
     * Sets a new position for the record and saves it
     *
     * @param integer|null $position
     * @return bool
     */
    public function setListPosition(?int $position): bool;

    /**
     * Swaps changed (original) attributes with current attributes
     *
     * @return $this
     */
    public function swapChangedAttributes();

}
