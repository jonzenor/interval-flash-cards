<?php

namespace App\Console\Commands;

use App\Models\Group;
use App\Models\Question;
use App\Models\Set;
use Illuminate\Console\Command;

class migrateQuestionGroups extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:migrateGroups';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $questions = Question::where('group', '!=', null)->get();
        $sets = [];

        foreach ($questions as $question) {
            $set = null;

            if (array_key_exists($question->set_id, $sets)) {
                $set = $sets[$question->set_id];
            } else {
                $set = Set::find($question->set_id);
                $sets[$question->set_id] = $set;
            }

            // See if there is already a new group name that matches the group
            $group = Group::where('set_id', $set->id)->where('name', '=', $question->group)->first();

            if (! $group) {
                // Create the group
                $group = new Group();
                $group->set_id = $set->id;
                $group->name = $question->group;
                $group->save();
            }

            if (! $group) {
                $this->error('Group not found and not created...');

                continue;
            }

            $question->group_id = $group->id;
            $question->group = null;
            $question->save();

            $this->info('Updated Question '.$question->id.' to use group '.$group->name.' : '.$group->id);
        }
    }
}
