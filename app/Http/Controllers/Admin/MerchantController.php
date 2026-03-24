<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\MerchantRequest;
use App\Http\Resources\MerchantResource;
use App\Services\MerchantService;
use Exception;

class MerchantController extends AdminController
{
    public MerchantService $merchantService;

    public function __construct(MerchantService $merchantService)
    {
        parent::__construct();
        $this->merchantService = $merchantService;
        $this->middleware(['permission:settings'])->only('update');
    }

    /**
     * GET /admin/setting/merchant
     * Returns all saved provider configs as a collection.
     */
    public function index(): \Illuminate\Http\Response | \Illuminate\Http\Resources\Json\AnonymousResourceCollection | \Illuminate\Contracts\Foundation\Application | \Illuminate\Contracts\Routing\ResponseFactory
    {
        try {
            return MerchantResource::collection($this->merchantService->list());
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    /**
     * POST /admin/setting/merchant
     * Create or update config for a provider (no duplicates).
     */
    public function update(MerchantRequest $request): \Illuminate\Http\Response | \Illuminate\Http\Resources\Json\AnonymousResourceCollection | \Illuminate\Contracts\Foundation\Application | \Illuminate\Contracts\Routing\ResponseFactory
    {
        try {
            return MerchantResource::collection($this->merchantService->update($request));
        } catch (Exception $exception) {
            return response(['status' => false, 'message' => $exception->getMessage()], 422);
        }
    }
}
