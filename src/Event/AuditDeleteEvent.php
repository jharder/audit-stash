<?php
declare(strict_types=1);

namespace AuditStash\Event;

use AuditStash\EventInterface;
use DateTime;

/**
 * Represents an audit log event for a newly deleted record.
 */
class AuditDeleteEvent implements EventInterface
{
    use BaseEventTrait;
    use SerializableEventTrait {
        basicSerialize as public jsonSerialize;
    }

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
     * @param mixed $id The primary key record that got deleted
     * @param string $source The name of the source (table) where the record was deleted
     * @param string|null $parentSource The name of the source (table) that triggered this change
     * @param string|null $displayValue The display field's value
     * @param DateTime $timestamp Timestamp of delete event
     */
    public function __construct(
        string $transactionId,
        mixed $id,
        string $source,
        ?string $parentSource,
        ?array $original,
        ?string $displayValue
    ) {
        $this->transactionId = $transactionId;
        $this->id = $id;
        $this->source = $source;
        $this->parentSource = $parentSource;
        $this->original = $original;
        $this->displayValue = $displayValue;
        $this->timestamp = (new DateTime())->format(DateTime::ATOM);
    }

    /**
     * Returns the name of this event type.
     *
     * @return string
     */
    public function getEventType(): string
    {
        return 'delete';
    }

    /**
     * Returns the original data.
     *
     * @return array|null
     */
    public function getOriginal(): ?array
    {
        return $this->original;
    }

    /**
     * Returns the changed data.
     *
     * @return array|null
     */
    public function getChanged(): ?array
    {
        return $this->changed;
    }
}
