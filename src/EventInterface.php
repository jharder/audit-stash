<?php
declare(strict_types=1);

namespace AuditStash;

use JsonSerializable;
use Serializable;

/**
 * Represents an event in a particular entity in a repository.
 */
interface EventInterface extends JsonSerializable, Serializable
{
    /**
     * Returns the name of this event type.
     *
     * @return string
     */
    public function getEventType(): string;

    /**
     * Returns the global transaction id in which this event is contained.
     *
     * @return string
     */
    public function getTransactionId(): string;

    /**
     * Returns the id of the entity that was created or altered.
     *
     * @return mixed
     */
    public function getId(): mixed;

    /**
     * Returns the repository name in which the entity is.
     *
     * @return string
     */
    public function getSourceName(): string;

    /**
     * Returns the parent repository name in which the entity's parent is.
     *
     * @return string
     */
    public function getParentSourceName(): ?string;

    /**
     * Returns the time string in which this change happened.
     *
     * @return string
     */
    public function getTimestamp(): string;

    /**
     * Returns an array with meta information that can describe this event.
     *
     * @return array
     */
    public function getMetaInfo(): array;

    /**
     * Sets the meta information that can describe this event.
     *
     * @param array $meta The meta information to attach to the event
     * @return void
     */
    public function setMetaInfo(array $meta): void;

    /**
     * Returns the display field value.
     * The display field is set via the Model.setDisplayField($fieldName) property
     *
     * @return string|int|null
     */
    public function getDisplayValue(): string|int|null;

    /**
     * Returns an array with the properties and their values before they got changed.
     *
     * @return array|null
     */
    public function getOriginal(): ?array;

    /**
     * Returns an array with the properties and their values as they were changed.
     *
     * @return array|null
     */
    public function getChanged(): ?array;
}
