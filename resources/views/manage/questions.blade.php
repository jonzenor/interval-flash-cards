@extends('layouts.app2')

@section('content')
<x-card.main title="{!! $set->name !!}">

    <x-card.mini title="Exam Settings">
        <form action="{{ route('update-exam', $set) }}" method="POST">
            @csrf
            <x-form.text name="name" label="Name" value="{!! $set->name !!}" />
            <x-form.text name="description" label="Description" value="{{ $set->description }}" />
            @php
                foreach ($visibilityOptions as $visibility)
                {
                    $visibilityValues[$visibility->value] = str_replace("is", "", $visibility->name);
                }
            @endphp
            <x-form.dropdown name="visibility" label="Public / Private" :values="$visibilityValues" selected="{{ $set->visibility }}" />
            
            <x-help.box>
                <x-help.text>The exams <x-help.highlight>Visibility</x-help.highlight> determines who can see or take an exam.</x-help.text>
                <x-help.text>If you set the exam to <x-help.highlight>Private</x-help.highlight> then only you, the exam's <x-help.highlight color="info">Architect</x-help.highlight>, can see the exam or take.</x-help.text>
                <x-help.text>If you set the exam to <x-help.highlight>Public</x-help.highlight> then every Acolyte at the academy will be able to see the exam and take it, starting their own journey down the path of mastery.</x-help.text>
                <x-help.text>In the future the <x-help.highlight color="info">Keeper</x-help.highlight> has plans to reward Architects who make their exams public.</x-help.text>
            </x-help.box>

            <x-card.buttons submitLabel="Update Exam Settings" />
        </form>
    </x-card.mini>

    <x-card.mini title="Question Groups">
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
                @foreach ($set->groups as $group)
                    <x-table.row>
                        <x-table.cell>{{ $group->name }}</x-table.cell>
                        <x-table.cell>{{ $group->questions->count() }}</x-table.cell>
                        <x-table.cell><x-card.buttons secondaryLabel="Manage Group" secondaryAction="{{ route('group-view', $group) }}" /></x-table.cell>
                    </x-table.row>
                @endforeach
            </x-table.body>
        </x-table.main>
        
        <x-card.buttons primaryLabel="Add a Group" primaryAction="{{ route('group-create', $set) }}" />
    </x-card.mini>

    <x-card.mini title="Test Questions">
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
                        <x-table.cell><x-card.buttons primaryAction="{{ route('manage-answers', $question->id) }}" primaryLabel="Edit"/></x-table.cell>        
                    </x-table.row>
                @endforeach
            </x-table.body>
        </x-table.main>

        <x-card.buttons primaryAction="{{ route('add-question', $set->id) }}" primaryLabel="Add Question" />
    </x-card.mini>
</x-card.main>

<div class="justify-end w-10/12 mx-auto my-5 text-right card-action">
    <a href="{{ route('profile.myexams') }}" class="btn btn-secondary">{{ __('Manage Your Exams') }}</a>
</div>
@endsection
