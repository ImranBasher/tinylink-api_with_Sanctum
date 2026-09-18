<?php

namespace App\Http\Controllers;


use App\Http\Requests\ListUrlsRequest;
use App\Http\Requests\StoreUrlRequest;
use App\Services\UrlService;
use App\Traits\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UrlController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly UrlService $urlService) {}

    public function store(StoreUrlRequest $request): JsonResponse
    {
        return $this->successResponse(
            $this->urlService->create($request->user(), $request->validated()),
            'URL shortened successfully.',
            201
        );
    }

    public function index(ListUrlsRequest $request): JsonResponse
    {
        $validated = $request->validated();

        return $this->successResponse(
            $this->urlService->listUrls(
                $request->user(),
                (int) ($validated['page'] ?? 1),
                (int) ($validated['per_page'] ?? 10)
            ),
            'URLs retrieved successfully.'
        );
    }

    public function show(Request $request, int $id): JsonResponse
    {
        try {
            return $this->successResponse(
                $this->urlService->details($request->user(), $id),
                'URL retrieved successfully.'
            );
        } catch (\Throwable $e) {
            return $this->handleUrlException($e);
        }
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $this->urlService->delete($request->user(), $id);

            return $this->successResponse(null, 'URL deleted successfully.');
        } catch (\Throwable $e) {
            return $this->handleUrlException($e);
        }
    }

    public function stats(Request $request, int $id): JsonResponse
    {
        try {
            return $this->successResponse(
                $this->urlService->stats($request->user(), $id),
                'URL statistics retrieved successfully.'
            );
        } catch (\Throwable $e) {
            return $this->handleUrlException($e);
        }
    }

    public function redirect(string $short_code): RedirectResponse|JsonResponse
    {
        try {
            return redirect()->away($this->urlService->redirect($short_code));
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Not found.'], 404);
        }
    }

    private function handleUrlException(\Throwable $e): JsonResponse
    {
        return match (true) {
            $e instanceof ModelNotFoundException => $this->errorResponse('Not found.', 404),
            $e instanceof AuthorizationException => $this->errorResponse('This action is unauthorized.', 403),
            default => $this->errorResponse('Something went wrong.', 500),
        };
    }
}