@extends('layouts.app')

@section('title', 'Create Ad')

@section('content')
<div class="max-w-3xl mx-auto px-4 py-8">
    <div class="mb-6">
        <a href="{{ route('ads.index') }}" class="text-sm font-semibold text-indigo-600 hover:text-indigo-800 transition flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Back to Dashboard
        </a>
        <h1 class="text-3xl font-extrabold text-gray-900 mt-2">Create Lead Generation Ad</h1>
        <p class="text-gray-500">Reach the right audience, generate leads directly into your dashboard.</p>
    </div>

    @if($errors->any())
        <div class="bg-red-50 text-red-600 border border-red-200 p-4 rounded-lg mb-6 text-sm">
            <h4 class="font-bold mb-1">Please fix the following validation errors:</h4>
            <ul class="list-disc list-inside">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('ads.store') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
        @csrf
        
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-6">
            {{-- Ad Creative --}}
            <div>
                <h3 class="text-lg font-bold text-gray-900 mb-4">Ad Details</h3>
                <div class="space-y-4">
                    <div>
                        <label for="title" class="block text-sm font-semibold text-gray-700 mb-1">Ad Title *</label>
                        <input type="text" name="title" id="title" required value="{{ old('title') }}" placeholder="e.g. Learn Web Development from Experts" class="input-field">
                    </div>

                    <div>
                        <label for="description" class="block text-sm font-semibold text-gray-700 mb-1">Ad Description *</label>
                        <textarea name="description" id="description" rows="4" required placeholder="Describe your product, service, or offer. Tell users what they get." class="input-field">{{ old('description') }}</textarea>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="cta_text" class="block text-sm font-semibold text-gray-700 mb-1">Call to Action (CTA) Button *</label>
                            <select name="cta_text" id="cta_text" required class="input-field">
                                <option value="Learn More" {{ old('cta_text') === 'Learn More' ? 'selected' : '' }}>Learn More</option>
                                <option value="Sign Up" {{ old('cta_text') === 'Sign Up' ? 'selected' : '' }}>Sign Up</option>
                                <option value="Apply Now" {{ old('cta_text') === 'Apply Now' ? 'selected' : '' }}>Apply Now</option>
                                <option value="Get Offer" {{ old('cta_text') === 'Get Offer' ? 'selected' : '' }}>Get Offer</option>
                                <option value="Contact Us" {{ old('cta_text') === 'Contact Us' ? 'selected' : '' }}>Contact Us</option>
                            </select>
                        </div>

                        <div>
                            <label for="image" class="block text-sm font-semibold text-gray-700 mb-1">Creative Image *</label>
                            <input type="file" name="image" id="image" required accept="image/*" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                        </div>
                    </div>
                </div>
            </div>

            <hr class="border-gray-100">

            {{-- Plans selection --}}
            <div>
                <h3 class="text-lg font-bold text-gray-900 mb-1">Select Advertising Plan</h3>
                <p class="text-sm text-gray-500 mb-4">Choose a duration that fits your campaign goals. Ads run continuously during the plan period.</p>
                
                <input type="hidden" name="plan" id="selected-plan" value="{{ old('plan', 'monthly') }}">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Monthly -->
                    <div type="button" onclick="selectPlan('monthly', 999.00)" id="plan-monthly" class="plan-card border-2 border-indigo-600 bg-indigo-50/30 rounded-xl p-4 cursor-pointer transition flex flex-col justify-between h-36">
                        <div>
                            <div class="flex justify-between items-start">
                                <span class="font-bold text-gray-900 text-lg">Monthly</span>
                                <span class="w-5 h-5 rounded-full border-4 border-indigo-600 bg-white flex items-center justify-center" id="radio-monthly"></span>
                            </div>
                            <span class="text-xs text-gray-500">Run ads for 30 Days</span>
                        </div>
                        <div class="mt-4">
                            <span class="text-2xl font-extrabold text-gray-900">₹999</span>
                            <span class="text-xs text-gray-500">/ month</span>
                        </div>
                    </div>

                    <!-- Quarterly -->
                    <div type="button" onclick="selectPlan('quarterly', 2499.00)" id="plan-quarterly" class="plan-card border-2 border-gray-200 rounded-xl p-4 cursor-pointer transition flex flex-col justify-between h-36">
                        <div>
                            <div class="flex justify-between items-start">
                                <span class="font-bold text-gray-900 text-lg">Quarterly</span>
                                <span class="w-5 h-5 rounded-full border-2 border-gray-300 bg-white flex items-center justify-center" id="radio-quarterly"></span>
                            </div>
                            <span class="text-xs text-gray-500">Run ads for 90 Days</span>
                        </div>
                        <div class="mt-4 flex flex-col">
                            <div>
                                <span class="text-2xl font-extrabold text-gray-900">₹2,499</span>
                                <span class="text-xs text-green-600 font-bold bg-green-50 px-2 py-0.5 rounded ml-1">Save 16%</span>
                            </div>
                        </div>
                    </div>

                    <!-- Half Yearly -->
                    <div type="button" onclick="selectPlan('half_yearly', 4499.00)" id="plan-half_yearly" class="plan-card border-2 border-gray-200 rounded-xl p-4 cursor-pointer transition flex flex-col justify-between h-36">
                        <div>
                            <div class="flex justify-between items-start">
                                <span class="font-bold text-gray-900 text-lg">Half-Yearly</span>
                                <span class="w-5 h-5 rounded-full border-2 border-gray-300 bg-white flex items-center justify-center" id="radio-half_yearly"></span>
                            </div>
                            <span class="text-xs text-gray-500">Run ads for 180 Days</span>
                        </div>
                        <div class="mt-4">
                            <span class="text-2xl font-extrabold text-gray-900">₹4,499</span>
                            <span class="text-xs text-green-600 font-bold bg-green-50 px-2 py-0.5 rounded ml-1">Save 25%</span>
                        </div>
                    </div>

                    <!-- Yearly -->
                    <div type="button" onclick="selectPlan('yearly', 7999.00)" id="plan-yearly" class="plan-card border-2 border-gray-200 rounded-xl p-4 cursor-pointer transition flex flex-col justify-between h-36">
                        <div>
                            <div class="flex justify-between items-start">
                                <span class="font-bold text-gray-900 text-lg">Yearly</span>
                                <span class="w-5 h-5 rounded-full border-2 border-gray-300 bg-white flex items-center justify-center" id="radio-yearly"></span>
                            </div>
                            <span class="text-xs text-gray-500">Run ads for 365 Days</span>
                        </div>
                        <div class="mt-4">
                            <span class="text-2xl font-extrabold text-gray-900">₹7,999</span>
                            <span class="text-xs text-green-600 font-bold bg-green-50 px-2 py-0.5 rounded ml-1">Save 33%</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex justify-end gap-3">
            <a href="{{ route('ads.index') }}" class="btn-secondary text-sm">Cancel</a>
            <button type="submit" class="btn-primary text-sm flex items-center gap-2">
                Continue to Payment
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
            </button>
        </div>
    </form>
</div>

<script>
    function selectPlan(plan, price) {
        document.getElementById('selected-plan').value = plan;

        // Reset all borders and radio styles
        const plans = ['monthly', 'quarterly', 'half_yearly', 'yearly'];
        plans.forEach(p => {
            const card = document.getElementById('plan-' + p);
            const radio = document.getElementById('radio-' + p);
            if (p === plan) {
                card.className = "plan-card border-2 border-indigo-600 bg-indigo-50/30 rounded-xl p-4 cursor-pointer transition flex flex-col justify-between h-36";
                radio.className = "w-5 h-5 rounded-full border-4 border-indigo-600 bg-white flex items-center justify-center";
            } else {
                card.className = "plan-card border-2 border-gray-200 rounded-xl p-4 cursor-pointer transition flex flex-col justify-between h-36";
                radio.className = "w-5 h-5 rounded-full border-2 border-gray-300 bg-white flex items-center justify-center";
            }
        });
    }

    // Set old input if exists
    const oldPlan = "{{ old('plan') }}";
    if (oldPlan) {
        selectPlan(oldPlan);
    }
</script>
@endsection
