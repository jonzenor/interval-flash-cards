<?php

namespace App\Http\Controllers;

use Alert;
use App\Enums\Visibility;
use App\Http\Requests\AnswerRequest;
use App\Http\Requests\QuestionRequest;
use App\Models\Answer;
use App\Models\Question;
use App\Models\Set;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QuestionController extends Controller
{
    public function exams()
    {
        if (! auth()->user()->hasRole('admin')) {
            Alert::toast('Permission Denied', 'warning');

            return redirect()->route('home');
        }

        $sets = Set::Where('user_id', '=', auth()->user()->id)->get();

        $otherPrivateExams = null;
        if (auth()->user()->hasRole('admin')) {
            $otherPrivateExams = Set::Where('user_id', '!=', auth()->user()->id)->where('visibility', '=', Visibility::isPrivate)->get();
        }

        $visibility = Visibility::cases();

        return view('manage.sets', [
            'sets' => $sets,
            'privateExams' => $otherPrivateExams,
            'visibility' => $visibility,
        ]);
    }

    public function index($id)
    {
        if (! auth()->user()->hasRole('admin')) {
            Alert::toast('Permission Denied', 'warning');

            return redirect()->route('home');
        }

        $set = Set::find($id);
        if (! $set) {
            Alert::toast('Page Not Found', 'error');

            return redirect()->route('home');
        }

        $visibility = Visibility::cases();
        $questions = Question::where('set_id', $set->id)->where('group_id', 0)->get();

        return view('manage.questions', [
            'set' => $set,
            'visibilityOptions' => $visibility,
            'questions' => $questions,
        ]);
    }

    // Add an question for an exam
    public function add($id)
    {
        if (! auth()->user()->hasRole('admin')) {
            Alert::toast('Permission Denied', 'warning');

            return redirect()->route('home');
        }

        $set = Set::find($id);
        if (! $set) {
            Alert::toast('Page Not Found', 'error');

            return redirect()->route('home');
        }

        return view('manage.addq', [
            'set' => $set,
        ]);
    }

    // Save a question for an exam
    public function store(QuestionRequest $request, $id): RedirectResponse
    {
        if (! auth()->user()->hasRole('admin')) {
            Alert::toast('Permission Denied', 'warning');

            return redirect()->route('home');
        }

        $set = Set::find($id);
        if (! $set) {
            Alert::toast('Page Not Found', 'error');

            return redirect()->route('home');
        }

        $validatedData = $request->validated();
        $validatedData['set_id'] = $set->id;

        $question = Question::create($validatedData);

        Alert::toast('Question Added', 'success');

        return redirect()->route('manage-answers', $question->id);
    }

    public function edit($id)
    {
        if (! auth()->user()->hasRole('admin')) {
            Alert::toast('Permission Denied', 'warning');

            return redirect()->route('home');
        }

        $question = Question::find($id);

        return view('manage.editq', [
            'question' => $question,
        ]);
    }

    public function update(QuestionRequest $request, $id)
    {
        if (! auth()->user()->hasRole('admin')) {
            Alert::toast('Permission Denied', 'warning');

            return redirect()->route('home');
        }

        $question = Question::find($id);
        $question->update($request->validated());

        Alert::toast('Question Updated', 'success');

        return redirect()->route('manage-answers', $question->id);
    }

    // List all answers for a question
    public function answers($id)
    {
        if (! auth()->user()->hasRole('admin')) {
            Alert::toast('Permission Denied', 'warning');

            return redirect()->route('home');
        }

        $question = Question::find($id);
        if (! $question) {
            Alert::toast('Page Not Found', 'error');

            return redirect()->route('home');
        }

        return view('manage.answers', [
            'question' => $question,
        ]);
    }

    // Save an answer to a question
    public function storeAnswer(AnswerRequest $request, $id): RedirectResponse
    {
        if (! auth()->user()->hasRole('admin')) {
            Alert::toast('Permission Denied', 'warning');

            return redirect()->route('home');
        }

        $question = Question::find($id);
        if (! $question) {
            Alert::toast('Page Not Found', 'error');

            return redirect()->route('home');
        }

        $validatedData = $request->validated();
        $validatedData['question_id'] = $question->id;
        $answer = Answer::create($validatedData);

        Alert::toast('Answer Added', 'success');

        return redirect()->route('manage-answers', $question->id);
    }

    public function editAnswer($id): View
    {
        $answer = Answer::find($id);

        $question = Question::find($answer->question->id);

        return view('manage.editanswer', [
            'answer' => $answer,
            'question' => $question,
        ]);
    }

    public function updateAnswer(AnswerRequest $request, $id): RedirectResponse
    {
        $answer = Answer::find($id);

        $validatedData = $request->validated();
        $answer->update($validatedData);

        Alert::toast('Answer Updated', 'success');

        return redirect()->route('manage-answers', $answer->question->id);
    }

    public function deleteAnswer($id): View
    {
        $answer = Answer::find($id);

        $question = Question::find($answer->question->id);

        return view('manage.deleteanswer', [
            'answer' => $answer,
            'question' => $question,
        ]);
    }

    public function deleteAnswerConfirm(Request $request, $id): RedirectResponse
    {
        $answer = Answer::find($id);

        $question = Question::find($answer->question->id);

        $this->validate($request, [
            'confirm' => 'string',
        ]);

        if ($request->confirm != 'true') {
            Alert::toast('Something Went Wrong', 'error');

            return redirect()->route('manage-answers', $question->id);
        }

        $answer->delete();

        Alert::toast('Answer deleted', 'success');

        return redirect()->route('manage-answers', $question->id);
    }
}
