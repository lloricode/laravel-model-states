<?php

namespace Spatie\ModelStates\Tests;

use Spatie\ModelStates\Tests\Dummy\ModelStates\ModelState;
use Spatie\ModelStates\Tests\Dummy\ModelStates\StateA;
use Spatie\ModelStates\Tests\Dummy\ModelStates\StateB;
use Spatie\ModelStates\Tests\Dummy\ModelStates\StateC;
use Spatie\ModelStates\Tests\Dummy\ModelStates\StateD;
use Spatie\ModelStates\Tests\Dummy\TestModel;
use Spatie\ModelStates\Tests\Dummy\TestModelWithDefault;

class StateTest extends TestCase
{
    /** @test */
    public function test_resolve_state_class()
    {
        $this->assertEquals(StateA::class, ModelState::resolveStateClass(StateA::class));
        $this->assertEquals(StateC::class, ModelState::resolveStateClass(StateC::class));
        $this->assertEquals(StateC::class, ModelState::resolveStateClass(StateC::getMorphClass()));
        $this->assertEquals(StateC::class, ModelState::resolveStateClass(StateC::$name));
        $this->assertEquals(StateD::class, ModelState::resolveStateClass(StateD::class));
        $this->assertEquals(StateD::class, ModelState::resolveStateClass(StateD::getMorphClass()));
        $this->assertEquals(StateD::class, ModelState::resolveStateClass(StateD::$name));
    }

    /** @test */
    public function transitionable_states()
    {
        $state = new StateA(new TestModel());

        $this->assertEquals([
            StateB::getMorphClass(),
            StateC::getMorphClass(),
            StateD::getMorphClass(),
        ], $state->transitionableStates());

        $modelB = TestModelWithDefault::create([
            'state' => StateC::class,
        ]);

        $this->assertEquals([], $modelB->state->transitionableStates());
    }

    /** @test */
    public function test_equals()
    {
        $modelA = TestModelWithDefault::create();

        $modelB = TestModelWithDefault::create();

        $this->assertTrue($modelA->state->equals($modelB->state));

        $modelA = TestModelWithDefault::create();

        $modelB = TestModelWithDefault::create([
            'state' => StateC::class,
        ]);

        $this->assertFalse($modelA->state->equals($modelB->state));

        $this->assertTrue($modelA->state->equals(StateA::class));
    }

    /** @test */
    public function test_can_transition_to()
    {
        $state = new StateA(new TestModel());

        $this->assertTrue($state->canTransitionTo(StateB::class));
        $this->assertTrue($state->canTransitionTo(StateC::class));

        $state = new StateB(new TestModel());

        $this->assertFalse($state->canTransitionTo(StateB::class));
        $this->assertFalse($state->canTransitionTo(StateA::class));
    }

    /** @test */
    public function test_get_states()
    {
        $states = TestModelWithDefault::getStates();

        $this->assertEquals(
            [
                'state' => [
                    StateA::getMorphClass(),
                    StateB::getMorphClass(),
                    StateC::getMorphClass(),
                    StateD::getMorphClass(),
                ],
            ],
            $states->toArray()
        );
    }

    /** @test */
    public function test_get_states_for()
    {
        $states = TestModelWithDefault::getStatesFor('state');

        $this->assertEquals(
            [
                StateA::getMorphClass(),
                StateB::getMorphClass(),
                StateC::getMorphClass(),
                StateD::getMorphClass(),
            ],
            $states->toArray()
        );
    }

    /** @test */
    public function test_get_default_states()
    {
        $states = TestModelWithDefault::getDefaultStates();

        $this->assertEquals(
            [
                'state' => StateA::getMorphClass(),
            ],
            $states->toArray()
        );
    }

    /** @test */
    public function test_get_default_states_for()
    {
        $defaultState = TestModelWithDefault::getDefaultStateFor('state');

        $this->assertEquals(StateA::getMorphClass(), $defaultState);
    }

    /** @test */
    public function test_make()
    {
        $stateA = ModelState::make(StateA::class, new TestModel());

        $this->assertInstanceOf(StateA::class, $stateA);

        $stateC = ModelState::make('C', new TestModel());

        $this->assertInstanceOf(StateC::class, $stateC);

        $stateD = ModelState::make(4, new TestModel());

        $this->assertInstanceOf(StateD::class, $stateD);
    }

    /** @test */
    public function test_all()
    {
        $this->assertEquals([
            StateA::getMorphClass() => StateA::class,
            StateB::getMorphClass() => StateB::class,
            StateC::getMorphClass() => StateC::class,
            StateD::getMorphClass() => StateD::class,
        ], ModelState::all()->toArray());
    }
}
