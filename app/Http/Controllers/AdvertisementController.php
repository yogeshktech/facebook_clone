<?php

namespace App\Http\Controllers;

use App\Models\Advertisement;
use App\Models\Lead;
use App\Support\MediaStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Carbon\Carbon;

class AdvertisementController extends Controller
{
    /**
     * Display a listing of the user's ads.
     */
    public function index(Request $request)
    {
        $ads = Advertisement::where('user_id', $request->user()->id)
            ->withCount('leads')
            ->latest()
            ->get();

        return view('ads.index', compact('ads'));
    }

    /**
     * Show the form for creating a new ad.
     */
    public function create()
    {
        return view('ads.create');
    }

    /**
     * Store a newly created ad in database.
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:100',
            'description' => 'required|string|max:1000',
            'image' => 'required|image|max:5120', // Max 5MB
            'cta_text' => 'required|string|in:Learn More,Sign Up,Apply Now,Get Offer,Contact Us',
            'plan' => 'required|string|in:monthly,quarterly,half_yearly,yearly',
        ]);

        // Calculate amount based on plan
        $amounts = [
            'monthly' => 999.00,
            'quarterly' => 2499.00,
            'half_yearly' => 4499.00,
            'yearly' => 7999.00,
        ];
        $amount = $amounts[$request->plan];

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = MediaStorage::store($request->file('image'), 'ads');
        }

        $ad = Advertisement::create([
            'user_id' => $request->user()->id,
            'title' => $request->title,
            'description' => $request->description,
            'image_path' => $imagePath,
            'cta_text' => $request->cta_text,
            'plan' => $request->plan,
            'amount' => $amount,
            'payment_status' => 'pending',
            'status' => 'pending_payment',
        ]);

        return redirect()->route('ads.payment', $ad)
            ->with('success', 'Ad created! Please complete payment to submit for approval.');
    }

    /**
     * Show the payment screen for an ad.
     */
    public function paymentScreen(Advertisement $ad)
    {
        // Authorize
        if ($ad->user_id !== auth()->id()) {
            abort(403);
        }

        if ($ad->payment_status === 'paid') {
            return redirect()->route('ads.index')->with('error', 'This ad is already paid.');
        }

        return view('ads.payment', compact('ad'));
    }

    /**
     * Process the payment (mock simulation).
     */
    public function processPayment(Request $request, Advertisement $ad)
    {
        if ($ad->user_id !== auth()->id()) {
            abort(403);
        }

        if ($ad->payment_status === 'paid') {
            return redirect()->route('ads.index')->with('error', 'This ad is already paid.');
        }

        $request->merge([
            'card_number' => preg_replace('/\s+/', '', (string) $request->input('card_number')),
        ]);

        $request->validate([
            'card_name' => 'required|string|max:100',
            'card_number' => 'required|string|digits:16',
            'expiry' => ['required', 'regex:/^\d{2}\/\d{2}$/'],
            'cvv' => 'required|string|digits_between:3,4',
        ]);

        $ad->update([
            'payment_status' => 'paid',
            'status' => 'pending_approval',
        ]);

        return redirect()->route('ads.index')
            ->with('success', 'Payment successful! Your ad is pending admin approval.');
    }

    /**
     * Submit a lead from an advertisement.
     */
    public function submitLead(Request $request, Advertisement $ad)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:150',
            'phone' => 'required|string|max:20',
            'notes' => 'nullable|string|max:500',
        ]);

        Lead::create([
            'advertisement_id' => $ad->id,
            'user_id' => auth()->check() ? auth()->id() : null,
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'notes' => $request->notes,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Thank you! Your details have been submitted.',
        ]);
    }

    /**
     * Show leads list for an ad.
     */
    public function showLeads(Advertisement $ad)
    {
        if ($ad->user_id !== auth()->id() && !auth()->user()->isAdmin()) {
            abort(403);
        }

        $leads = $ad->leads()->latest()->get();

        return view('ads.leads', compact('ad', 'leads'));
    }

    /**
     * Export leads as Excel/CSV.
     */
    public function downloadLeads(Advertisement $ad)
    {
        if ($ad->user_id !== auth()->id() && !auth()->user()->isAdmin()) {
            abort(403);
        }

        $leads = $ad->leads()->latest()->get();
        $filename = "leads_ad_" . $ad->id . "_" . date('Y-m-d') . ".csv";

        $headers = [
            "Content-type" => "text/csv",
            "Content-Disposition" => "attachment; filename=$filename",
            "Pragma" => "no-cache",
            "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
            "Expires" => "0"
        ];

        $callback = function() use($leads) {
            $file = fopen('php://output', 'w');
            // Write column headers
            fputcsv($file, ['ID', 'Name', 'Email', 'Phone', 'Notes', 'Submitted At']);

            foreach ($leads as $lead) {
                fputcsv($file, [
                    $lead->id,
                    $lead->name,
                    $lead->email,
                    $lead->phone,
                    $lead->notes,
                    $lead->created_at->format('Y-m-d H:i:s'),
                ]);
            }

            fclose($file);
        };

        return Response::stream($callback, 200, $headers);
    }

    /*
     * ==========================================
     * ADMIN METHODS
     * ==========================================
     */

    /**
     * Admin: Display list of all ads in system.
     */
    public function adminIndex()
    {
        $ads = Advertisement::with('user')
            ->withCount('leads')
            ->latest()
            ->get();

        return view('ads.admin.index', compact('ads'));
    }

    /**
     * Admin: Approve an advertisement.
     */
    public function approveAd(Advertisement $ad)
    {
        if ($ad->payment_status !== 'paid') {
            return back()->with('error', 'Payment must be completed before approving this ad.');
        }

        if ($ad->status === 'approved') {
            return back()->with('error', 'This ad is already approved.');
        }
        $days = match ($ad->plan) {
            'monthly' => 30,
            'quarterly' => 90,
            'half_yearly' => 180,
            'yearly' => 365,
            default => 30,
        };

        $ad->update([
            'status' => 'approved',
            'expires_at' => Carbon::now()->addDays($days),
        ]);

        return back()->with('success', 'Advertisement approved and is now active.');
    }

    /**
     * Admin: Reject an advertisement.
     */
    public function rejectAd(Request $request, Advertisement $ad)
    {
        $ad->update([
            'status' => 'rejected',
        ]);

        return back()->with('success', 'Advertisement has been rejected.');
    }
}
