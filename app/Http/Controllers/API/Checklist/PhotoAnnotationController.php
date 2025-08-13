<?php

namespace App\Http\Controllers\API\Checklist;

use App\Http\Controllers\Controller;
use App\Models\Checklist\PhotoAnnotation;
use App\Services\Checklist\PhotoAnnotationService;
use App\Http\Requests\Checklist\PhotoAnnotation\StorePhotoAnnotationRequest;
use App\Http\Requests\Checklist\PhotoAnnotation\UpdatePhotoAnnotationRequest;
use Illuminate\Http\Request;
use App\Http\Resources\Checklist\PhotoAnnotationResource;

class PhotoAnnotationController extends Controller
{
    public function __construct(private PhotoAnnotationService $service) {}

    public function index(Request $request)
    {
        $data = $this->service->paginate($request->all(), (int) $request->get('per_page', 15));
        $data->getCollection()->transform(fn($item) => new PhotoAnnotationResource($item));
        return $data;
    }

    public function store(StorePhotoAnnotationRequest $request)
    {
        $model = $this->service->create($request->validated());
        return (new PhotoAnnotationResource($model))->response()->setStatusCode(201);
    }

    public function show(PhotoAnnotation $photoAnnotation)
    {
        return new PhotoAnnotationResource($photoAnnotation);
    }

    public function update(PhotoAnnotation $photoAnnotation, UpdatePhotoAnnotationRequest $request)
    {
        $photoAnnotation = $this->service->update($photoAnnotation, $request->validated());
        return new PhotoAnnotationResource($photoAnnotation);
    }

    public function destroy(PhotoAnnotation $photoAnnotation)
    {
        $this->service->delete($photoAnnotation);
        return response()->noContent();
    }
}
