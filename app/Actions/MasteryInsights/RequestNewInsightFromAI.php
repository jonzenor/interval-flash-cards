<?php

namespace App\Actions\MasteryInsights;

use App\Models\Insight;
use App\Models\Question;
use OpenAI\Laravel\Facades\OpenAI;

class RequestNewInsightFromAI
{
    public static function execute(Question $question, $personality_id): Array
    {
        if (!$question) {
            return "ERROR, no Question supplied!";
        }

        $personality = GetAIPersonality::execute($personality_id);

        $answers = FormatAnswersForAI::execute($question);

        $authorInsight = Insight::where('question_id', $question->id)->where('ai_generated', 0)->first();
        $authorText = ($authorInsight == null) ? '' : '\nThe author gave this additional insight for this question: ' . $authorInsight->insight_text;

        try {
            if (config('personalities.model') == 'none') {
                return GenerateFakeAIResponse::execute();
                sleep(3);
            }

            $result = OpenAI::chat()->create([
                'model' => config('personalities.model'),
                'messages' => [
                    [
                        'role' => 'system',
                        'content' =>
                            config('personalities.task_give_insight') . '\n' .
                            config('personalities.context') . '\n' .
                            config('personalities.format_give_insight') . '\n' .
                            $personality['persona'] . '\n' .
                            $personality['tone'] . '\n',
                    ],
                    [
                        'role' => 'user',
                        'content' =>
                            'I need help with this test question. Exam: ' . $question->set->name .
                            '\nExam Description: ' . $question->set->description .
                            '\nQuestion Text: ' . $question->text .
                            '\n' . $answers .
                            $authorText,
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
