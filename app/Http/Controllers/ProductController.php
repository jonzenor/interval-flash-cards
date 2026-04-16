<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Gate;
use App\Models\Product;
use Illuminate\Http\Request;
use App\Http\Requests\ProductDetailsRequest;
use App\Actions\Product\StandardizeProductFormData;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        Gate::authorize('viewAny', Product::class);

        $products = Product::all();

        return view('product.index')->with([
            'products' => $products,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        Gate::authorize('create', Product::class);

        return view('product.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(ProductDetailsRequest $request)
    {
        Gate::authorize('create', Product::class);

        $validated = StandardizeProductFormData::execute($request);

        Product::create($request->validated());

        return redirect()->route('admin.product.index')->with('success', 'Product added');
    }

    /**
     * Display the specified resource.
     */
    public function show(Product $product)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Product $product)
    {
        Gate::authorize('update', $product);

        return view('product.edit')->with([
            'product' => $product,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(ProductDetailsRequest $request, Product $product)
    {
        Gate::authorize('update', $product);

        $validated = StandardizeProductFormData::execute($request);
        
        $product->update($validated);

        return redirect()->route('admin.product.index')->with('success', 'Product updated');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Product $product)
    {
        //
    }
}
