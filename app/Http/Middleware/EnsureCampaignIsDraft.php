<?php

namespace App\Http\Middleware;

use App\Models\Campaign;
use Closure;
use Illuminate\Http\Request;

class EnsureCampaignIsDraft
{
    public function handle(Request $request, Closure $next)
    {
        $campaign = Campaign::findOrFail($request->route('campaign'));

        if ($campaign->status !== 'draft') {
            return response()->json(['error' => 'Campaign must be in draft status.'], 422);
        }

        return $next($request);
    }
}
