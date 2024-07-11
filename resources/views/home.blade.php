@extends('layouts.app2')

@section('content')

    <x-card.main title='Your Completed Exams' size='grid'>
        @foreach($tests as $test)
            <x-card.mini :title="$test['name']">
                <x-text.main label='Recent Average:'><span class="font-bold text-neutral-content">{{ $test['average'] }}%</span></x-text.main>
                <div class="flex w-full p-4 mb-3 rounded-md bg-base-100">
                    <div class="mx-1 badge badge-accent">Questions: {{ $test['total_questions'] }}</div>
                    @if ($test['author'] )<div class="mx-1 badge badge-secondary">Author: {{ $test['author'] }}</div>@endif
                </div>
                <div class="flex w-full">
                    <div class="w-1/4 text-sm row text-secondary">Mastery:</div><div class="w-3/4"><progress class="w-56 progress progress-accent " value="{{ $test['mastery'] }}" max="100"></progress></div>
                </div>
                <div class="flex w-full">
                    <div class="w-1/4 text-sm row text-secondary">Proficient:</div><div class="w-3/4"><progress class="w-56 progress progress-secondary " value="{{ $test['proficient'] }}" max="100"></progress></div>
                </div>
                <div class="flex w-full">
                    <div class="w-1/4 text-sm row text-secondary">Familiar:</div><div class="w-3/4"><progress class="w-56 progress progress-success " value="{{ $test['familiar'] }}" max="100"></progress></div>
                </div>
                <div class="flex w-full">
                    <div class="w-1/4 text-sm row text-secondary">Apprentice:</div><div class="w-3/4"><progress class="w-56 progress progress-info " value="{{ $test['apprentice'] }}" max="100"></progress></div>
                </div>
                <br />
                {{-- <x-card.buttons primaryLabel='Retake Test' primaryAction="{{ route('select-test', $test['id']) }}" secondaryLabel="Practice" secondaryAction="{{ route('practice-start', $test['set']) }}"></x-card.buttons> --}}
                <x-card.buttons primaryLabel='Retake Test' primaryAction="{{ route('select-test', $test['id']) }}"></x-card.buttons>
            </x-card.mini>
        @endforeach
    </x-card.main>

    @if ($incomplete->count())
        <x-card.main title="Incomplete Exams" size="grid">
            @foreach($incomplete as $test)
                <x-card.mini title="{{ $test->set->name }}">
                    <x-text.main>{{ $test->set->description }}</x-text.main>
                    <x-card.buttons primaryAction="{{ route('take-test', $test->id) }}" primaryLabel="Continue Test" />
                </x-card.mini>
            @endforeach
        </x-card.main>
    @endif

    <x-card.buttons primaryAction="{{ route('exam-create') }}" primaryLabel="Create an Exam" secondaryAction="{{ route('tests') }}" secondaryLabel="View Public Exams" />

    <x-card.main title="Manage Your Exams" size="grid">
        @forelse ($sets as $set)
            <x-card.mini title="{{ $set->name }}">
                <br />
                @if ($set->visibility)
                    <div class="badge badge-primary">Public</div>
                @else
                    <div class="badge badge-secondary">Private</div>
                @endif

                <br />
                <x-text.main>{{ $set->description }}</x-text.main>
                
                <br />
                <x-card.buttons primaryAction="{{ route('select-test', $set->id) }}" primaryLabel="Start Test" secondaryAction="{{ route('manage-questions', $set->id) }}" secondaryLabel="Manage Exam" />
            </x-card.mini>
        @empty
            You have not created an exam yet. Create one?

            <x-card.buttons primaryAction="{{ route('exam-create') }}" primaryLabel="Create an Exam" />
        @endforelse
    </x-card.main>

@endsection
