<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalculatorTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_handle(): void
    {
        $this->artisan('calculator -T')
            ->expectsQuestion('Please enter the calculation expression', '1+1')
            ->expectsOutput("The calculation result is 2")
            ->doesntExpectOutput("The calculation result is ")
            ->assertExitCode(0);

        $this->artisan('calculator -T')
            ->expectsQuestion('Please enter the calculation expression', '2/2')
            ->expectsOutput("The calculation result is 1")
            ->doesntExpectOutput("The calculation result is ")
            ->assertExitCode(0);

        $this->artisan('calculator -T')
            ->expectsQuestion('Please enter the calculation expression', '1+2*3')
            ->expectsOutput("The calculation result is 7")
            ->doesntExpectOutput("The calculation result is ")
            ->assertExitCode(0);

        $this->artisan('calculator -T')
            ->expectsQuestion('Please enter the calculation expression', '(1+2)*3')
            ->expectsOutput("The calculation result is 9")
            ->doesntExpectOutput("The calculation result is ")
            ->assertExitCode(0);
    }
}
