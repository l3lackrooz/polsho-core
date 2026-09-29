<?php

namespace App\Domain\Market\Controllers;

use App\Domain\Shared\Concerns\RespondsWithApi;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class NotificationPreferencesController extends Controller
{
    use RespondsWithApi;

    public function show(Request $request)
    {
        return $this->respond(['market_milestones_enabled' => $request->user()->market_milestones_enabled]);
    }

    public function update(Request $request)
    {
        $data = $request->validate(['market_milestones_enabled' => ['required', 'boolean']]);
        $user = $request->user();
        $user->market_milestones_enabled = $data['market_milestones_enabled'];
        $user->save();

        return $this->respond(['market_milestones_enabled' => $user->market_milestones_enabled]);
    }
}
