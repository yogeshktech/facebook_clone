@extends('layouts.app')

@section('title', 'Edit Profile')

@section('content')
<div class="max-w-2xl mx-auto p-4">
    <div class="bg-white rounded-lg shadow p-6">
        <h1 class="text-2xl font-bold mb-6">Edit Profile</h1>
        <form action="{{ route('profile.update') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
            @csrf @method('PUT')
            <div>
                <label class="block text-sm font-medium mb-1">Name</label>
                <input type="text" name="name" value="{{ old('name', $user->name) }}" class="input-field" required>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Username</label>
                <input type="text" name="username" value="{{ old('username', $user->username) }}" class="input-field">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Bio</label>
                <textarea name="bio" rows="3" class="input-field">{{ old('bio', $user->bio) }}</textarea>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Location</label>
                <input type="text" name="location" value="{{ old('location', $user->location) }}" class="input-field">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Website</label>
                <input type="url" name="website" value="{{ old('website', $user->website) }}" class="input-field">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Profile Picture</label>
                <input type="file" name="avatar" accept="image/*" class="input-field">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Cover Photo</label>
                <input type="file" name="cover_photo" accept="image/*" class="input-field">
            </div>
            <button type="submit" class="btn-primary w-full">Save Changes</button>
        </form>
    </div>
</div>
@endsection
