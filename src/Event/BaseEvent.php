<?php
declare(strict_types=1);

namespace AuditStash\Event;

use AuditStash\EventInterface;
use DateTime;
use ReturnTypeWillChange;

/**
 * Represents a change in the repository where the list of changes can be
 * tracked as a list of properties and their values.
 */
abstract class BaseEvent implements EventInterface
{
    use BaseEventTrait;
    use SerializableEventTrait;

    /**
     * The array of changed properties for the entity.
     *
     * @var array|null
     */
    protected ?array $changed;

    /**
     * The array of original properties before they got changed.
     *
     * @var array|null
     */
    protected ?array $original;

    /**
     * Constructor.
     *
     * @param string $transactionId The global transaction id
     * @param mixed $id The entities primary key
     * @param string $source The name of the source (table)
     * @param ?string $parentSource The name of the parent source (table) for associated records
     * @param array|null $changed The array of changes that got detected for the entity
     * @param array|null $original The original values the entity had before it got changed
     * @param string|int|null $displayValue Human-readable field to identify records
     */
    public function __construct(
        string $transactionId,
        mixed $id,
        string $source,
        ?string $parentSource,
        ?array $changed,
        ?array $original,
        string|int|null $displayValue
    ) {
        $this->transactionId = $transactionId;
        $this->id = $id;
        $this->source = $source;
        $this->parentSource = $parentSource;
        $this->changed = $changed;
        $this->original = $original;
        $this->displayValue = $displayValue;
        $this->timestamp = (new DateTime())->format(DateTime::ATOM);
    }

    /**
     * Returns an array with the properties and their values before they got changed.
     *
     * @return array|null
     */
    public function getOriginal(): ?array
    {
        return $this->original;
    }

    /**
     * Returns an array with the properties and their values as they were changed.
     *
     * @return array|null
     */
    public function getChanged(): ?array
    {
        return $this->changed;
    }

    /**
     * Returns the name of this event type.
     *
     * @return string
     */
    abstract public function getEventType(): string;

    /**
     * Returns the array to be used for encoding this object as json.
     *
     * @return mixed
     */
    #[ReturnTypeWillChange]
    public function jsonSerialize(): mixed
    {
        return $this->basicSerialize() + [
            'original' => $this->original,
            'changed' => $this->changed,
        ];
    }
}
