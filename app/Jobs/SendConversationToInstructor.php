<?php

namespace App\Jobs;

use App\Actions\MasteryInsights\FormatAnswersForAI;
use App\Actions\MasteryInsights\GenerateFakeAIResponse;
use App\Actions\MasteryInsights\GetAIPersonality;
use App\Models\Conversation;
use App\Models\Dialog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Models\User;
use Carbon\Carbon;
use OpenAI\Laravel\Facades\OpenAI;

class SendConversationToInstructor implements ShouldQueue
{
    use Queueable;

    public ?User $user = null;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Conversation $conversation,
        $userid,
    )
    {
        $this->user = User::find($userid);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $personality = GetAIPersonality::execute($this->conversation->insight->ai_generated);
        $question = $this->conversation->insight->question;
        $answers = FormatAnswersForAI::execute($question);
        $dialogMessages = [];
        $users[$this->user->id] = $this->user->name;

        foreach ($this->conversation->dialogs as $dialog) {
            if ($dialog->user_id && !isset($users[$dialog->user_id])) {
                $thisUser = User::find($dialog->user_id)->first();
                $users[$thisUser->id] = $thisUser->name;
            }

            if ($dialog->user_id) {
                $dialogMessages[] = [
                    'role' => 'user',
                    'content' => 'User: ' . $users[$dialog->user_id] . '\nMessage: ' . $dialog->message,
                ];
            } else {
                $dialogMessages[] = [
                    'role' => 'assistant',
                    'content' => $dialog->message,
                ];
            }
        }

        $systemMessages = [
            [
                'role' => 'system',
                'content' =>
                    config('personalities.task_have_dialog') . '\n' .
                    config('personalities.context') . '\n' .
                    config('personalities.format_have_dialog') . '\n' .
                    $personality['persona'] . '\n' .
                    $personality['tone'] . '\n',
            ],
            [
                'role' => 'user',
                'content' =>
                    'Exam: ' . $question->set->name .
                    '\nExam Description: ' . $question->set->description,
            ],
            [
                'role' => 'assistant',
                'content' => 'This is the Insight that you gave for the question at hand which should include the question and some of the answers:\n\n' . $this->conversation->insight->insight_text,
            ],
        ];

        $messages = array_merge($systemMessages, $dialogMessages);

        $response = $this->ContactAI($messages);

        if ($response['success']) {
            $dialog = Dialog::create([
                'conversation_id' => $this->conversation->id,
                'personality_id' => $this->conversation->insight->ai_generated,
                'message' => $response['data'],
            ]);

            $this->conversation->last_message_date = Carbon::now();
            $this->conversation->save();
        } else {
            $dialog = Dialog::create([
                'conversation_id' => $this->conversation->id,
                'personality_id' => 0,
                'message' => $response['error'] . $response['details'],
            ]);

            $this->conversation->last_message_date = Carbon::now();
            $this->conversation->save();
        }
    }

    public function ContactAI($messages)
    {
        try {
            if (config('personalities.model') == 'none') {
                return GenerateFakeAIResponse::execute();
            }

            $result = OpenAI::chat()->create([
                'model' => config('personalities.model'),
                'messages' => $messages,
            ]);

            return [
                'success' => true,
                'data' => $result->choices[0]->message->content,
            ];
        } catch (\OpenAI\Exceptions\ErrorException $e) {
            logger()->error('OpenAI API Error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'There was an issue with the OpenAI API. Please try again later.',
                'details' => $e->getMessage(),
            ];
        } catch (\Exception $e) {
            logger()->error('General Error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'An unexpected error occurred.',
                'details' => $e->getMessage(),
            ];
        }
    }
}
