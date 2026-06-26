@extends('layouts.app')

@section('title', 'Create Page')

@section('content')
<div class="max-w-2xl mx-auto p-4">
    <div class="bg-white rounded-lg shadow p-6">
        <h1 class="text-2xl font-bold mb-6">Create a Page</h1>
        <form action="{{ route('pages.store') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
            @csrf
            <div><label class="block text-sm font-medium mb-1">Page Name</label><input type="text" name="name" class="input-field" required></div>
            <div><label class="block text-sm font-medium mb-1">Category</label><input type="text" name="category" class="input-field" placeholder="e.g. Business, Brand"></div>
            <div><label class="block text-sm font-medium mb-1">Description</label><textarea name="description" rows="3" class="input-field"></textarea></div>
            <button type="submit" class="btn-primary w-full">Create Page</button>
        </form>
    </div>
</div>
@endsection
