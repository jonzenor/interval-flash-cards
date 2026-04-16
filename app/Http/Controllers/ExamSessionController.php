<?php

namespace App\Http\Controllers;

use DB;
use Carbon\Carbon;
use App\Models\Set;
use App\Models\User;
use App\Enums\Mastery;
use App\Models\Answer;
use App\Models\Question;
use Illuminate\Http\Request;
use Laravel\Pennant\Feature;
use App\Actions\ExamRecords\CreateUserExamRecord;
use App\Actions\ExamSession\AwardCreditsForMastery;
use App\Actions\ExamSession\SelectQuestionsForExam;
use App\Actions\ExamSession\CalculateQuestionTimeout;
use App\Http\Requests\ExamSessionConfigurationRequest;
use App\Actions\ExamSession\UpdateUsersHighestMastery;
use App\Actions\ExamSession\AddExamQuestionsToUserRecord;
use App\Actions\ExamSession\CalculateUsersMaxAvailableQuestions;
use App\Actions\User\RecordCreditHistory;

class ExamSessionController extends Controller
{
    public function start(Set $examSet)
    {
        $this->authorize('view', $examSet);

        $record = DB::table('exam_records')->where('user_id', auth()->user()->id)->where('set_id', $examSet->id)->first();

        if (! $record) {
            if ($examSet->user_id == auth()->user()->id) {
                return redirect()->route('exam-session.enroll', $examSet);
            } else {
                return redirect()->route('exam-session.register', $examSet);
            }
        }

        $session = $this->getInProgressSession($examSet);

        if (! $session) {
            return redirect()->route('exam-session.configure', $examSet);
        } else {
            return redirect()->route('exam-session.test', $examSet);
        }
    }

    public function register(Set $examSet)
    {
        $this->authorize('view', $examSet);

        $record = DB::table('exam_records')->where('user_id', auth()->user()->id)->where('set_id', $examSet->id)->first();

        if ($record) {
            return redirect()->route('exam-session.configure', $examSet);
        }

        return view('exam-session.register')->with([
            'exam' => $examSet,
        ]);
    }

    public function enroll(Set $examSet)
    {
        $this->authorize('view', $examSet);

        $record = DB::table('exam_records')->where('user_id', auth()->user()->id)->where('set_id', $examSet->id)->first();
        $user = $this->getAuthedUser();

        if ($record) {
            return redirect()->route('exam-session.configure', $examSet)->with('info', 'You are already enrolled in this account.');
        }

        if (Feature::active('mage-upgrade')) {
            if (($examSet->user_id != $user->id) && ($user->credit->study < 1)) {
                return back()->with('warning', 'Insufficient Study Credits. Please earn more Study Credits or upgrade to Mage status to start another exam.');
            }
        }

        CreateUserExamRecord::execute($user, $examSet);

        return redirect()->route('exam-session.start', $examSet);
    }

    public function configure(Set $examSet)
    {
        $this->authorize('view', $examSet);

        $user = $this->getAuthedUser();
        AddExamQuestionsToUserRecord::execute($user, $examSet);
        
        $lessonId = request('lesson_id');
        $maxQuestions = CalculateUsersMaxAvailableQuestions::execute($user, $examSet, $lessonId);
        $message = '';

        if ($maxQuestions == 0) {
            $record = DB::table('exam_records')->where('user_id', $user->id)->where('set_id', $examSet->id)->first();
            if ($record->available_at) {
                $available = Carbon::parse($record->available_at);

                $message = "You will have have 10 more questions available: " . $available->diffForHumans();
            }

            return redirect()->route('profile.exams')->with('warning', 'You do not have any available questions to take yet. ' . $message);
        }

        return view('exam-session.configure')->with([
            'examSet' => $examSet,
            'maxQuestions' => $maxQuestions,
        ]);
    }

    public function store(ExamSessionConfigurationRequest $request, Set $examSet)
    {
        $this->authorize('view', $examSet);

        $session = $this->getInProgressSession($examSet);
        if ($session) {
            return redirect()->route('exam-session.test', $examSet);
        }

        $user = $this->getAuthedUser();
        $lessonId = $request->input('lesson_id');
        $maxQuestions = CalculateUsersMaxAvailableQuestions::execute($user, $examSet, $lessonId);

        $request->validate([
            'question_count' => 'max:'.$maxQuestions,
            'lesson_id' => 'nullable|integer|exists:lessons,id',
        ]);

        if ($request->question_count > $maxQuestions) {
            return back()->with('error', 'Requested question count exceeds maximum available of '.$maxQuestions.' Questions.');
        }

        $questions = SelectQuestionsForExam::execute($user, $examSet, $request->question_count, $lessonId);

        $questionArray = [];
        $questionArray = $questions->pluck('question_id');

        // Store lesson_id if provided
        $sessionData = [
            'question_count' => $request->question_count,
            'questions_array' => json_encode($questionArray),
            'current_question' => 0,
        ];
        
        if ($lessonId) {
            $sessionData['lesson_id'] = $lessonId;
        }

        // Create a new instance of this test
        $examSet->sessions()->attach($user->id, $sessionData);

        return redirect()->route('exam-session.test', $examSet->id);
    }

    public function test(Set $examSet)
    {
        $session = $this->getInProgressOrLatestSession($examSet);

        // If the last question was answered, complete the session
        if (($session->date_completed) || ($session->current_question == $session->question_count)) {
            return redirect()->route('exam-session.summary', $examSet);
        }

        $arrayData = json_decode($session->questions_array);

        if (! array_key_exists($session->current_question, $arrayData)) {
            return redirect()->route('exam-session.summary', $examSet);
        }

        $question = Question::with(['answers', 'group'])->find($arrayData[$session->current_question]);

        if (! $question) {
            // Something happened, the question was probably deleted

            // Remove the offending question from the session
            unset($arrayData[$session->current_question]);

            // Reindex the array
            $arrayData = array_values($arrayData);

            DB::table('exam_sessions')->where('id', $session->id)->update([
                'questions_array' => json_encode($arrayData),
                'question_count' => $session->question_count - 1,
            ]);

            return redirect()->route('exam-session.test', $examSet)->with('warning', 'Skipping question that was deleted from the Exam');
        }

        // Generate the list of answers for this question
        $answers = null;
        $single = null;

        if ($question->answers->count() > 1) {
            $answers = $question->answers->shuffle();
        } else {
            if ($question->group_id) {
                // There was only one answer, so let's grab some more random ones from the test
                $single = true;
                $answers = $question->answers;

                // Track the text value of the answers we have selected so that we do not choose other answers with the exact same text
                $answersText = collect($question->answers[0]->text);

                $pool = Question::where('set_id', '=', $question->set_id)->where('group_id', '=', $question->group_id)->where('id', '!=', $question->id)->get();

                $pool = $pool->shuffle();

                $count = 1;

                foreach ($pool as $q) {
                    if (! $answersText->contains($q->answers[0]->text)) {
                        $answersText->push($q->answers[0]->text);
                        $answers->push($q->answers->first());
                        $count = $count + 1;
                    }

                    if ($count == config('test.target_answers')) {
                        break;
                    }
                }

                $answers = $answers->shuffle();
            } else {
                $answers = $question->answers;
            }
        }

        $correct = 0;
        $order = [];

        foreach ($answers as $answer) {
            $order[] = $answer->id;

            if (! $single && $answer->correct) {
                $correct = $correct + 1;
            }
        }

        $multi = ($correct > 1) ? 1 : 0;

        return view('exam-session.test')->with([
            'question' => $question,
            'answers' => $answers,
            'multi' => $multi,
            'examSet' => $examSet,
            'order' => json_encode($order),
            'session' => $session,
        ]);
    }

    public function answerRedirect(Set $examSet)
    {
        return redirect()->route('exam-session.test', $examSet);
    }

    public function answer(Request $request, Set $examSet)
    {
        $request->validate([
            'question' => 'required|integer',
            'order' => 'required|string',
        ]);

        $session = $this->getInProgressOrLatestSession($examSet);
        $question = Question::find($request->question);

        $recordAnswer = true;
        $questionArray = json_decode($session->questions_array);
        if (($session->current_question == $session->question_count) ||
            ($questionArray[$session->current_question] != $question->id)
        ) {
            // We have already recorded the results of this question, so let's not record it again.
            $recordAnswer = false;
        }

        $correct = 0;
        $correctAnswersCount = $this->determineCorrectAnswerCount($question);
        $multi = ($correctAnswersCount > 1) ? 1 : 0; // Is this a question with multiple correct answers?L
        $normalizedAnswer = [];
        $testAnswers = [];

        foreach ($question->answers as $answer) {
            if ($multi) {
                $normalizedAnswer[$answer->id] = (array_key_exists($answer->id, $request->answer)) ? 1 : 0;
                $gotRight = 0;

                if ($answer->correct && ($normalizedAnswer[$answer->id] == 1)) {
                    $correct += 1;
                    $gotRight = 1;
                } elseif (!$answer->correct && ($normalizedAnswer[$answer->id] == 1)) {
                    $correct -= 1;
                    $gotRight = 0;
                }

                $testAnswers[] = [
                    'id' => $answer->id,
                    'text' => $answer->text,
                    'correct' => $answer->correct,
                    'gotRight' => $gotRight,
                ];
            } elseif ($question->answers->count() == 1) {
                $correct = ($request->answer == $answer->id) ? 1 : 0;

                // If this is a single answer question, then we don't care if the user marked it as correct or not
                // We know that there is only one answer so it must be correct
                // Later this is going to be a "fill in the blank" type question
                $correctAnswersCount = 1;
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

        $result = ($correct == $correctAnswersCount) ? 1 : 0;

        $userQuestion = DB::table('user_question')
            ->where('user_id', auth()->user()->id)
            ->where('question_id', $question->id)
            ->first();
        $previousScore = null;
        $incorrectCount = ($userQuestion->incorrect_count) ?? 0;
        $correctCount = ($userQuestion->correct_count) ?? 0;
        $lastIncorrect = $userQuestion->last_incorrect_at;

        if ($recordAnswer) {
            $updatedScore = 0;
            $previousScore = $userQuestion->score;
            if ($result == 1) {
                if ($userQuestion->score == 0) {
                    $updatedScore = config('test.min_score') + config('test.add_score');
                } else {
                    $updatedScore = $userQuestion->score + config('test.add_score');
                }

                $correctCount = $correctCount + 1;
                $incorrectCount = 0;

                // If the user has gotten this question correct 2 times in a row,
                // then reset the last incorrect flag so it stops showing up in the
                // practice for recent incorrect answers
                if ($correctCount >= 2) {
                    $lastIncorrect = null;
                }
            } else {
                $updatedScore = $userQuestion->score - config('test.sub_score');

                $incorrectCount = $incorrectCount + 1;
                $correctCount = 0;
                $lastIncorrect = Carbon::now();
            }

            if ($updatedScore < config('test.min_score')) {
                $updatedScore = config('test.min_score');
            }

            $nextAt = CalculateQuestionTimeout::execute($updatedScore, $result);

            DB::table('user_question')
                ->where('user_id', auth()->user()->id)
                ->where('question_id', $question->id)
                ->update([
                    'score' => $updatedScore,
                    'next_at' => $nextAt,
                    'incorrect_count' => $incorrectCount,
                    'correct_count' => $correctCount,
                    'last_incorrect_at' => $lastIncorrect,
                ]);

            // Update mastery for leveled up questions
            $updateMastery = $this->calculateUpdatedMastery($userQuestion->score, $updatedScore, $session);

            $updateSession = [
                'current_question' => $session->current_question + 1,
                'correct_answers' => ($result == 1) ? $session->correct_answers + 1 : $session->correct_answers,
                'incorrect_answers' => ($result == 0) ? $session->incorrect_answers + 1 : $session->incorrect_answers,
            ];

            if (count($updateMastery)) {
                $updateSession = array_merge($updateSession, $updateMastery);
            }

            DB::table('exam_sessions')->where('id', $session->id)->update($updateSession);
        }

        // Now load the answers in the order that they were shown to the user
        $aorder = json_decode($request->order);

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

        // Get an updated copy of the session
        $session = $this->getSessionById($session->id);
        $userQuestion = DB::table('user_question')
            ->where('user_id', auth()->user()->id)
            ->where('question_id', $question->id)
            ->first();

        return view('exam-session.answer', [
            'question' => $question,
            'answers' => $orderedAnswers,
            'normalizedAnswer' => $normalizedAnswer,
            'result' => $result,
            'multi' => $multi,
            'correct' => $correct,
            'examSet' => $examSet,
            'session' => $session,
            'userQuestionStats' => $userQuestion,
            'previousScore' => $previousScore,
        ]);
    }

    public function toggleReviewFlag(Set $examSet, Question $question)
    {
        $userQuestion = DB::table('user_question')
            ->where('question_id', $question->id)
            ->where('user_id', auth()->user()->id)
            ->first();

        DB::table('user_question')->where('user_id', auth()->user()->id)->where('question_id', $question->id)->update([
            'reviewFlagged' => ($userQuestion->reviewFlagged) ? 0 : 1,
        ]);

        $userQuestion = DB::table('user_question')
            ->where('question_id', $question->id)
            ->where('user_id', auth()->user()->id)
            ->first();

        return view('exam-session.flagged')->with([
            'exam' => $examSet,
            'userQuestion' => $userQuestion,
            'question' => $question,
        ]);
    }

    public function summary(Set $examSet)
    {
        $session = $this->getInProgressOrLatestSession($examSet);

        // Make sure the exam has been completed
        if (($session) && (($session->current_question) != ($session->question_count))) {
            return redirect()->route('exam-session.test', $examSet);
        }

        if ($session && !$session->date_completed) {
            $dateNow = Carbon::now();
            $updateSession['date_completed'] = $dateNow;
            $updateSession['grade'] = round(($session->correct_answers / $session->question_count) * 100);
            DB::table('exam_sessions')->where('id', $session->id)->update($updateSession);

            $session = $this->getSessionById($session->id);

            // Recalculate Exam Record Stats
            $this->calculateExamRecordStats($examSet);
        }

        $examRecord = DB::table('exam_records')
            ->where('user_id', auth()->user()->id)
            ->where('set_id', $examSet->id)
            ->first();
        $mastery = ($session) ? Mastery::from($examRecord->highest_mastery)->name : Mastery::Unskilled->name;

        return view('exam-session.summary')->with([
            'examSet' => $examSet,
            'session' => $session,
            'examRecord' => $examRecord,
            'mastery' => $mastery,
        ]);
    }

    /**
 * ========== Public Helper Functions ==========
*/
    public function calculateExamRecordStats(Set $examSet, $user = null)
    {
        if (! $user) {
            $user = User::find(auth()->user()->id);
        }

        $record = DB::table('exam_records')->where('user_id', $user->id)->where('set_id', $examSet->id)->first();

        $recentSessions = DB::table('exam_sessions')
            ->where('set_id', $examSet->id)
            ->where('user_id', $user->id)
            ->orderBy('date_completed', 'desc')
            ->limit(config('count_tests_for_average_score'))
            ->get();

        $averageCount = 0;
        $averageTotal = 0;
        $dateNow = null;

        foreach ($recentSessions as $recentSession) {
            if (! $dateNow) {
                $dateNow = $recentSession->date_completed;
            }

            $averageCount++;
            $averageTotal += $recentSession->grade;
        }

        $originalMastery = $record->highest_mastery;
        $highestMastery = UpdateUsersHighestMastery::execute($user, $examSet, $originalMastery);

        if (Feature::active('mage-upgrade')) {
            AwardCreditsForMastery::execute($user, $examSet, $originalMastery, $highestMastery);
        }

        $nextQuestions = DB::table('user_question')
            ->where('user_id', $user->id)
            ->where('set_id', $examSet->id)
            ->orderBy('next_at', 'asc')
            ->limit(10)
            ->get();
        $tenthQuestion = $nextQuestions->last();

        DB::table('exam_records')->where('user_id', $user->id)->where('set_id', $examSet->id)->update([
            'times_taken' => $record->times_taken + 1,
            'recent_average' => round($averageTotal / $averageCount),
            'last_completed' => $dateNow,
            'highest_mastery' => $highestMastery,
            'available_at' => $tenthQuestion->next_at,
        ]);
    }

    /**
 * ========== HELPER FUNCTIONS ==========
*/
    private function getInProgressOrLatestSession($examSet)
    {
        $session = DB::table('exam_sessions')
            ->where('user_id', auth()->user()->id)
            ->where('set_id', $examSet->id)
            ->orderByRaw('date_completed IS NULL DESC')
            ->orderByDesc('date_completed')
            ->first();

        return $session;
    }

    private function getInProgressSession($examSet)
    {
        $session = DB::table('exam_sessions')
            ->where('user_id', auth()->user()->id)
            ->where('set_id', $examSet->id)
            ->where('date_completed', null)
            ->first();

        return $session;
    }

    private function getSessionById($sessionId)
    {
        $session = DB::table('exam_sessions')->where('id', $sessionId)->first();

        return $session;
    }

    private function determineCorrectAnswerCount($question)
    {
        $correctAnswersCount = 0;

        foreach ($question->answers as $answer) {
            if ($answer->correct) {
                $correctAnswersCount = $correctAnswersCount + 1;
            }
        }

        return $correctAnswersCount;
    }

    public function calculateUpdatedMastery($originalScore, $updatedScore, $session)
    {
        $updateMastery = [];

        // See if the current score equals a threshold
        if ($this->justAttainedMasteryLevel($originalScore, $updatedScore, 'apprentice') ||
            $this->justAttainedMasteryLevelPlusOne($originalScore, $updatedScore, 'apprentice')
        ) {
            $updateMastery['mastery_apprentice_change'] = $session->mastery_apprentice_change + 1;
        } elseif ($this->justLostMasteryLevel($originalScore, $updatedScore, 'apprentice')) {
            $updateMastery['mastery_apprentice_change'] = $session->mastery_apprentice_change - 1;
        }

        if ($this->justAttainedMasteryLevel($originalScore, $updatedScore, 'familiar') ||
            ($originalScore == 0 && $this->scoreIsMasteryLevel($updatedScore, 'familiar'))
        ) {
            $updateMastery['mastery_familiar_change'] = $session->mastery_familiar_change + 1;
        } elseif ($this->justLostMasteryLevel($originalScore, $updatedScore, 'familiar')) {
            $updateMastery['mastery_familiar_change'] = $session->mastery_familiar_change - 1;
        }

        if ($this->justAttainedMasteryLevel($originalScore, $updatedScore, 'proficient')) {
            $updateMastery['mastery_proficient_change'] = $session->mastery_proficient_change + 1;
        } elseif ($this->justLostMasteryLevel($originalScore, $updatedScore, 'proficient')) {
            $updateMastery['mastery_proficient_change'] = $session->mastery_proficient_change - 1;
        }

        if ($this->justAttainedMasteryLevel($originalScore, $updatedScore, 'mastered')) {
            $updateMastery['mastery_mastered_change'] = $session->mastery_mastered_change + 1;
        } elseif ($this->justLostMasteryLevel($originalScore, $updatedScore, 'mastered')) {
            $updateMastery['mastery_mastered_change'] = $session->mastery_mastered_change - 1;
        }

        return $updateMastery;
    }



    private function justLostMasteryLevel($originalScore, $updatedScore, $mastery)
    {
        if ($this->scoreIsMasteryMinusIncorrect($updatedScore, $mastery) &&
            $this->scoreIsMasteryLevel($originalScore, $mastery)
        ) {
            return true;
        }

        return false;
    }

    public function justAttainedMasteryLevel($originalScore, $updatedScore, $mastery)
    {
        if ($this->scoreIsMasteryLevel($updatedScore, $mastery) &&
            $this->scoreIsMasteryMinusCorrect($originalScore, $mastery)
            ) {
                return true;
        }

        return false;
    }

    // This is a one off calculation because the user can sometimes get a bonus point for getting the question
    // correct the first time, so we have to account for that as well
    private function justAttainedMasteryLevelPlusOne($originalScore, $updatedScore, $mastery)
    {
        if ($this->scoreIsMasteryPlusCorrect($updatedScore, $mastery) &&
            $this->scoreIsMasteryMinusCorrect($originalScore, $mastery)
        ) {
            return true;
        }

        return false;
    }

    public function scoreIsMasteryLevel($score, $mastery)
    {
        return $score == config('test.grade_' . $mastery);
    }

    private function scoreIsMasteryMinusCorrect($score, $mastery)
    {
        return $score == (config('test.grade_' . $mastery) - config('test.add_score'));
    }

    private function scoreIsMasteryMinusIncorrect($score, $mastery)
    {
        return $score == (config('test.grade_' . $mastery) - config('test.sub_score'));
    }

    private function scoreIsLessThanMastery($score, $mastery)
    {
        return $score <= config('test.grade_' . $mastery);
    }

    private function scoreIsMasteryPlusCorrect($score, $mastery)
    {
        return $score == (config('test.grade_' . $mastery) + config('test.add_score'));
    }
}
