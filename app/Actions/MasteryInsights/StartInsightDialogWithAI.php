<?php

namespace App\Actions\MasteryInsights;

use App\Models\Conversation;
use App\Models\Insight;
use App\Models\Question;
use OpenAI\Laravel\Facades\OpenAI;

class StartInsightDialogWithAI
{
    public static function execute(Conversation $conversation): Array
    {

        $personality = GetAIPersonality::execute($conversation->insight->ai_generated);

        $question = $conversation->insight->question;

        $answers = FormatAnswersForAI::execute($question);

        try {
            if (config('personalities.model') == 'none') {
                return GenerateFakeAIResponse::execute();
            }

            $result = OpenAI::chat()->create([
                'model' => config('personalities.model'),
                'messages' => [
                    [
                        'role' => 'system',
                        'content' =>
                            config('personalities.task_start_dialog') . '\n' .
                            config('personalities.context') . '\n' .
                            config('personalities.format_start_dialog') . '\n' .
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
                        'role' => 'user',
                        'content' => 'The user asking for help is Acolyte ' . auth()->user()->name,
                    ],
                ],
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
