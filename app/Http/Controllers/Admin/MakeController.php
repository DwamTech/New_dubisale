<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Models\Make;
use App\Models\CarModel;
use App\Traits\LocalizedResponse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MakeController extends Controller
{
    use LocalizedResponse;

    public function index()
    {
        $items = Make::with('models')->orderBy('name')->get();
        $items->push((object)[
            'id' => null, 'name' => 'غير ذلك', 'name_en' => 'Other', 'models' => []
        ]);

        return response()->json(
            $this->localizeCollection($items, ['name'])
        );
    }

    public function addMake(Request $request)
    {
        $data = $request->validate([
            'name'    => ['required', 'string', 'max:191'],
            'name_en' => ['nullable', 'string', 'max:191'],
        ]);

        $make = Make::where('name', $data['name'])->first();

        if ($make) {
            return response()->json(['message' => 'Make with this name already exists.'], 422);
        }

        $make = Make::create($data);

        CarModel::create([
            'name'    => 'غير ذلك',
            'name_en' => 'Other',
            'make_id' => $make->id,
        ]);

        $make->load('models');

        return response()->json($make, 201);
    }

    public function update(Request $request, Make $make)
    {
        $data = $request->validate([
            'name'    => ['sometimes', 'string', 'max:191', 'unique:makes,name,' . $make->id],
            'name_en' => ['nullable', 'string', 'max:191'],
        ]);

        $make->update($data);

        return response()->json($make->load('models'));
    }

    public function destroy(Make $make)
    {
        // كل الموديلات اللي تحت الماركة دي
        $modelIds = $make->models()->pluck('id');

        $isUsed = Listing::where('make_id', $make->id)
            ->orWhereIn('model_id', $modelIds)
            ->exists();

        if ($isUsed) {
            return response()->json([
                'message' => 'لا يمكن حذف هذه الماركة لأنها مرتبطة بإعلانات أو موديلات مستخدمة في إعلانات.',
            ], 422);
        }

        // لو حابة كمان يتم حذف كل الموديلات التابعة ليها:
        $make->models()->delete();

        $make->delete();

        return response()->json("Deleted successfully", 204);
    }

    public function models(Make $make)
    {
        $models = $make->models()->orderBy('name')->get();
        $models->push((object)[
            'id' => null, 'name' => 'غير ذلك', 'name_en' => 'Other', 'make_id' => $make->id
        ]);

        return response()->json(
            $this->localizeCollection($models, ['name'])
        );
    }

    public function addModel(Request $request, Make $make)
    {
        $data = $request->validate([
            'models'      => ['required', 'array', 'min:1'],
            'models.*.name' => [
                'required', 'string', 'max:191',
                Rule::unique('models', 'name')->where(fn($q) => $q->where('make_id', $make->id)),
            ],
            'models.*.name_en' => ['nullable', 'string', 'max:191'],
        ]);

        $createdModels = [];

        foreach ($data['models'] as $item) {
            $createdModels[] = CarModel::create([
                'name'    => $item['name'],
                'name_en' => $item['name_en'] ?? null,
                'make_id' => $make->id,
            ]);
        }

        return response()->json([
            'make_id' => $make->id,
            'models'  => $createdModels,
        ], 201);
    }

    public function updateModel(Request $request, CarModel $model)
    {
        $data = $request->validate([
            'name'    => ['required', 'string', 'max:191', 'unique:models,name,' . $model->id . ',id,make_id,' . $model->make_id],
            'name_en' => ['nullable', 'string', 'max:191'],
            'make_id' => ['required', 'integer', 'exists:makes,id'],
        ]);

        $model->update($data);
        return response()->json($model);
    }

    public function deleteModel(CarModel $model)
    {
        $isUsed = Listing::where('model_id', $model->id)->exists();

        if ($isUsed) {
            return response()->json([
                'message' => 'لا يمكن حذف هذا الموديل لأنه مستخدم في إعلانات حالية.',
            ], 422);
        }

        $model->delete();

        return response()->json("Deleted successfully", 204);
    }
}
