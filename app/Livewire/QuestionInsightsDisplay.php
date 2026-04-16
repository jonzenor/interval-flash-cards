<?php

namespace App\Livewire;

use App\Actions\MasteryInsights\RecordInsightResponse;
use App\Actions\MasteryInsights\RequestNewInsightFromAI;
use App\Models\Insight;
use App\Models\Question;
use Flux\Flux;
use Livewire\Component;
use Livewire\Attributes\On;

#[On('refresh-the-component')]
class QuestionInsightsDisplay extends Component
{
    public ?Question $question = null;
    public $insights = [];
    private $personalities = [];

    public function mount(Question $question)
    {
        $this->question = $question;
        $this->getInsights();
    }

    public function summon(Question $question, $id)
    {
        $this->question = $question;
        $response = RequestNewInsightFromAI::execute($question, $id);

        if ($response['success']) {
            $insight = RecordInsightResponse::execute($question, $id, $response['data']);
            $this->insights[$id] = $insight;
            $this->dispatch('refresh-the-component');
            Flux::toast('Instructor summoned!');
        } else {
            Flux::toast(variant: 'danger', text: 'There was an error communicating with the Instructor. Please try again later.');
        }
    }

    public function getInsights()
    {
        $insights = [];
        $personalities = [];

        for ($i = 0; $i <= config('personalities.ai_count'); $i ++) {
            if ($i == 0) {
                $personalities[$i] = [
                    'id' => 0,
                    'name' => $this->question->set->user->name,
                    'title' => 'Author',
                    'species' => 'human',
                    'avatarUrl' => $this->question->set->user->gravatarUrl(),
                ];
            } else {
                $personalities[$i] = config('personalities.ai.' . $i);
                $personalities[$i]['avatarUrl'] = asset('images/' . $personalities[$i]['avatar']);
            }

            $insights[$i] = $this->question->insights->where('ai_generated', $i)->first() ?? null;
        }

        $this->personalities = $personalities;
        $this->insights = $insights;
    }

    public function render()
    {
        $personalities = [];
        $this->getInsights();

        return view('livewire.question-insights-display', [
            'insights' => $this->insights,
            'personalities' => $this->personalities,
            'question' => $this->question,
        ]);
    }
}
