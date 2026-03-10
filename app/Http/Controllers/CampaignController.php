<?php

namespace App\Http\Controllers;

use App\Contracts\CampaignRepositoryInterface;
use App\Http\Requests\StoreCampaignRequest;
use App\Http\Resources\CampaignResource;
use App\Models\Campaign;
use App\Services\CampaignService;
use Illuminate\Http\JsonResponse;

class CampaignController extends Controller
{
    public function __construct(
        private readonly CampaignRepositoryInterface $campaigns
    ) {}

    public function index()
    {
        return CampaignResource::collection($this->campaigns->paginateWithStats());
    }

    public function store(StoreCampaignRequest $request): JsonResponse
    {
        $campaign = $this->campaigns->create($request->validated());

        return (new CampaignResource($campaign))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Campaign $campaign): CampaignResource
    {
        $campaign = $this->campaigns->findWithStats($campaign->id);

        return new CampaignResource($campaign);
    }

    public function dispatch(Campaign $campaign, CampaignService $service): JsonResponse
    {
        $service->dispatch($campaign);

        return response()->json(['message' => 'Campaign dispatch started.']);
    }
}
