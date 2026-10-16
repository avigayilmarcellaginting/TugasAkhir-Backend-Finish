<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductBranch;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use App\Services\ImageService;

class InventoryController extends Controller
{
    protected $imageService;

    public function __construct(ImageService $imageService)
    {
        $this->imageService = $imageService;
    }

    // Categories
    public function getCategories(Request $request)
    {
        $user = $request->user();
        $query = Category::where('tenant_id', $user->tenant_id)->with('branch');

        // If not owner/admin, only show categories for their branch or null branch (global)
        if (!$user->isOwnerOrAdmin()) {
            $query->where(function ($q) use ($user) {
                $q->where('branch_id', $user->branch_id)->orWhereNull('branch_id');
            });
        } elseif ($request->has('branch_id')) {
            $query->where(function ($q) use ($request) {
                $q->where('branch_id', $request->branch_id)
                    ->orWhereNull('branch_id');
            });
        }

        return $query->get();
    }

    public function storeCategory(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'branch_id' => 'nullable|exists:branches,id',
        ]);

        $user = $request->user();

        // If branch_id is NOT present in the request (even if null), 
        // fallback to user's branch. If it IS present and null, it means Global.
        $branchId = $request->has('branch_id') ? $request->branch_id : $user->branch_id;

        $category = Category::create([
            'tenant_id' => $user->tenant_id,
            'branch_id' => $branchId,
            'name' => $request->name,
            'slug' => Str::slug($request->name),
        ]);

        return response()->json($category, 201);
    }

    public function updateCategory(Request $request, Category $category)
    {
        $this->authorizeTenant($request, $category);

        $request->validate([
            'name' => 'required|string|max:255',
            'branch_id' => 'nullable|exists:branches,id',
        ]);

        $category->update([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'branch_id' => $request->has('branch_id') ? $request->branch_id : $category->branch_id,
        ]);

        return response()->json($category);
    }

    public function deleteCategory(Request $request, Category $category)
    {
        $this->authorizeTenant($request, $category);
        $category->delete();
        return response()->json(null, 204);
    }

    // Products
    public function getProducts(Request $request)
    {
        $user = $request->user();
        $query = Product::where('tenant_id', $user->tenant_id)->with(['category', 'stockRecord']);

        // Scope to branch if NOT owner/admin
        if (!$user->isOwnerOrAdmin()) {
            $query->where(function ($q) use ($user) {
                $q->where('branch_id', $user->branch_id)->orWhereNull('branch_id');
            });
        } elseif ($request->has('branch_id')) {
            $query->where(function ($q) use ($request) {
                $q->where('branch_id', $request->branch_id)
                    ->orWhereNull('branch_id');
            });
        }

        return $query->get();
    }

    public function storeProduct(Request $request)
    {
        $request->validate([
            'category_id' => 'nullable|exists:categories,id',
            'name' => 'required|string|max:255',
            'sku' => 'nullable|string|max:100',
            'price' => 'required|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'track_stock' => 'boolean',
            'branch_id' => 'nullable|exists:branches,id',
            'image' => 'nullable', // Allow string/null
        ]);

        $user = $request->user();
        $branchId = $request->has('branch_id') ? $request->branch_id : $user->branch_id;

        // Auto-SKU generation if empty
        $sku = $request->sku;
        if (empty($sku)) {
            $sku = 'PRD-' . strtoupper(Str::random(6));
        }

        $imagePath = null;
        if ($request->hasFile('image')) {
            $request->validate(['image' => 'image|max:1024']);
            $path = $this->imageService->convertToWebp($request->file('image'));
            if ($path) {
                $imagePath = asset('storage/' . $path);
            }
        }

        $product = Product::create([
            'tenant_id' => $user->tenant_id,
            'branch_id' => $branchId,
            'category_id' => $request->category_id,
            'name' => $request->name,
            'sku' => $sku,
            'price' => $request->price,
            'cost_price' => $request->cost_price ?? 0,
            'description' => $request->description,
            'image' => $imagePath,
            'track_stock' => $request->track_stock ?? false,
        ]);

        return response()->json($product, 201);
    }

    public function updateProduct(Request $request, Product $product)
    {
        $this->authorizeTenant($request, $product);

        $request->validate([
            'category_id' => 'nullable|exists:categories,id',
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'branch_id' => 'nullable|exists:branches,id',
            'image' => 'nullable', // Allow string/null
        ]);

        $data = $request->all();

        if ($request->hasFile('image')) {
            $request->validate(['image' => 'image|max:1024']);

            // Delete old image
            $this->imageService->deleteImage($product->image);

            $path = $this->imageService->convertToWebp($request->file('image'));
            if ($path) {
                $data['image'] = asset('storage/' . $path);
            }
        } elseif (array_key_exists('image', $data) && empty($data['image'])) {
            $this->imageService->deleteImage($product->image);
            $data['image'] = null;
        } elseif (isset($data['image']) && is_string($data['image'])) {
            unset($data['image']);
        }

        if (array_key_exists('sku', $data) && empty($data['sku'])) {
            unset($data['sku']);
        }

        $product->update($data);

        return response()->json($product);
    }

    public function deleteProduct(Request $request, Product $product)
    {
        $this->authorizeTenant($request, $product);

        // Delete image first
        $this->imageService->deleteImage($product->image);

        $product->delete();

        return response()->json(null, 204);
    }

    public function updateStock(Request $request, Product $product)
    {
        $this->authorizeTenant($request, $product);

        $request->validate([
            'stock' => 'required|numeric',
            'type' => 'required|in:set,add,subtract'
        ]);

        $stockRecord = ProductBranch::firstOrNew([
            'product_id' => $product->id,
        ]);

        if ($request->type == 'set') {
            $stockRecord->stock = $request->stock;
        } elseif ($request->type == 'add') {
            $stockRecord->stock += $request->stock;
        } else {
            if ($stockRecord->stock < $request->stock) {
                return response()->json(['message' => 'Stok tidak boleh kurang dari 0'], 422);
            }
            $stockRecord->stock -= $request->stock;
        }

        $stockRecord->save();

        return response()->json($stockRecord);
    }

    private function authorizeTenant(Request $request, $model)
    {
        if ($model->tenant_id != $request->user()->tenant_id) {
            abort(403, 'Unauthorized action for this tenant.');
        }
    }
}
