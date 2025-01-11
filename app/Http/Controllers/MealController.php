<?php

namespace App\Http\Controllers;

use App\Http\Requests\MealRequest;
use App\Models\Category;
use App\Models\Meal;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;

class MealController extends Controller
{
    public function index()
    {
        $models = Meal::all();
        $curriers = \App\Models\User::where('role', 'currier')->get();
        $categories = Category::all();

        return view('meal.index', ['meals' => $models, 'categories' => $categories, 'curriers' => $curriers]);
    }

    public function store(MealRequest $request)
    {
        $data = $request->validated();

        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('meals', 'public');
            $data['image'] = $imagePath;
        }

        Meal::create($data);

        return redirect()->back()->with('success', 'Meal created successfully');
    }

    public function update(MealRequest $request, Meal $meal)
    {
        $data = $request->validated();

        if ($request->hasFile('image')) {
            if ($meal->image && Storage::exists($meal->image)) {
                Storage::delete($meal->image);
            }

            $imagePath = $request->file('image')->store('meals', 'public');
            $data['image'] = $imagePath;
        }

        $meal->update($data);

        return redirect()->back()->with('success', 'Meal updated successfully');
    }

    public function destroy(Meal $meal)
    {
        if ($meal->image && Storage::exists($meal->image)) {
            Storage::delete($meal->image);
        }

        $meal->delete();

        return redirect()->back()->with('success', 'Meal deleted successfully');
    }

    public function giveToCurrier(Request $request)
    {   

        $data = $request->all();
        $meal = Meal::find($data['meal_id']);
        $currier = \App\Models\User::find($data['currier_id']);

        Order::create([
            'currier_id' => $currier->id,
            'status' => 'pending',
            'price' => $meal->price,
            'location' => $data['location'],
            'date' => $data['datetime']          
        ]);

        OrderItem::create([
            'order_id' => Order::latest()->first()->id,
            'meal_id' => $meal->id
        ]);

        return redirect()->back()->with('success', 'Meal given to currier successfully');
    }
}
