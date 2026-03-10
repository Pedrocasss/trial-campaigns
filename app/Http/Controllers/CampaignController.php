<?php

namespace App\Http\Controllers;

use App\Contracts\CampaignRepositoryInterface;
use App\Http\Requests\StoreCampaignRequest;
use App\Models\Campaign;
use App\Services\CampaignService;
use Illuminate\Http\JsonResponse;

class CampaignController extends Controller
{
    public function __construct(
        private readonly CampaignRepositoryInterface $campaigns
    ) {}

    public function index(): JsonResponse
    {
        return response()->json($this->campaigns->paginateWithStats());
    }

    public function store(StoreCampaignRequest $request): JsonResponse
    {
        $campaign = $this->campaigns->create($request->validated());

        return response()->json($campaign, 201);
    }

    public function show(Campaign $campaign): JsonResponse
    {
        $campaign = $this->campaigns->findWithStats($campaign->id);

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
