<?php

namespace App\Http\Controllers;

use App\Models\Question;
use App\Models\Set;
use App\Models\Test;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     */
    public function index(): View
    {
        $user_id = Auth::id();

        $user = User::find($user_id);

        $allSets = Set::all();
        $sets = [];

        foreach ($allSets as $set) {
            $tests = Test::where('user_id', '=', $user->id)->where('set_id', '=', $set->id)->orderBy('end_at', 'desc')->limit(config('test.count_tests_for_average_score'))->get();

            if (! $tests->count()) {
                continue;
            }

            $average = 0;
            $last_taken = null;

            foreach ($tests as $test) {
                $average = $average + $test->result;

                // Get the timestamp of the last time this was taken. Since we're ordering in desc order,
                // the first time this loop runs should be the latest time
                if (! $last_taken) {
                    $last_taken = $test->end_at;
                }
            }

            $total_questions = Question::where('set_id', '=', $set->id)->count();
            $total_mastery = DB::table('user_question')
                ->where('user_id', '=', $user->id)
                ->where('set_id', '=', $set->id)
                ->where('score', '>=', config('test.grade_mastery'))
                ->count();

            $total_proficient = DB::table('user_question')
                ->where('user_id', '=', $user->id)
                ->where('set_id', '=', $set->id)
                ->where('score', '>=', config('test.grade_proficient'))
                ->count();

            $total_familiar = DB::table('user_question')
                ->where('user_id', '=', $user->id)
                ->where('set_id', '=', $set->id)
                ->where('score', '>=', config('test.grade_familiar'))
                ->count();

            $average = round(($average / $tests->count()), 1);
            $sets[] = [
                'name' => $set->name,
                'id' => $set->id,
                'average' => $average,
                'taken' => Test::where('user_id', '=', $user->id)->where('set_id', '=', $set->id)->count(),
                'last_time' => $last_taken,
                'mastery' => round((($total_mastery / $total_questions) * 100), 1),
                'proficient' => round((($total_proficient / $total_questions) * 100), 1),
                'familiar' => round((($total_familiar / $total_questions) * 100), 1),
            ];
        }

        $incomplete = Test::where('user_id', '=', $user->id)->whereNull('end_at')->get();

        return view('home', [
            'sets' => $sets,
            'incomplete' => $incomplete,
        ]);
    }

    public function history($id): View
    {
        $user_id = Auth::id();

        $user = User::find($user_id);
        $set = Set::find($id);

        $tests = Test::where('user_id', '=', $user->id)
            ->where('set_id', '=', $id)
            ->orderBy('end_at', 'desc')
            ->get();

        foreach ($tests as $test) {
            $start = new Carbon($test->start_at);
            $diffTime = $start->diffInMinutes($test->getAttributes()['end_at']);
            $test->duration = $diffTime;
        }

        return view('history', [
            'tests' => $tests,
            'set' => $set,
        ]);
    }
}
