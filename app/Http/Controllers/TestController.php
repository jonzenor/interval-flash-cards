<?php

namespace App\Http\Controllers;

use Alert;
use App\Enums\Visibility;
use App\Models\Answer;
use App\Models\Question;
use App\Models\Set;
use App\Models\Test;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class TestController extends Controller
{
    public function sets(): View
    {
        $sets = Set::Where('visibility', '=', Visibility::isPublic)->get();

        $privateSets = null;

        if (auth()) {
            $privateSets = Set::Where('visibility', '=', Visibility::isPrivate)->where('user_id', '=', auth()->user()->id)->get();
        }

        return view('test.select', [
            'sets' => $sets,
            'privateSets' => $privateSets,
        ]);
    }

    public function select($id): View
    {
        $set = Set::find($id);

        return view('test.start', [
            'set' => $set,
        ]);
    }

    public function start(Request $request, $id): RedirectResponse
    {
        $set = Set::find($id);
        $user = Auth::user();

        $this->validate($request, [
            'number_questions' => 'required|integer|max:'.$set->questions->count(),
        ]);

        $now = new Carbon();
        $start = $now->subMinutes(2);

        // Load all questions to the user questions
        foreach ($set->questions as $question) {
            if (! $user->questions->contains($question)) {
                $user->questions()->attach($question->id, ['score' => 0, 'next_at' => $start, 'set_id' => $set->id]);
            }
        }

        // Make sure the user has questions left in the pool
        $questions = DB::table('user_question')->where('set_id', '=', $set->id)->where('next_at', '<=', $now)->get();

        if ($questions->count() < $request->number_questions) {
            Alert::warning('No more quesitons available. Please come back later.');

            return redirect()->route('home');
        }

        $test = new Test();
        $test->user_id = $user->id;
        $test->set_id = $set->id;
        $test->num_questions = $request->number_questions;
        $test->start_at = $now;
        $test->result = 0;
        $test->save();

        return redirect()->route('take-test', $test->id);
    }

    public function test($id)
    {
        $test = Test::find($id);
        $user = Auth::user();

        if ($user->id != $test->user_id) {
            Alert::toast('Invalid Test! Don\'t be a hacker.', 'warning');

            return redirect()->route('tests');
        }

        if ($test->ends_at) {
            Alert::toast('Test Completed', 'warning');

            return redirect()->route('home');
        }

        if ($test->questions->count() >= $test->num_questions) {
            $now = Carbon::now();
            $test->end_at = $now;

            $questions = $test->questions;
            $correct = 0;
            foreach ($questions as $question) {
                $result = DB::table('test_question')
                    ->where('test_id', '=', $test->id)
                    ->where('question_id', '=', $question->id)
                    ->select('result')
                    ->first();

                $correct = $correct + $result->result;
            }

            $grade = ($correct / $test->num_questions) * 100;
            $test->result = $grade;

            $test->save();

            Alert::success('Test Completed!<br />Your Grade: '.$grade.'%');

            return redirect()->route('home');
        }

        $now = Carbon::now();
        $single = false;

        $selecting = true;

        while ($selecting) {
            $question = DB::table('user_question')->where('set_id', '=', $test->set_id)->where('next_at', '<', $now)->get();

            $question = $question->random(1);

            $previous = DB::table('test_question')
                ->where('test_id', '=', $test->id)
                ->where('question_id', '=', $question[0]->question_id)
                ->first();

            if (! $previous) {
                $selecting = false;
            }
        }

        $question = Question::find($question[0]->question_id);

        if ($question->answers->count() > 1) {
            $answers = $question->answers->shuffle();
        } else {
            if ($question->group_id) {
                // There was only one answer, so let's grab some more random ones from the test
                $single = true;
                $answers = $question->answers;

                $pool = Question::where('set_id', '=', $question->set_id)->where('group_id', '=', $question->group_id)->where('id', '!=', $question->id)->get();

                $pool = $pool->shuffle();

                $count = 1;

                foreach ($pool as $q) {
                    $answers->push($q->answers->first());
                    $count = $count + 1;

                    if ($count == config('test.target_answers')) {
                        break;
                    }
                }

                $answers = $answers->shuffle();
            }
        }

        $correct = 0;
        $order = '';

        foreach ($answers as $answer) {
            if ($order != '') {
                $order .= ',';
            }

            $order .= $answer->id;

            if (! $single && $answer->correct) {
                $correct = $correct + 1;
            }
        }

        $multi = ($correct > 1) ? 1 : 0;

        return view('test.question', [
            'question' => $question,
            'answers' => $answers,
            'multi' => $multi,
            'test' => $test,
            'order' => $order,
        ]);
    }

    public function answer(Request $request, $id)
    {
        $test = Test::find($id);
        $user = Auth::user();

        if ($user->id != $test->user_id) {
            Alert::toast('Invalid Test! Don\'t be a hacker.', 'warning');

            return redirect()->route('tests');
        }

        $this->validate($request, [
            'question' => 'required|integer',
            'order' => 'required|string',
        ]);

        $question = Question::find($request->question);

        // This is just to see how many correct answers there are for this question
        $correctAnswer = 0;

        foreach ($question->answers as $answer) {
            if ($answer->correct) {
                $correctAnswer = $correctAnswer + 1;
            }
        }

        $multi = ($correctAnswer > 1) ? 1 : 0;
        $correct = 0;

        // An array to store / convert the checkbox answers into so we can compare easier
        $normalizedAnswer = [];

        // An array to store the answer and all results in to make displaying the correct answer easier
        $testAnswers = [];

        foreach ($question->answers as $answer) {
            if ($multi) {
                $normalizedAnswer[$answer->id] = (array_key_exists($answer->id, $request->answer)) ? 1 : 0;

                if ($answer->correct && ($normalizedAnswer[$answer->id] == 1)) {
                    $correct = $correct + 1;
                    $testAnswers[] = [
                        'id' => $answer->id,
                        'text' => $answer->text,
                        'correct' => $answer->correct,
                        'gotRight' => 1,
                    ];
                } else {
                    $testAnswers[] = [
                        'id' => $answer->id,
                        'text' => $answer->text,
                        'correct' => $answer->correct,
                        'gotRight' => 0,
                    ];
                }
            } elseif ($question->answers->count() == 1) {
                $correct = ($request->answer == $answer->id) ? 1 : 0;

                // If this is a single answer question, then we don't care if the user marked it as correct or not
                // We know that there is only one answer so it must be correct
                $correctAnswer = 1;
            } else {
                $normalizedAnswer[$answer->id] = ($request->answer == $answer->id) ? 1 : 0;

                if ($answer->correct && ($request->answer == $answer->id)) {
                    $correct = 1;

                    $testAnswers[] = [
                        'id' => $answer->id,
                        'text' => $answer->text,
                        'correct' => $answer->correct,
                        'gotRight' => 1,
                    ];
                } else {
                    $testAnswers[] = [
                        'id' => $answer->id,
                        'text' => $answer->text,
                        'correct' => $answer->correct,
                        'gotRight' => ($request->answer == $answer->id) ? 0 : 1,
                    ];
                }
            }
        }

        $result = ($correct == $correctAnswer) ? 1 : 0;

        // let's make sure they didn't just refresh the page
        if (! $test->questions->contains($question)) {
            $test->questions()->attach($question->id, ['result' => $result]);

            $userScore = DB::table('user_question')
                ->where('user_id', '=', $user->id)
                ->where('question_id', '=', $question->id)
                ->select('score')
                ->first();

            $score = $userScore->score;

            if ($result) {
                $score = $score + config('test.add_score');
            } else {
                $score = $score - config('test.sub_score');
                if ($score < config('test.min_score')) {
                    $score = config('test.min_score');
                }
            }

            $now = Carbon::now();
            $next = $now->addHours((config('test.hour_multiplier') * ($score ** 2)));

            $user->questions()->updateExistingPivot($question->id, ['score' => $score, 'next_at' => $next]);
        }

        // refresh the test from db so we can get an accurate question count. Otherwise
        // the question number is wrong depending on if this is the intial answer or they
        // refreshed the page.
        $test = Test::find($id);

        // Now load the answers in the order that they were shown to the user
        $aorder = explode(',', $request->order);

        $orderedAnswers = [];

        foreach ($aorder as $answerID) {
            $answer = Answer::find($answerID);

            if ($question->answers->count() > 1) {
                $orderedAnswers[] = [
                    'id' => $answer->id,
                    'text' => $answer->text,
                    'correct' => $answer->correct,
                ];
            } else {
                $orderedAnswers[$answer->id] = [
                    'id' => $answer->id,
                    'text' => $answer->text,
                    'correct' => ($answer->question_id == $question->id) ? 1 : 0,
                ];

                $normalizedAnswer[$answer->id] = ($request->answer == $answer->id) ? 1 : 0;
            }
        }

        return view('test.answer', [
            'test' => $test,
            'question' => $question,
            'answers' => $orderedAnswers,
            'normalizedAnswer' => $normalizedAnswer,
            'result' => $result,
            'multi' => $multi,
            'correct' => $correct,
        ]);
    }
}
