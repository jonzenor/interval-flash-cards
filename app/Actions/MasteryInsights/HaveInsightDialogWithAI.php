<?php

namespace App\Actions\MasteryInsights;

use App\Models\Conversation;
use App\Models\Insight;
use App\Models\Question;
use App\Models\User;
use OpenAI\Laravel\Facades\OpenAI;

class HaveInsightDialogWithAI
{
    public static function execute(Conversation $conversation): Array
    {
        $personality = GetAIPersonality::execute($conversation->insight->ai_generated);
        $question = $conversation->insight->question;
        $answers = FormatAnswersForAI::execute($question);
        $dialogMessages = array();
        $users[auth()->id()] = auth()->user()->name;

        foreach ($conversation->dialogs as $dialog) {
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
                'content' => 'This is the Insight that you gave for the question at hand which should include the question and some of the answers:\n\n' . $conversation->insight->insight_text,
            ],
        ];

        $messages = array_merge($systemMessages, $dialogMessages);

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
                'data' => $result->choices[0]->message->content, // Extract the content
            ];
        } catch (\OpenAI\Exceptions\ErrorException $e) {
            logger()->error('OpenAI API Error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'There was an issue with the OpenAI API. Please try again later.',
                'details' => $e->getMessage(), // Optional: include detailed error message
            ];
        } catch (\Exception $e) {
            logger()->error('General Error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'An unexpected error occurred.',
                'details' => $e->getMessage(), // Optional: include detailed error message
            ];
        }
    }
}
