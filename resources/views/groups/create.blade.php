@extends('layouts.app')

@section('title', 'Create Group')

@section('content')
<div class="max-w-2xl mx-auto p-4">
    <div class="bg-white rounded-lg shadow p-6">
        <h1 class="text-2xl font-bold mb-6">Create a Group</h1>
        <form action="{{ route('groups.store') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-medium mb-1">Group Name</label>
                <input type="text" name="name" class="input-field" required>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Description</label>
                <textarea name="description" rows="3" class="input-field"></textarea>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Privacy</label>
                <select name="privacy" class="input-field">
                    <option value="public">Public</option>
                    <option value="private">Private</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Avatar</label>
                <input type="file" name="avatar" accept="image/*" class="input-field">
            </div>
            <button type="submit" class="btn-primary w-full">Create Group</button>
        </form>
    </div>
</div>
@endsection
