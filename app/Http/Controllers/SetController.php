<?php

namespace App\Http\Controllers;

use Alert;
use App\Models\Set;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SetController extends Controller
{
    // Save a new exam set
    public function store(Request $request): RedirectResponse
    {
        if (! auth()->user()->hasRole('admin')) {
            Alert::toast('Permission Denied', 'warning');

            return redirect()->route('home');
        }

        $this->validate($request, [
            'name' => 'required|string',
            'description' => 'required|string',
            'visibility' => 'required|integer',
        ]);

        $set = new Set();

        $set->name = $request->name;
        $set->description = $request->description;
        $set->user_id = auth()->user()->id;
        $set->visibility = $request->visibility;
        $set->save();

        Alert::toast('Exam Added', 'success');

        return redirect()->route('manage-questions', $set->id);
    }

    public function update(Request $request, $id): RedirectResponse
    {
        if (! auth()->user()->hasRole('admin')) {
            Alert::toast('Permission Denied', 'warning');

            return redirect()->route('home');
        }

        $this->validate($request, [
            'name' => 'required|string',
            'description' => 'required|string',
            'visibility' => 'required|integer',
        ]);

        $set = Set::findOrFail($id);

        $set->name = $request->name;
        $set->description = $request->description;
        $set->visibility = $request->visibility;
        $set->save();

        Alert::toast('Exam Updated', 'success');

        return redirect()->route('manage-questions', $set->id);
    }
}
