<?php

namespace App\Domain\Market\Controllers;

use App\Domain\Market\Application\DTO\FeederConfig;
use App\Http\Controllers\Controller;

class TestController extends Controller
{
    public function index()
    {
        $config = FeederConfig::generate(
            'the url',
            [
                'key' => 1234,
                'key2' => [
                    'key3' => [
                        'value',
                        'value2'
                    ],
                ],
            ]);

        return response()->json($config->get('key2.key3'));
    }
}
