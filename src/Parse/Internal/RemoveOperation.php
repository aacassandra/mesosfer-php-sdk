<?php
/**
 * Class RemoveOperation | Parse/Internal/RemoveOperation.php
 */

namespace Parse\Internal;

use Parse\ParseClient;
use Parse\ParseException;
use Parse\ParseObject;

/**
 * Class RemoveOperation - FieldOperation for removing object(s) from array
 * fields.
 *
 * @author Fosco Marotto <fjm@fb.com>
 * @package Parse\Internal
 */
class RemoveOperation implements FieldOperation
{
    /**
     * Array with objects to remove.
     *
     * @var array
     */
    private $objects;

    /**
     * Creates an RemoveOperation with the provided objects.
     *
     * @param array $objects Objects to remove.
     *
     * @throws ParseException
     */
    public function __construct($objects)
    {
        if (!is_array($objects)) {
            throw new ParseException('RemoveOperation requires an array.');
        }
        $this->objects = $objects;
    }

    /**
     * Gets the objects for this operation.
     *
     * @return mixed
     */
    public function getValue()
    {
        return $this->objects;
    }

    /**
     * Returns associative array representing encoded operation.
     *
     * @return array
     */
    public function _encode()
    {
        return ['__op'    => 'Remove',
                'objects' => ParseClient::_encode($this->objects, true), ];
    }

    /**
     * Takes a previous operation and returns a merged operation to replace it.
     *
     * @param FieldOperation $previous Previous operation.
     *
     * @throws ParseException
     *
     * @return FieldOperation Merged operation.
     */
    public function _mergeWithPrevious($previous)
    {
        if (!$previous) {
            return $this;
        }
        if ($previous instanceof DeleteOperation) {
            return $previous;
        }
        if ($previous instanceof SetOperation) {
            return new SetOperation(
                $this->_apply($previous->getValue(), $this->objects, null)
            );
        }
        if ($previous instanceof self) {
            $oldList = $previous->getValue();

            return new self(
                array_merge((array) $oldList, (array) $this->objects)
            );
        }
        throw new ParseException(
            'Operation is invalid after previous operation.'
        );
    }

    /**
     * Applies current operation, returns resulting value.
     *
     * @param mixed  $oldValue Value prior to this operation.
     * @param mixed  $obj      Value being applied.
     * @param string $key      Key this operation affects.
     *
     * @return array
     */
    public function _apply($oldValue, $obj, $key)
    {
        if (empty($oldValue)) {
            return [];
        }

        if (!is_array($oldValue)) {
            $oldValue = [$oldValue];
        }

        $newValue = [];
        foreach ($oldValue as $oldObject) {
            foreach ($this->objects as $newObject) {
                if ($oldObject instanceof ParseObject) {
                    if ($newObject instanceof ParseObject
                        && !$oldObject->isDirty()
                        && $oldObject->getObjectId() == $newObject->getObjectId()
                    ) {
                        // found the object, won't add it.
                    } else {
                        $newValue[] = $oldObject;
                    }
                } else {
                    if ($oldObject !== $newObject) {
                        $newValue[] = $oldObject;
                    }
                }
            }
        }

        return $newValue;
    }
}
