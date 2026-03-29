<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CarRequest;
use App\Http\Resources\CarResource;
use App\Models\Car;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;


class CarController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->only([
            'governorate',
            'city',
            'make',
            'model',
            'year',
            'min_km',
            'max_km',
            'min_price',
            'max_price',
            'search',
        ]);

        $cars = Car::query()
            ->with(['governorate', 'city', 'make', 'model'])
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('description', 'like', "%$search%")
                        ->orWhere('color', 'like', "%$search%")
                        ->orWhereHas('make', fn($mq) => $mq->where('name', 'like', "%$search%"))
                        ->orWhereHas('model', fn($mq) => $mq->where('name', 'like', "%$search%"))
                        ->orWhereHas('governorate', fn($mq) => $mq->where('name', 'like', "%$search%"))
                        ->orWhereHas('city', fn($mq) => $mq->where('name', 'like', "%$search%"));
                });
            })
            ->when($filters['governorate'] ?? null, fn($q, $v) => $q->whereHas('governorate', fn($g) => $g->where('name', 'like', "%$v%")))
            ->when($filters['city'] ?? null, fn($q, $v) => $q->whereHas('city', fn($c) => $c->where('name', 'like', "%$v%")))
            ->when($filters['make'] ?? null, fn($q, $v) => $q->whereHas('make', fn($m) => $m->where('name', 'like', "%$v%")))
            ->when($filters['model'] ?? null, fn($q, $v) => $q->whereHas('model', fn($m) => $m->where('name', 'like', "%$v%")))
            ->when($filters['year'] ?? null, fn($q, $v) => $q->where('year', $v))
            ->when($filters['min_km'] ?? null, fn($q, $v) => $q->where('kilometers', '>=', $v))
            ->when($filters['max_km'] ?? null, fn($q, $v) => $q->where('kilometers', '<=', $v))
            ->when($filters['min_price'] ?? null, fn($q, $v) => $q->where('price', '>=', $v))
            ->when($filters['max_price'] ?? null, fn($q, $v) => $q->where('price', '<=', $v))
            ->latest()
            ->paginate(10);

        return CarResource::collection($cars);
    }

    public function store(CarRequest $request)
    {
        $data = $request->validated();
        $data['user_id'] = Auth::id();

        $car = Car::create($data);

        return response()->json([
            'message' => __('api.car_added'),
            'car'     => new CarResource($car),
        ], 201);
    }


    public function show(Car $car)
    {
        return new CarResource($car->load(['governorate', 'city', 'make', 'model']));
    }


    public function update(CarRequest $request, Car $car)
    {

        if ($car->user_id !== auth()->id()) {
            return response()->json(['message' => __('api.car_unauthorized')], 403);
        }

        $data = $request->validated();
        $car->update($data);

        return response()->json(['message' => __('api.car_updated'), 'car' => new CarResource($car)]);
    }

    public function destroy(Car $car)
    {

        if ($car->user_id !== auth()->id()) {
            return response()->json(['message' => __('api.car_unauthorized')], 403);
        }

        $car->delete();

        return response()->json(['message' => __('api.car_deleted')]);
    }
}
