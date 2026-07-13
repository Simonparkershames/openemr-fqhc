<?php

/**
 * The MA rooming worklist for one clinic day (issue #37): who has checked in
 * and is waiting to be roomed, and who is roomed and with (or waiting for)
 * the care team. Both queues keep the day's time order.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Claude Code
 * @copyright Copyright (c) 2026 OpenEMR FQHC project
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\FQHC\Rooming;

final readonly class RoomingWorklist
{
    /**
     * @param list<RoomingQueueEntry> $awaitingRooming
     * @param list<RoomingQueueEntry> $withCareTeam
     */
    public function __construct(
        public string $date,
        public array $awaitingRooming,
        public array $withCareTeam,
    ) {
    }

    public function total(): int
    {
        return count($this->awaitingRooming) + count($this->withCareTeam);
    }

    /**
     * Every patient on the worklist, waiting queue first.
     *
     * @return list<RoomingQueueEntry>
     */
    public function all(): array
    {
        return [...$this->awaitingRooming, ...$this->withCareTeam];
    }
}
