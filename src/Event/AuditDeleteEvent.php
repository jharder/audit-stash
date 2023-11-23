<?php
declare(strict_types=1);

namespace AuditStash\Event;

/**
 * Represents an audit log event for a deleted record.
 */
class AuditDeleteEvent extends BaseEvent
{
    /**
     * Returns the type name of this event object.
     *
     * @return string
     */
    public function getEventType(): string
    {
        return 'delete';
    }
}
