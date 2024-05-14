<?php

declare(strict_types=1);

namespace DomainDrivers\Tests\Unit\SmartSchedule\Availability;

use DomainDrivers\SmartSchedule\Availability\AvailabilityFacade;
use DomainDrivers\SmartSchedule\Availability\Owner;
use DomainDrivers\SmartSchedule\Availability\ResourceAvailabilityId;
use DomainDrivers\SmartSchedule\Shared\TimeSlot\TimeSlot;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[CoversClass(AvailabilityFacade::class)]
final class AvailabilityFacadeTest extends KernelTestCase
{
    private AvailabilityFacade $availabilityFacade;

    protected function setUp(): void
    {
        $this->availabilityFacade = self::getContainer()->get(AvailabilityFacade::class);
    }

    #[Test]
    public function canCreateAvailabilitySlots(): void
    {
        // given
        $resourceId = ResourceAvailabilityId::newOne();
        $oneDay = TimeSlot::createDailyTimeSlotAtUTC(2021, 1, 1);

        // when
        $this->availabilityFacade->createResourceSlots($resourceId, $oneDay);

        // then
        self::assertSame(96, $this->availabilityFacade->find($resourceId, $oneDay)->size());
    }

    #[Test]
    public function canCreateNewAvailabilitySlotsWithParentId(): void
    {
        // given
        $resourceId = ResourceAvailabilityId::newOne();
        $resourceId2 = ResourceAvailabilityId::newOne();
        $parentId = ResourceAvailabilityId::newOne();
        $differentParentId = ResourceAvailabilityId::newOne();
        $oneDay = TimeSlot::createDailyTimeSlotAtUTC(2021, 1, 1);

        // when
        $this->availabilityFacade->createResourceSlotsWitParent($resourceId, $parentId, $oneDay);
        $this->availabilityFacade->createResourceSlotsWitParent($resourceId2, $differentParentId, $oneDay);

        // then
        self::assertSame(96, $this->availabilityFacade->findByParentId($parentId, $oneDay)->size());
        self::assertSame(96, $this->availabilityFacade->findByParentId($differentParentId, $oneDay)->size());
    }

    #[Test]
    public function canBlockAvailabilities(): void
    {
        // given
        $resourceId = ResourceAvailabilityId::newOne();
        $oneDay = TimeSlot::createDailyTimeSlotAtUTC(2021, 1, 1);
        $owner = Owner::newOne();
        $this->availabilityFacade->createResourceSlots($resourceId, $oneDay);

        // when
        $result = $this->availabilityFacade->block($resourceId, $oneDay, $owner);

        // then
        self::assertTrue($result);
        $resourceAvailabilities = $this->availabilityFacade->find($resourceId, $oneDay);
        self::assertSame(96, $resourceAvailabilities->size());
        self::assertTrue($resourceAvailabilities->blockedEntirelyBy($owner));
    }

    #[Test]
    public function canDisableAvailabilities(): void
    {
        // given
        $resourceId = ResourceAvailabilityId::newOne();
        $oneDay = TimeSlot::createDailyTimeSlotAtUTC(2021, 1, 1);
        $owner = Owner::newOne();
        $this->availabilityFacade->createResourceSlots($resourceId, $oneDay);

        // when
        $result = $this->availabilityFacade->disable($resourceId, $oneDay, $owner);

        // then
        self::assertTrue($result);
        $resourceAvailabilities = $this->availabilityFacade->find($resourceId, $oneDay);
        self::assertSame(96, $resourceAvailabilities->size());
        self::assertTrue($resourceAvailabilities->isDisabledEntirelyBy($owner));
    }

    #[Test]
    public function cantBlockEvenWhenJustSmallSegmentOfRequestedSlotIsBlocked(): void
    {
        // given
        $resourceId = ResourceAvailabilityId::newOne();
        $oneDay = TimeSlot::createDailyTimeSlotAtUTC(2021, 1, 1);
        $owner = Owner::newOne();
        $this->availabilityFacade->createResourceSlots($resourceId, $oneDay);
        // and
        $this->availabilityFacade->block($resourceId, $oneDay, $owner);
        $fifteenMinutes = new TimeSlot($oneDay->from, $oneDay->from->modify('+15 minutes'));

        // when
        $result = $this->availabilityFacade->block($resourceId, $fifteenMinutes, Owner::newOne());

        // then
        self::assertFalse($result);
        $resourceAvailabilities = $this->availabilityFacade->find($resourceId, $oneDay);
        self::assertTrue($resourceAvailabilities->blockedEntirelyBy($owner));
    }

    #[Test]
    public function canReleaseAvailability(): void
    {
        // given
        $resourceId = ResourceAvailabilityId::newOne();
        $oneDay = TimeSlot::createDailyTimeSlotAtUTC(2021, 1, 1);
        $owner = Owner::newOne();
        $this->availabilityFacade->createResourceSlots($resourceId, $oneDay);
        // and
        $this->availabilityFacade->block($resourceId, $oneDay, $owner);

        // when
        $result = $this->availabilityFacade->release($resourceId, $oneDay, $owner);

        // then
        self::assertTrue($result);
        $resourceAvailabilities = $this->availabilityFacade->find($resourceId, $oneDay);
        self::assertTrue($resourceAvailabilities->isEntirelyAvailable());
    }

    #[Test]
    public function cantReleaseEvenWhenJustPartOfSlotIsOwnedByTheRequester(): void
    {
        // given
        $resourceId = ResourceAvailabilityId::newOne();
        $jan_1 = TimeSlot::createDailyTimeSlotAtUTC(2021, 1, 1);
        $jan_2 = TimeSlot::createDailyTimeSlotAtUTC(2021, 1, 2);
        $jan_1_2 = new TimeSlot($jan_1->from, $jan_2->to);
        $jan1owner = Owner::newOne();
        $this->availabilityFacade->createResourceSlots($resourceId, $jan_1_2);
        // and
        $this->availabilityFacade->block($resourceId, $jan_1, $jan1owner);
        // and
        $jan2owner = Owner::newOne();
        $this->availabilityFacade->block($resourceId, $jan_2, $jan2owner);

        // when
        $result = $this->availabilityFacade->release($resourceId, $jan_1_2, $jan1owner);

        // then
        self::assertFalse($result);
        $resourceAvailabilities = $this->availabilityFacade->find($resourceId, $jan_1);
        self::assertTrue($resourceAvailabilities->blockedEntirelyBy($jan1owner));
    }

    #[Test]
    public function oneSegmentCanBeTakenBySomeoneElseAfterRealising(): void
    {
        // given
        $resourceId = ResourceAvailabilityId::newOne();
        $oneDay = TimeSlot::createDailyTimeSlotAtUTC(2021, 1, 1);
        $fifteenMinutes = new TimeSlot($oneDay->from, $oneDay->from->modify('+15 minutes'));
        $owner = Owner::newOne();
        $this->availabilityFacade->createResourceSlots($resourceId, $oneDay);
        // and
        $this->availabilityFacade->block($resourceId, $oneDay, $owner);
        // and
        $this->availabilityFacade->release($resourceId, $fifteenMinutes, $owner);

        // when
        $newOwner = Owner::newOne();
        $result = $this->availabilityFacade->block($resourceId, $fifteenMinutes, $newOwner);

        // then
        self::assertTrue($result);
        $resourceAvailabilities = $this->availabilityFacade->find($resourceId, $oneDay);
        self::assertSame(96, $resourceAvailabilities->size());
        self::assertSame(95, $resourceAvailabilities->findBlockedBy($owner)->length());
        self::assertSame(1, $resourceAvailabilities->findBlockedBy($newOwner)->length());
    }
}
