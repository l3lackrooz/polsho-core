<?php

namespace App\Domain\Asset\Controllers;

use App\Domain\Asset\Actions\CreateInstrument;
use App\Domain\Asset\Actions\DeleteInstrument;
use App\Domain\Asset\Actions\ListInstruments;
use App\Domain\Asset\Actions\UpdateInstrument;
use App\Domain\Asset\Application\DTO\InstrumentDTO;
use App\Domain\Asset\Application\DTO\InstrumentFiltersDTO;
use App\Domain\Asset\Models\Instrument;
use App\Domain\Asset\Requests\StoreInstrumentRequest;
use App\Domain\Asset\Requests\UpdateInstrumentRequest;
use App\Domain\Shared\Concerns\RespondsWithApi;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InstrumentController extends Controller
{
    use RespondsWithApi;

    public function index(Request $request, ListInstruments $action): JsonResponse
    {
        return $this->respondPaginated(
            $action->execute(InstrumentFiltersDTO::fromRequest($request))
        );
    }

    public function store(StoreInstrumentRequest $request, CreateInstrument $action): JsonResponse
    {
        return $this->respond(
            $action->execute(InstrumentDTO::fromArray($request->validated())),
            201,
        );
    }

    public function show(Instrument $instrument): JsonResponse
    {
        return $this->respond($instrument->load(['baseAsset', 'quoteAsset']));
    }

    public function update(UpdateInstrumentRequest $request, Instrument $instrument, UpdateInstrument $action): JsonResponse
    {
        return $this->respond(
            $action->execute($instrument, InstrumentDTO::forUpdate($instrument, $request->validated()))
        );
    }

    public function destroy(Instrument $instrument, DeleteInstrument $action): JsonResponse
    {
        $action->execute($instrument);

        return $this->respondMessage('Instrument deleted.');
    }
}
