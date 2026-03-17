<?php

namespace App\Http\Controllers;

use DB;
use Auth;
use Exception;
use Inertia\Inertia;
use App\Models\Swap;
use Illuminate\Http\Request;

class SwapController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Inertia::render('swaps/Swaps', [
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Swap $swap)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Swap $swap)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Swap $swap)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Swap $swap)
    {
        //
    }

    public function search(Request $request)
    {        
        $q = $request->input('q', '');
        
        return response()->json();
    }

}
