<?php

namespace App\Http\Controllers;

use App\Models\Advertisement;
use App\Models\Lead;
use App\Services\AdPaymentService;
use App\Support\AdPlans;
use App\Support\MediaStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class AdvertisementController extends Controller
{
    public function __construct(private AdPaymentService $payments) {}

    public function index(Request $request)
    {
        $ads = Advertisement::where('user_id', $request->user()->id)
            ->withCount('leads')
            ->latest()
            ->get();

        return view('ads.index', compact('ads'));
    }

    public function create()
    {
        return view('ads.create', ['plans' => AdPlans::PLANS]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:100',
            'description' => 'required|string|max:1000',
            'image' => 'required|image|max:5120',
            'cta_text' => 'required|string|in:Learn More,Sign Up,Apply Now,Get Offer,Contact Us',
            'plan' => 'required|string|in:'.implode(',', AdPlans::keys()),
        ]);

        $amount = AdPlans::amount($request->plan);

        $imagePath = $request->hasFile('image')
            ? MediaStorage::store($request->file('image'), 'ads')
            : null;

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
            ->with('success', 'Ad saved! Complete payment to start running your campaign.');
    }

    public function paymentScreen(Advertisement $ad)
    {
        if ($ad->user_id !== auth()->id()) {
            abort(403);
        }

        if ($ad->payment_status === 'paid' && $ad->isRunning()) {
            return redirect()->route('ads.index')->with('success', 'This ad is already paid and running.');
        }

        return view('ads.payment', compact('ad'));
    }

    public function processPayment(Request $request, Advertisement $ad)
    {
        if ($ad->user_id !== auth()->id()) {
            abort(403);
        }

        if ($ad->payment_status === 'paid' && $ad->isRunning()) {
            return redirect()->route('ads.index')->with('error', 'This ad is already active.');
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

        if ($this->payments->shouldFailPayment($request->card_number)) {
            $this->payments->markPaymentFailed($ad);

            return redirect()->route('ads.payment', $ad)
                ->with('error', 'Payment failed. Your ad will not run until payment is completed.');
        }

        $this->payments->activateAfterPayment($ad);

        return redirect()->route('ads.index')
            ->with('success', 'Payment successful! Your ad is now live and running on the feed.');
    }

    public function submitLead(Request $request, Advertisement $ad)
    {
        if (! $ad->canAcceptLeads()) {
            return response()->json([
                'success' => false,
                'message' => 'This ad is not running yet. Payment may be pending or incomplete.',
            ], 422);
        }

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

    public function showLeads(Advertisement $ad)
    {
        if ($ad->user_id !== auth()->id() && ! auth()->user()->isAdmin()) {
            abort(403);
        }

        $leads = $ad->leads()->latest()->get();

        return view('ads.leads', compact('ad', 'leads'));
    }

    public function downloadLeads(Advertisement $ad)
    {
        if ($ad->user_id !== auth()->id() && ! auth()->user()->isAdmin()) {
            abort(403);
        }

        $leads = $ad->leads()->latest()->get();
        $filename = 'leads_ad_'.$ad->id.'_'.date('Y-m-d').'.csv';

        $headers = [
            'Content-type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=$filename",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($leads) {
            $file = fopen('php://output', 'w');
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

    public function adminIndex()
    {
        $ads = Advertisement::with('user')
            ->withCount('leads')
            ->latest()
            ->get();

        return view('ads.admin.index', compact('ads'));
    }

    public function rejectAd(Request $request, Advertisement $ad)
    {
        $ad->update(['status' => 'rejected']);

        return back()->with('success', 'Advertisement stopped and marked as rejected.');
    }
}
