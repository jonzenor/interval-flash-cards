<?php

namespace Tests\Feature;

use App\Http\Controllers\ExamSessionController;
use Config;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ExamSessionControllerTest extends TestCase
{
    /** @test */
    public function justAttainedMasteryLevel_returns_true(): void {
        Config::set('test.grade_familiar', 2);
        Config::set('test.add_score', 1);

        $controller = new ExamSessionController();

        $result = $controller->justAttainedMasteryLevel(1, 2, 'familiar');

        $this->assertEquals(true, $result);
    }

    /** @test */
    public function scoreIsMasteryLevel_returns_true_for_familiar(): void {
        Config::set('test.grade_familiar', 2);

        $controller = new ExamSessionController();

        $result = $controller->scoreIsMasteryLevel(2, 'familiar');

        $this->assertEquals(true, $result);
    }

    /** @test */
    public function calculateUpdatedMastery_returns_results_for_familiar_with_bonus_points(): void {
        Config::set('test.grade_apprentice', 1);
        Config::set('test.grade_familiar', 2);
        Config::set('test.add_score', 1);

        $mockSession = (object)[
            'mastery_apprentice_change' => 0,
            'mastery_familiar_change' => 0,
            'mastery_proficient_change' => 0,
            'mastery_mastered_change' => 0,
        ];

        $controller = new ExamSessionController();

        $result = $controller->calculateUpdatedMastery(0, 2, $mockSession);

        $this->assertEquals(1, $result['mastery_apprentice_change']);
        $this->assertEquals(1, $result['mastery_familiar_change']);
    }
}
