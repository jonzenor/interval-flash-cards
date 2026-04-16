<?php

namespace App\Livewire;

use App\Actions\ExamSession\CalculateUsersMaxAvailableQuestions;
use App\Models\Set as ExamSet;
use Livewire\Component;

class ExamConfiguration extends Component
{
    public ExamSet $examSet;
    public $selectedLessonId = null;
    public $questionCount;
    public $maxQuestions;
    public $questionPoolCount;

    public function mount(ExamSet $examSet, int $maxQuestions)
    {
        $this->examSet = $examSet;
        $this->maxQuestions = $maxQuestions;
        $this->questionCount = ($maxQuestions < 10) ? $maxQuestions : 10;
        $this->questionPoolCount = $examSet->questions->count();
    }

    public function updatedSelectedLessonId()
    {
        $user = auth()->user();
        $lessonId = $this->selectedLessonId ? (int) $this->selectedLessonId : null;
        $this->maxQuestions = CalculateUsersMaxAvailableQuestions::execute(
            $user,
            $this->examSet,
            $lessonId
        );
        $this->questionCount = ($this->maxQuestions < 10) ? $this->maxQuestions : 10;
        
        // Update question pool count based on lesson selection
        if ($lessonId) {
            $this->questionPoolCount = $this->examSet->questions()->where('lesson_id', $lessonId)->count();
        } else {
            $this->questionPoolCount = $this->examSet->questions->count();
        }
    }

    public function render()
    {
        return view('livewire.exam-configuration');
    }
}
