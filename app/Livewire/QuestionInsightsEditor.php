<?php

namespace App\Livewire;

use App\Actions\Instructors\GetInstructorInsights;
use App\Actions\Instructors\GetInstructorPersonalities;
use App\Actions\MasteryInsights\RecordInsightResponse;
use App\Models\Insight;
use App\Models\Question;
use Flux\Flux;
use Livewire\Component;
use Livewire\Attributes\Validate;
use Livewire\Attributes\On;

#[On('refresh-the-component')]

class QuestionInsightsEditor extends Component
{
    public ?Insight $authorInsight = null;
    public Question $question;
    public $instructorInsights = array();
    public $personalities = [];

    #[Validate('string|required')]
    public string $insight_text = '';


    public function mount(Question $question)
    {
        $this->question = $question;
        $this->authorInsight = $question->insights->whereStrict('ai_generated', 0)->first();
        $this->insight_text = $this->authorInsight?->insight_text ?? '';
        $this->personalities = GetInstructorPersonalities::execute();
        $this->instructorInsights = GetInstructorInsights::execute($question);
    }

    public function save()
    {
        $this->validate();

        $this->authorInsight = RecordInsightResponse::execute($this->question, 0, $this->insight_text);

        Flux::toast('Mastery Insight Saved');
        $this->reloadComponent($this->question);
    }

    public function deleteInsight(Question $question, $insightId)
    {
        $insight = Insight::find($insightId);
        $this->question = $question;

        if (!$insight || ($insight->question_id != $question->id)) {
            Flux::toast(variant: 'danger', text: 'Invalid Insight ID!');
            $this->reloadComponent($question);
            return 0;
        }

        $insight->delete();
        Flux::toast('Instructor Insight deleted');
        $this->reloadComponent($question);
    }

    public function reloadComponent(Question $question)
    {
        $this->mount($question);

        // $this->dispatch('refresh-the-component');
    }

    public function render()
    {
        return view('livewire.question-insights-editor', [
            'personalities' => $this->personalities,
        ]);
    }
}
