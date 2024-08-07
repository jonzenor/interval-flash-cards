@extends('layouts.app2')

@section('content')
<x-card.main title="{!! $exam->name !!}">

    <div class="collapse collapse-arrow">
        <input type="checkbox">
        <div class="w-1/2 mx-auto collapse-title btn btn-secondary btn-outline btn-md">Show/Hide Settings</div>
        <div class="collapse-content">
            <x-card.mini title="Exam Settings">
                <form action="{{ route('exam.update', $exam) }}" method="POST">
                    @csrf
                    <x-form.text name="name" label="Name" value="{!! $exam->name !!}" />
                    <x-form.text name="description" label="Description" value="{!! $exam->description !!}" />
                    @php
                        foreach ($visibilityOptions as $visibility)
                        {
                            $visibilityValues[$visibility->value] = str_replace("is", "", $visibility->name);
                        }
                    @endphp
                    <x-form.dropdown name="visibility" label="Public / Private" :values="$visibilityValues" selected="{{ $exam->visibility }}" />
                    
                    <x-help.box>
                        <x-help.text>The exams <x-help.highlight>Visibility</x-help.highlight> determines who can see or take an exam.</x-help.text>
                        <x-help.text>If you set the exam to <x-help.highlight>Private</x-help.highlight> then only you, the exam's <x-help.highlight color="info">Architect</x-help.highlight>, can see the exam or take.</x-help.text>
                        <x-help.text>If you set the exam to <x-help.highlight>Public</x-help.highlight> then every Acolyte at the academy will be able to see the exam and take it, starting their own journey down the path of mastery.</x-help.text>
                        <x-help.text>In the future the <x-help.highlight color="info">Keeper</x-help.highlight> has plans to reward Architects who make their exams public.</x-help.text>
                    </x-help.box>

                    <x-card.buttons submitLabel="Update Exam Settings" />
                </form>
            </x-card.mini>
        </div>
    </div>
</x-card.main>

<x-card.main title="Question Groups">
    <x-card.mini>
        <x-help.box>
            <x-help.text>Hey there! I bet you're wondering what this whole <x-help.highlight>Question Groups</x-help.highlight> thing is about.</x-help.text>
            <x-help.text><x-help.highlight color="normal">Question Groups</x-help.highlight> allow you to group questions together that have similar answers. These can be similar in <x-help.highlight color="info">appearance</x-help.highlight> or <x-help.highlight color="info">content</x-help.highlight>.</x-help.text>
            <x-help.text>Unlike regualr test questions, when you add a question to a <x-help.highlight color="normal">Question Group</x-help.highlight> you only have to specify <x-help.highlight color="secondary">the one correct answer</x-help.highlight>.</x-help.text>
            <x-help.text>The real magic happens when an acolyte takes an exam.</x-help.text>
            <x-help.text>When a question shows up from your <x-help.highlight color="normal">Question Groups</x-help.highlight>, the incorrect answers are <x-help.highlight color="accent">magically</x-help.highlight> selected from the other questions in the same group.</x-help.text>
            <x-help.text>This helps make your exam dynamic. You don't have to worry about figuring out the best fake answers to put in to test the acolytes. As long as all of the answers in your <x-help.highlight color="normal">Question Groups</x-help.highlight> are similar then it will give the other acolytes a challenging exam.</x-help.text>
            <x-help.text>And it makes exam creation easier for you as well!</x-help.text>
            <x-help.text>You can have as many <x-help.highlight color="normal">Question Groups</x-help.highlight> as you need.</x-help.text>
        </x-help.box>

        <x-table.main>
            <x-table.head>
                <x-table.hcell>Name</x-table.hcell>
                <x-table.hcell># Questions</x-table.hcell>
                <x-table.hcell>&nbsp;</x-table.hcell>
            </x-table.head>
            <x-table.body>
                @foreach ($exam->groups as $group)
                    <x-table.row>
                        <x-table.cell>{{ $group->name }}</x-table.cell>
                        <x-table.cell>{{ $group->questions->count() }}</x-table.cell>
                        <x-table.cell><a href="{{ route('group-view', $group) }}" class="btn btn-secondary btn-outline">Manage Question Group</a></x-table.cell>
                    </x-table.row>
                @endforeach
            </x-table.body>
        </x-table.main>
    </x-card.mini>

    
    <div class="collapse collapse-arrow">
        <input type="checkbox">
        <div class="w-1/2 mx-auto collapse-title btn btn-md btn-secondary btn-outline">Create Question Group</div>
        <div class="collapse-content">
            <x-card.mini title="Create Question Group">
                <form action="{{ route('group-store', $exam->id) }}" method="post">
                    @csrf
    
                    <x-form.text name="name" label="Name" />
    
                    <x-card.buttons submitLabel="Add Group" />
                </form>
            </x-card.mini>
        </div>        
    </div>
</x-card.main>

<x-card.main title="Test Questions">
    <x-card.mini title="Add Quesiton">
        <form action="{{ route('exam.add', $exam) }}" method="post">
            @csrf
            <h3 class="text-lg font-bold text-secondary">Question</h3>
            <x-form.text name="question" value="{{ old('question') }}" />

            <h3 class="text-lg font-bold text-secondary">Answers</h3>
            @for ($i = 1; $i <= 4; $i ++)
                <div class="divider">Answer #{{ $i }}</div>
                <div class="block lg:flex">
                    <div class="w-full px-4 lg:w-1/4">
                        @if ($i == 1)
                            <x-form.checkbox name="correct[{{ $i }}]" label="Answer is correct?" checked="yes" />
                        @else
                            <x-form.checkbox name="correct[{{ $i }}]" label="Answer is correct?"  />
                        @endif
                    </div>

                    <div class="w-full lg:w-3/4">
                        <x-form.text name="answers[{{ $i }}]" value="{{ old('answers[' . $i . ']') }}" />
                    </div>
                </div>
            @endfor

            <div class="w-full my-2 text-right">
                <input type="submit" value="Add Question" class="btn btn-primary">
            </div>
        </form>
    </x-card.mini>

    <x-card.mini title="Normal Questions">
        <x-table.main>
            <x-table.head>
                <x-table.hcell>{{ __('Question') }}</x-table.hcell>
                <x-table.hcell hideMobile='true'>{{ __('# Answers') }}</x-table.hcell>
                <x-table.hcell>{{ __('Actions') }}</x-table.hcell>
            </x-table.head>
            <x-table.body>
                @foreach ($questions as $question)
                    <x-table.row>
                        <x-table.cell>{{ $question->text }}</x-table.cell>
                        <x-table.cell hideMobile='true'>{{ $question->answers->count() }}</x-table.cell>
                        <x-table.cell>
                            <a href="{{ route('exam.question', ['exam' => $exam, 'question' => $question]) }}" class="btn btn-secondary btn-outline">Edit Question</a>
                        </x-table.cell>
                    </x-table.row>
                @endforeach
            </x-table.body>
        </x-table.main>

    </x-card.mini>
</x-card.main>

<div class="justify-end w-10/12 mx-auto my-5 text-right card-action">
    <a href="{{ route('exam.index') }}" class="btn btn-secondary">{{ __('Manage Your Exams') }}</a>
</div>
@endsection
