<?php

namespace App\Http\Controllers;

use DB;
use App\Models\User;
use App\Enums\Mastery;
use App\Models\Answer;
use App\Models\Question;
use App\Enums\Visibility;
use Illuminate\View\View;
use Illuminate\Http\Request;
use App\Models\Set as ExamSet;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\RedirectResponse;
use App\Http\Requests\ExamSetDataRequest;

class ExamSetController extends Controller
{
    public function index() {
        $user = $this->getAuthedUser();
        $exams = $user->exams;

        return view('exam.index')->with([
            'exams' => $exams,
        ]);
    }

    public function create()
    {
        $this->authorize('create', ExamSet::class);

        $visibility = Visibility::cases();

        return view('exam.create')->with([
            'visibility' => $visibility,
        ]);
    }

    // Save a new exam set
    public function store(ExamSetDataRequest $request): RedirectResponse
    {
        $this->authorize('create', ExamSet::class);

        $validatedData = $request->validated();
        $validatedData['user_id'] = auth()->user()->id;

        $exam = ExamSet::create($validatedData);

        return redirect()->route('exam.edit', $exam)->with('success', 'Exam Created!');
    }

    public function update(ExamSetDataRequest $request, ExamSet $exam):RedirectResponse
    {
        $this->authorize('update', $exam);

        $exam->update($request->validated());

        return redirect()->route('exam.edit', $exam)->with('success', 'Exam Updated');
    }

    public function view(ExamSet $exam): View
    {
        $this->authorize('view', $exam);

        $examRecord = null;

        if (Auth::check()) {
            $examRecord = DB::table('exam_records')
                            ->where('set_id', $exam->id)
                            ->where('user_id', Auth::id())
                            ->first();
        }

        $mastery = [];
        foreach (Mastery::cases() as $level) {
            $mastery[$level->value] = $level->name;
        }

        $masters = ExamSet::whereHas('records', function ($query) use ($exam) {
            $query->where('exam_records.highest_mastery', '>', Mastery::Proficient->value)->where('exam_records.set_id', $exam->id)->orderBy('exam_records.highest_mastery', 'desc');
        })->with(['records' => function ($query) {
            $query->where('exam_records.highest_mastery', '>', Mastery::Proficient->value);
        }])->get();

        return view('exam.view')->with([
            'exam' => $exam,
            'examRecord' => $examRecord,
            'mastery' => $mastery,
            'masters' => $masters,
        ]);
    }

    public function public(): View
    {
        $exams = ExamSet::where('visibility', 1)->get();

        return view('exam.public')->with([
            'exams' => $exams,
        ]);
    }

    public function edit(ExamSet $exam) {
        $this->authorize('update', $exam);

        $visibility = Visibility::cases();
        $questions = Question::where('set_id', $exam->id)->where('group_id', 0)->get();

        return view('exam.edit', [
            'exam' => $exam,
            'visibilityOptions' => $visibility,
            'questions' => $questions,
        ]);
    }

    public function add(Request $request, ExamSet $exam) {
        $this->authorize('update', $exam);

        $request->validate([
            'question' => 'required|string',
            'answers*' => 'required|string|nullable',
            'correct*' => 'sometimes',
        ]);

        $question = new Question();
        $question->text = $request->question;
        $question->set_id = $exam->id;
        $question->group_id = 0;
        $question->save();

        foreach ($request->answers as $index => $newAnswer) {
            if ($newAnswer) {
                $answer = new Answer();
    
                $answer->question_id = $question->id;
                $answer->text = $newAnswer;
                $answer->correct = (isset($request->correct[$index])) ? 1 : 0;
                $answer->save();
            }
        }

        return redirect()->route('exam.edit', $exam)->with('success', 'Exam question added');
    }

    public function question(ExamSet $exam, Question $question) {
        $this->authorize('update', $exam);

        return view('exam.question', [
            'exam' => $exam,
            'question' => $question,
        ]);
    }

    public function questionUpdate(Request $request, ExamSet $exam, Question $question) {
        $this->authorize('update', $exam);

        $request->validate([
            'question' => 'required|string',
            'answers*' => 'required|string|nullable',
            'correct*' => 'sometimes',
        ]);

        $question->text = $request->question;
        $question->save();

        foreach ($request->answers as $index => $newAnswer) {
            $answer = Answer::find($index);
            if ($answer->question_id != $question->id) {
                return back()->with('error', 'There was an error handling the answers.');
            }

            $answer->text = $newAnswer;
            $answer->correct = (isset($request->correct[$index])) ? 1 : 0;
            $answer->save();
        }

        return redirect()->route('exam.edit', $exam)->with('success', 'Question updated successfully.');
    }

    public function addAnswer(Request $request, ExamSet $exam, Question $question) {
        $this->authorize('update', $exam);

        $request->validate([
            'answer' => 'required|string',
            'correct' => 'sometimes',
        ]);

        $answer = new Answer();
    
        $answer->question_id = $question->id;
        $answer->text = $request->answer;
        $answer->correct = (isset($request->correct)) ? 1 : 0;
        $answer->save();

        return redirect()->route('exam.question', ['exam' => $exam, 'question' => $question])->with('success', 'Answer added to question!');
    }

    private function getAuthedUser() {
        $user = User::where('id', auth()->user()->id)->with('records')->first();

        return $user;
    }
}
