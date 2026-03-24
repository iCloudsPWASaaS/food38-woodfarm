<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;
use App\Models\MerchantConfig;
use App\Http\Requests\MerchantRequest;
use App\Libraries\QueryExceptionLibrary;

class MerchantService
{
    /**
     * Return all saved provider configs.
     *
     * @throws Exception
     */
    public function list()
    {
        try {
            return MerchantConfig::all();
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }

    /**
     * Create or update config for the given provider.
     * Provider is unique — no duplicates allowed.
     *
     * @throws Exception
     */
    public function update(MerchantRequest $request)
    {
        try {
            MerchantConfig::updateOrCreate(
                ['provider' => $request->provider],
                $request->validated()
            );
            return $this->list();
        } catch (Exception $exception) {
            Log::info($exception->getMessage());
            throw new Exception(QueryExceptionLibrary::message($exception), 422);
        }
    }
}
