<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\TicketStatusTransition;
use PHPUnit\Framework\TestCase;

class TicketStatusTransitionTest extends TestCase
{
    public function test_the_service_desk_workflow_is_enforced(): void
    {
        $transitions = new TicketStatusTransition;

        $this->assertTrue($transitions->allows('new', 'assigned'));
        $this->assertTrue($transitions->allows('new', 'resolved'));
        $this->assertTrue($transitions->allows('assigned', 'in-progress'));
        $this->assertTrue($transitions->allows('assigned', 'resolved'));
        $this->assertTrue($transitions->allows('in-progress', 'awaiting-user'));
        $this->assertTrue($transitions->allows('awaiting-user', 'resolved'));
        $this->assertFalse($transitions->allows('resolved', 'closed'));

        $this->assertFalse($transitions->allows('new', 'closed'));
        $this->assertFalse($transitions->allows('assigned', 'closed'));
        $this->assertFalse($transitions->allows('closed', 'in-progress'));
        $this->assertSame([], $transitions->targetsFor('closed'));
    }
}
