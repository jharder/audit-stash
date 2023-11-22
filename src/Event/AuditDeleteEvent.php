<?php
declare(strict_types=1);

namespace AuditStash\Event;

/**
 * Represents an audit log event for a newly deleted record.
 */
class AuditDeleteEvent extends BaseEvent
{
    /**
     * Returns the name of this event type.
     *
     * @return string
     */
    public function getEventType(): string
    {
        return 'delete';
    }
}
