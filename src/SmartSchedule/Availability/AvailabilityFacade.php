<?php

declare(strict_types=1);

namespace DomainDrivers\SmartSchedule\Availability;

use DomainDrivers\SmartSchedule\Availability\Segment\SegmentInMinutes;
use DomainDrivers\SmartSchedule\Availability\Segment\Segments;
use DomainDrivers\SmartSchedule\Shared\TimeSlot\TimeSlot;

final readonly class AvailabilityFacade
{
    public function __construct(private ResourceAvailabilityRepository $availabilityRepository)
    {
    }

    public function createResourceSlots(ResourceAvailabilityId $resourceId, TimeSlot $timeSlot): void
    {
        $this->availabilityRepository->saveGroup(ResourceGroupedAvailability::of($resourceId, $timeSlot));
    }

    public function createResourceSlotsWitParent(ResourceAvailabilityId $resourceId, ResourceAvailabilityId $parentId, TimeSlot $timeSlot): void
    {
        $this->availabilityRepository->saveGroup(ResourceGroupedAvailability::withParent($resourceId, $timeSlot, $parentId));
    }

    public function block(ResourceAvailabilityId $resourceId, TimeSlot $timeSlot, Owner $requester): bool
    {
        $toBlock = $this->findGrouped($resourceId, $timeSlot);
        if ($toBlock->block($requester)) {
            return $this->availabilityRepository->saveCheckingVersions($toBlock);
        }

        return false;
    }

    public function release(ResourceAvailabilityId $resourceId, TimeSlot $timeSlot, Owner $requester): bool
    {
        $toRelease = $this->findGrouped($resourceId, $timeSlot);
        if ($toRelease->release($requester)) {
            return $this->availabilityRepository->saveCheckingVersions($toRelease);
        }

        return false;
    }

    public function disable(ResourceAvailabilityId $resourceId, TimeSlot $timeSlot, Owner $requester): bool
    {
        $toDisable = $this->findGrouped($resourceId, $timeSlot);
        if ($toDisable->disable($requester)) {
            return $this->availabilityRepository->saveCheckingVersions($toDisable);
        }

        return false;
    }

    public function find(ResourceAvailabilityId $resourceId, TimeSlot $within): ResourceGroupedAvailability
    {
        return new ResourceGroupedAvailability(
            $this->availabilityRepository->loadAllWithinSlot(
                $resourceId,
                Segments::normalizeToSegmentBoundaries($within, SegmentInMinutes::defaultSegment()
                ))
        );
    }

    public function findByParentId(ResourceAvailabilityId $parentId, TimeSlot $within): ResourceGroupedAvailability
    {
        return new ResourceGroupedAvailability(
            $this->availabilityRepository->loadAllByParentIdWithinSlot(
                $parentId,
                Segments::normalizeToSegmentBoundaries($within, SegmentInMinutes::defaultSegment()
                ))
        );
    }

    private function findGrouped(ResourceAvailabilityId $resourceId, TimeSlot $within): ResourceGroupedAvailability
    {
        return new ResourceGroupedAvailability(
            $this->availabilityRepository->loadAllWithinSlot(
                $resourceId,
                Segments::normalizeToSegmentBoundaries($within, SegmentInMinutes::defaultSegment()
                ))
        );
    }
}
