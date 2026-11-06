<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class CategoryController extends Controller
{
    public function index()
    {
        Gate::authorize('viewAny', Category::class);

        return CategoryResource::collection(
            Category::withCount('documents')->orderBy('nom')->get()
        );
    }

    public function store(CategoryRequest $request): JsonResponse
    {
        Gate::authorize('create', Category::class);

        $category = Category::create($request->validated());

        return (new CategoryResource($category))->response()->setStatusCode(201);
    }

    public function update(CategoryRequest $request, Category $category): CategoryResource
    {
        Gate::authorize('update', $category);

        $category->update($request->validated());

        return new CategoryResource($category->loadCount('documents'));
    }

    public function destroy(Category $category): JsonResponse|Response
    {
        Gate::authorize('delete', $category);

        if ($category->documents()->exists()) {
            return response()->json([
                'message' => 'Suppression impossible : cette catégorie contient encore des documents.',
            ], 409);
        }

        $category->delete();

        return response()->noContent();
    }
}
