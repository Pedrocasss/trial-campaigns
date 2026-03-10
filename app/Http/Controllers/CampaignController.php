<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCampaignRequest;
use App\Models\Campaign;
use App\Services\CampaignService;
use Illuminate\Http\JsonResponse;

class CampaignController extends Controller
{
    public function index(): JsonResponse
    {
        $campaigns = Campaign::with('contactList')->withSendStats()->paginate(15);

        return response()->json($campaigns);
    }

    public function store(StoreCampaignRequest $request): JsonResponse
    {
        $campaign = Campaign::create($request->validated());

        return response()->json($campaign, 201);
    }

    public function show(Campaign $campaign): JsonResponse
    {
        $campaign->load('contactList');
        $campaign->loadCount([
            'sends as pending_count' => fn ($q) => $q->where('status', 'pending'),
            'sends as sent_count' => fn ($q) => $q->where('status', 'sent'),
            'sends as failed_count' => fn ($q) => $q->where('status', 'failed'),
            'sends as total_count',
        ]);

        return response()->json($campaign);
    }

    public function dispatch(Campaign $campaign, CampaignService $service): JsonResponse
    {
        if ($campaign->status !== 'draft') {
            return response()->json(['error' => 'Campaign must be in draft status.'], 422);
        }

        $service->dispatch($campaign);

        return response()->json(['message' => 'Campaign dispatch started.']);
    }
}
