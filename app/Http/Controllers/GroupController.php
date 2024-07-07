<?php

namespace App\Http\Controllers;

use Alert;
use App\Http\Requests\CreateGroupRequest;
use App\Http\Requests\GroupQuestionRequest;
use App\Models\Answer;
use App\Models\Group;
use App\Models\Question;
use App\Models\Set;
use Illuminate\Http\Request;

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
     * Show the form for creating a new resource.
     */
    public function create(Set $set)
    {
        $this->authorize('update', $set);

        return view('group.create')->with([
            'set' => $set,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CreateGroupRequest $request, Set $set)
    {
        $this->authorize('update', $set);

        $group = new Group();
        $group->name = $request->input('name');
        $group->set_id = $set->id;
        $group->save();

        Alert::toast('Question Group Created', 'success');

        return redirect()->route('manage-questions', $set->id);
    }

    /**
     * Display the specified resource.
     */
    public function show(Group $group)
    {
        $this->authorize('update', $group);

        $questions = Question::where('group_id', $group->id)->get();

        return view('group.show')->with([
            'group' => $group,
            'questions' => $questions,
        ]);
    }

    public function storeQuestions(Request $request, Group $group)
    {
        $this->authorize('update', $group);

        $validated = $request->validate([
            'questions.*.question' => 'nullable|string|max:255',
            'questions.*.answer' => 'nullable|string|max:255',
        ]);

        // Iterate over the questions and save them to the database
        foreach ($validated['questions'] as $questionData) {
            if ($questionData['question'] != '' && $questionData['answer'] != '') {
                $question = Question::create([
                    'text' => $questionData['question'],
                    'set_id' => $group->set->id,
                    'group_id' => $group->id,
                ]);

                Answer::create([
                    'text' => $questionData['answer'],
                    'question_id' => $question->id,
                    'correct' => 1,
                ]);
            }
        }

        Alert::toast('Question Added', 'success');

        return redirect()->route('group-view', $group->id);
    }

    public function editQuestion(Group $group, Question $question)
    {
        $this->authorize('update', $group);

        return view('group.edit')->with([
            'group' => $group,
            'question' => $question,
        ]);
    }

    public function updateQuestion(GroupQuestionRequest $request, Group $group, Question $question)
    {
        $this->authorize('update', $group);

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
    public function update(Request $request, Group $group)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Group $group)
    {
        //
    }
}
