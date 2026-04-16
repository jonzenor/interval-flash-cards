<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Gate;
use Alert;
use App\Http\Requests\GroupQuestionRequest;
use App\Http\Requests\GroupSettingsRequest;
use App\Models\Answer;
use App\Models\Group;
use App\Models\Question;
use App\Models\Set;
use DB;
use Illuminate\Http\Request;
use Laravel\Pennant\Feature;

class GroupController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(GroupSettingsRequest $request, Set $set)
    {
        Gate::authorize('update', $set);

        $group = new Group;
        $group->set_id = $set->id;
        $group->name = $request->input('name');
        $group->question = $request->input('question');
        $group->save();

        Alert::toast('Question Group Created', 'success');

        return redirect()->route('group-view', $group->id);
    }

    public function deleteQuestion(Group $group, Question $question)
    {
        Gate::authorize('delete', $group);

        return view('group.delete')->with([
            'group' => $group,
            'question' => $question,
        ]);
    }

    public function removeQuestion(Request $request, Group $group, Question $question)
    {
        Gate::authorize('delete', $group);
        $user = $this->getAuthedUser();

        DB::table('user_question')->where('question_id', $question->id)->delete();
        $question->delete();

        return redirect()->route('group-view', $group)->with('alert', 'Group Question was successfully deleted');
    }

    /**
     * Display the specified resource.
     */
    public function show(Group $group)
    {
        Gate::authorize('update', $group);

        $questions = Question::where('group_id', $group->id)->get();

        return view('group.show')->with([
            'group' => $group,
            'questions' => $questions,
        ]);
    }

    public function storeQuestions(Request $request, Group $group)
    {
        Gate::authorize('update', $group);
        $user = $this->getAuthedUser();
        $numQuestions = $group->set->questions->count();

        $validated = $request->validate([
            'questions.*.question' => 'nullable|string|max:255',
            'questions.*.answer' => 'nullable|string|max:255',
        ]);

        // Iterate over the questions and save them to the database
        foreach ($validated['questions'] as $questionData) {
            if ($questionData['question'] != '' && $questionData['answer'] != '') {
                if ($numQuestions >= config('test.max_exam_questions')) {
                    return back()->with('warning', 'You have reached the maximum allowed questions for an exam.');
                }

                $question = Question::create([
                    'text' => $questionData['question'],
                    'set_id' => $group->set->id,
                    'group_id' => $group->id,
                    'lesson_id' => $group->lesson_id,
                ]);

                Answer::create([
                    'text' => $questionData['answer'],
                    'question_id' => $question->id,
                    'correct' => 1,
                ]);

                $numQuestions += 1;
            }
        }

        Alert::toast('Question Added', 'success');

        return redirect()->route('group-view', $group->id);
    }

    public function editQuestion(Group $group, Question $question)
    {
        Gate::authorize('update', $group);

        return view('group.edit')->with([
            'group' => $group,
            'question' => $question,
        ]);
    }

    public function updateQuestion(GroupQuestionRequest $request, Group $group, Question $question)
    {
        Gate::authorize('update', $group);

        $answer = Answer::where('question_id', $question->id)->first();

        if (! $answer || ! $question) {
            abort(404, 'Resource not found');
        }

        $question->update([
            'text' => $request->question,
        ]);

        $answer->update([
            'text' => $request->answer,
        ]);

        return redirect()->route('group-view', $group);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Group $group)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(GroupSettingsRequest $request, Group $group)
    {
        Gate::authorize('update', $group);

        $validated = $request->validated();
        $group->update($validated);

        // Update all questions in this group to have the same lesson
        $group->questions()->update(['lesson_id' => $validated['lesson_id'] ?? null]);

        Alert::toast('Group Settings Updated', 'success');

        return redirect()->route('group-view', $group);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Group $group)
    {
        //
    }
}
