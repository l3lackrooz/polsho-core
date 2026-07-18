<?php

use Illuminate\Support\Facades\Broadcast;

// This private channel is authenticated through /api/broadcasting/auth using
// the mobile app's Sanctum bearer token.
Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
}, ['guards' => ['sanctum']]);
