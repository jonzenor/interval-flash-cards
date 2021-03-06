@extends('layouts.app')

@section('content')
    <div class="flex items-center">
        
        <div class="w-full sm:w-11/12 md:w-9/12 lg:w-8/12 m-auto bg-gray-200 rounded-lg">
            <div class="w-full bg-gray-700 rounded-t-lg text-center">
                <h1 class="text-white text-2xl">{{ $question->set->name }}</h1>
            </div>

            <div class="w-full my-2">
                <div class="w-full">
                    <h2 class="text-lg p-2"> {{ $test->questions->count() + 1 }} / {{ $test->num_questions }}</h2>
                    <p class="p-2 m-2 text-strong text-lg leading-loose text-blue-900">{{ $question->text }}</p>
                    <form action="{{ route('answer', $test->id) }}" method="post">
                        @csrf
                        <input type="hidden" name="question" value="{{ $question->id }}">
                        <input type="hidden" name="order" value="{{ $order }}">
                        <table class="w-full">
                            @foreach ($answers as $answer)
                                <tr>
                                    <td class="p-2 mb-4">
                                        @if ($multi)
                                            <input type="checkbox" class="w-4 h-4 bg-gray-200 text-gray-700 border border-gray-200 rounded py-3 px-4 mb-3 text-xl" name="answer[{{ $answer->id }}]">
                                        @else 
                                            <input type="radio" class="w-6 h-4 bg-gray-200 text-gray-700 border border-gray-200 rounded py-3 px-4 mb-3 text-xl" name="answer" value="{{ $answer->id }}">
                                        @endif
                                    </td>
                                    <td class="p-2 mb-4 w-full">
                                        {{ $answer->text }}
                                    </td>
                                </tr>
                            @endforeach
                        </table>
                        <div class="w-full text-center p-4">
                            <input type="submit" class="px-3 bg-gray-800 rounded-lg text-white text-xl" value="Submit Answer">
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
    </div>
@endsection
