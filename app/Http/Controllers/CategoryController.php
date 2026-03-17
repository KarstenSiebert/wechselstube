<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{    
    public function search(Request $request)
    {        
        $q = $request->input('q', '');

        $categories = Category::query()
            ->where(function ($query) use ($q) {
                $query->where('name', 'like', "%{$q}%");
            })
            ->limit(10)
            ->get(['id', 'name']);
            
        return response()->json($categories);
    }
}
