<?php

namespace App\Http\Controllers;

use DB;
use App\Enums\Mastery;
use App\Models\Answer;
use App\Models\Question;
use App\Models\ExamPractice;
use Illuminate\Http\Request;
use Laravel\Pennant\Feature;
use App\Helpers\ExamFunctions;
use App\Models\Set as ExamSet;

class PracticeController extends Controller
{
    public function start(ExamSet $exam)
    {
        if (! Feature::active('flash-cards')) {
            abort(404, 'Not found');
        }

        $this->authorize('view', $exam);

        $session = $this->getPracticeSession($exam);

        if ($session) {
            return redirect()->route('practice.review', $exam);
        }

        ExamFunctions::initiate_questions_for_authed_user($exam);

        return view('practice.start')->with([
            'exam' => $exam,
        ]);
    }

    public function begin(Request $request, ExamSet $exam)
    {
        if (! Feature::active('flash-cards')) {
            abort(404, 'Not found');
        }

        $this->authorize('view', $exam);
        $this->authorize('create', ExamPractice::class);

        $request->validate([
            'filter' => 'required|string|min:1|max:32',
        ]);

        $questionsArray = [];

        switch ($request->filter) {
            case 'all':
                $questionsArray = $exam->questions->shuffle()->pluck('id');
                break;

            case 'flagged':
                $questionsArray = DB::table('user_question')
                    ->where('set_id', $exam->id)
                    ->where('user_id', auth()->user()->id)
                    ->where('reviewFlagged', 1)
                    ->pluck('question_id')
                    ->shuffle();
                break;

            case 'weak':
                $questionsArray = DB::table('user_question')
                    ->where('set_id', $exam->id)
                    ->where('user_id', auth()->user()->id)
                    ->where('score', '<=', Mastery::Familiar)
                    ->pluck('question_id')
                    ->shuffle();
                break;

            case 'strong':
                $questionsArray = DB::table('user_question')
                    ->where('set_id', $exam->id)
                    ->where('user_id', auth()->user()->id)
                    ->where('score', '>=', Mastery::Proficient)
                    ->pluck('question_id')
                    ->shuffle();
                break;

            case 'recentIncorrect':
                $questionsArray = DB::table('user_question')
                    ->where('set_id', $exam->id)
                    ->where('user_id', auth()->id())
                    ->where('last_incorrect_at', '!=', null)
                    ->orderBy('last_incorrect_at', 'DESC')
                    ->limit(15)
                    ->pluck('question_id')
                    ->shuffle();
        }

        if ($questionsArray->count() == 0) {
            return back()->with('warning', 'There were no questions available for review with the options selected');
        }

        $practice = ExamPractice::create([
            'user_id' => auth()->user()->id,
            'exam_id' => $exam->id,
            'question_count' => $questionsArray->count(),
            'question_index' => 0,
            'question_order' => json_encode($questionsArray),
        ]);

        return redirect()->route('practice.review', $exam);
    }

    public function review(ExamSet $exam)
    {
        $this->authorize('view', $exam);

        $session = $this->getPracticeSession($exam);

        if (! $session) {
            return redirect()->route('practice.start', $exam);
        }

        $this->authorize('view', $session);

        $questionArray = json_decode($session->question_order);

        if (! array_key_exists($session->question_index, $questionArray)) {
            return redirect()->route('practice.done', $exam);
        }

        $question = Question::find($questionArray[$session->question_index]);

        if (! $question) {
            // Something happened, the question was probably deleted
            $session->question_count -= 1;

            // Remove the offending question from the session
            unset($questionArray[$session->question_index]);

            // Reindex the array
            $questionArray = array_values($questionArray);
            $session->question_order = json_encode($questionArray);
            $session->save();

            return redirect()->route('practice.review', $exam)->with('warning', 'Skipping question that was deleted from the Exam');
        }

        $answers = Answer::where('question_id', $question->id)->where('correct', 1)->get();

        $userQuestion = DB::table('user_question')->where('question_id', $question->id)->where('user_id', auth()->user()->id)->first();

        return view('practice.review')->with([
            'exam' => $exam,
            'question' => $question,
            'answers' => $answers,
            'session' => $session,
            'userQuestion' => $userQuestion,
        ]);
    }

    public function done(ExamSet $exam)
    {
        $this->authorize('view', $exam);

        $session = $this->getPracticeSession($exam);

        if (! $session) {
            return redirect()->route('practice.start', $exam);
        }
        $this->authorize('delete', $session);

        $session->delete();

        return view('practice.done')->with([
            'exam' => $exam,
        ]);
    }

    public function next(ExamSet $exam)
    {
        $this->authorize('view', $exam);

        $session = $this->getPracticeSession($exam);
        $this->authorize('view', $session);

        if ($session->question_index >= $session->question_count - 1) {
            return redirect()->route('practice.done', $exam);
        }

        $session->update([
            'question_index' => $session->question_index + 1,
        ]);

        return redirect()->route('practice.review', $exam);
    }

    public function previous(ExamSet $exam)
    {
        $this->authorize('view', $exam);

        $session = $this->getPracticeSession($exam);
        $this->authorize('view', $session);

        if ($session->question_index == 0) {
            return redirect()->route('practice.review', $exam);
        }

        $session->update([
            'question_index' => $session->question_index - 1,
        ]);

        return redirect()->route('practice.review', $exam);
    }

    public function toggleReviewFlag(ExamSet $exam, Question $question)
    {
        $userQuestion = DB::table('user_question')->where('question_id', $question->id)->where('user_id', auth()->user()->id)->first();

        DB::table('user_question')->where('user_id', auth()->user()->id)->where('question_id', $question->id)->update([
            'reviewFlagged' => ($userQuestion->reviewFlagged) ? 0 : 1,
        ]);

        return redirect()->route('practice.review', $question->set_id);
    }

    /**
 * ========== Helper Functions ==========
*/
    private function getPracticeSession(ExamSet $exam)
    {
        $session = ExamPractice::where('exam_id', $exam->id)->where('user_id', auth()->user()->id)->first();

        return $session;
    }
}
