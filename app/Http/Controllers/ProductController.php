<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use App\Models\Unit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Product::query()
            ->with([
                'category:id,name,slug',
                'brand:id,name,slug',
                'unit:id,name,short_name,slug',
                'primaryMedia',
                'defaultVariant:id,product_id,name,cost_price,selling_price,wholesale_price,promotional_price,is_default,status',
            ])
            ->orderBy('sort_order')
            ->orderBy('name');

        $search = trim((string) $request->query('search', ''));

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('name', 'ilike', '%' . $search . '%')
                    ->orWhere('slug', 'ilike', '%' . $search . '%')
                    ->orWhere('sku', 'ilike', '%' . $search . '%')
                    ->orWhere('model_number', 'ilike', '%' . $search . '%')
                    ->orWhere('barcode', 'ilike', '%' . $search . '%');
            });
        }

        $categoryId = $request->query('category_id');

        if (is_numeric($categoryId)) {
            $query->where('category_id', (int) $categoryId);
        }

        $brandId = $request->query('brand_id');

        if (is_numeric($brandId)) {
            $query->where('brand_id', (int) $brandId);
        }

        $unitId = $request->query('unit_id');

        if (is_numeric($unitId)) {
            $query->where('unit_id', (int) $unitId);
        }

        $productType = $request->query('product_type');

        if (is_string($productType) && $productType !== '') {
            $query->where('product_type', $productType);
        }

        $status = $request->query('status');

        if (is_string($status) && $status !== '') {
            $query->where('status', $status);
        }

        $visibility = $request->query('visibility');

        if (is_string($visibility) && $visibility !== '') {
            $query->where('visibility', $visibility);
        }

        $products = $query->get([
            'id',
            'category_id',
            'brand_id',
            'unit_id',
            'name',
            'slug',
            'sku',
            'model_number',
            'barcode',
            'product_type',
            'short_description',
            'description',
            'status',
            'visibility',
            'sort_order',
            'created_at',
            'updated_at',
        ]);

        return response()->json([
            'data' => $products,
        ]);
    }

    public function show(Product $product): JsonResponse
    {
        $product->load([
            'category:id,name,slug',
            'brand:id,name,slug',
            'unit:id,name,short_name,slug',
            'media',
            'primaryMedia',
            'defaultVariant',
        ]);

        return response()->json([
            'data' => $product,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateProduct($request);

        $validated['slug'] = $this->resolveSlug(
            $validated['slug'] ?? null,
            $validated['name'],
        );

        // Variable products own identifiers at variant level; simple products
        // keep SKU/barcode at product level and use an identifier-free price record.
        $requestedSku = $this->normalizeNullableValue($validated['sku'] ?? null);
        $requestedBarcode = $this->normalizeNullableValue($validated['barcode'] ?? null);
        if (($validated['product_type'] ?? 'simple') === 'variable') {
            unset($validated['sku'], $validated['barcode']);
        } else {
            $validated['sku'] = $this->resolveSku($requestedSku);
            $validated['barcode'] = $requestedBarcode;
            $this->ensureBarcodeAvailable($validated['barcode']);
        }

        // Variable products may submit multiple variant drafts from the create form.
        $initialVariants = $validated['variants'] ?? [];
        unset($validated['variants']);

        // Backward compatibility for older API clients that submit one default
        // variant through the legacy top-level pricing fields.
        if (
            ($validated['product_type'] ?? 'simple') === 'variable'
            && $initialVariants === []
            && array_key_exists('selling_price', $validated)
        ) {
            $initialVariants = [[
                'name' => 'Standard',
                'sku' => $requestedSku,
                'barcode' => $requestedBarcode,
                'cost_price' => $validated['cost_price'] ?? 0,
                'selling_price' => $validated['selling_price'],
                'wholesale_price' => $validated['wholesale_price'] ?? null,
                'promotional_price' => $validated['promotional_price'] ?? null,
            ]];
        }

        if (($validated['product_type'] ?? 'simple') === 'variable') {
            $this->validateInitialVariantIdentifiers($initialVariants);
        }

        // Initial prices belong to the default variant, not the product row.
        $initialPricing = [];
        foreach (['cost_price', 'selling_price', 'wholesale_price', 'promotional_price'] as $priceField) {
            if (array_key_exists($priceField, $validated)) {
                $initialPricing[$priceField] = $validated[$priceField] === '' ? null : $validated[$priceField];
                unset($validated[$priceField]);
            }
        }
        // Images are media records, not columns on the products table.
        unset($validated['image'], $validated['images']);
        $uploadedFiles = array_values(array_filter(array_merge(
            [$request->file('image')],
            $request->file('images', [])
        )));
        if (count($uploadedFiles) > 10) {
            throw ValidationException::withMessages([
                'images' => 'A product can have up to 10 images.',
            ]);
        }

        $storedPaths = [];

        try {
            $product = DB::transaction(function () use (
                $validated,
                $uploadedFiles,
                $initialPricing,
                $initialVariants,
                &$storedPaths
            ) {
                $product = Product::create($validated);

                if ($product->product_type === 'variable') {
                    foreach (array_values($initialVariants) as $index => $variantData) {
                        $variantData['name'] = trim((string) $variantData['name']);
                        $variantData['sku'] = $this->normalizeNullableValue($variantData['sku'] ?? null);
                        $variantData['barcode'] = $this->normalizeNullableValue($variantData['barcode'] ?? null);
                        foreach (['wholesale_price', 'promotional_price'] as $optionalPrice) {
                            if (($variantData[$optionalPrice] ?? null) === '') {
                                $variantData[$optionalPrice] = null;
                            }
                        }
                        unset($variantData['is_default'], $variantData['status'], $variantData['sort_order']);
                        $product->variants()->create(array_merge($variantData, [
                            'is_default' => $index === 0,
                            'status' => 'active',
                            'sort_order' => $index,
                        ]));
                    }
                } elseif (array_key_exists('selling_price', $initialPricing)) {
                    $product->variants()->create(array_merge([
                        'name' => 'Standard',
                        'sku' => null,
                        'barcode' => null,
                        'cost_price' => 0,
                        'selling_price' => 0,
                        'is_default' => true,
                        'status' => 'active',
                        'sort_order' => 0,
                    ], array_intersect_key($initialPricing, array_flip(['cost_price', 'selling_price', 'wholesale_price', 'promotional_price']))));
                }

                foreach ($uploadedFiles as $index => $uploadedFile) {
                    $storedPath = $uploadedFile->store(
                        'products/' . $product->id . '/images',
                        'public'
                    );

                    if ($storedPath === false) {
                        throw new \RuntimeException(
                            'A product image could not be stored.'
                        );
                    }

                    $storedPaths[] = $storedPath;
                    $imageSize = @getimagesize($uploadedFile->getRealPath());

                    ProductMedia::create([
                        'product_id' => $product->id,
                        'disk' => 'public',
                        'path' => $storedPath,
                        'collection' => 'images',
                        'original_name' => $uploadedFile->getClientOriginalName(),
                        'mime_type' => $uploadedFile->getMimeType(),
                        'size' => $uploadedFile->getSize(),
                        'width' => $imageSize !== false ? ($imageSize[0] ?? null) : null,
                        'height' => $imageSize !== false ? ($imageSize[1] ?? null) : null,
                        'alt_text' => $product->name,
                        'sort_order' => $index,
                        'is_primary' => $index === 0,
                    ]);
                }

                return $product;
            });
        } catch (\Throwable $exception) {
            foreach ($storedPaths as $path) {
                Storage::disk('public')->delete($path);
            }

            throw $exception;
        }

        $product->load([
            'category:id,name,slug',
            'brand:id,name,slug',
            'unit:id,name,short_name,slug',
            'media',
            'primaryMedia',
        ]);

        return response()->json([
            'message' => 'Product created successfully.',
            'data' => $product,
        ], 201);
    }

    public function update(
        Request $request,
        Product $product
    ): JsonResponse {
        $validated = $this->validateProduct(
            $request,
            $product
        );

        $validated['slug'] = $this->resolveSlug(
            $validated['slug'] ?? null,
            $validated['name'],
            $product,
        );

        $validated['sku'] = $this->resolveSku(
            $validated['sku'] ?? null,
            $product,
        );

        $validated['barcode'] = $this->normalizeNullableValue(
            $validated['barcode'] ?? null,
        );

        $this->ensureBarcodeAvailable(
            $validated['barcode'],
            $product,
        );

        // Prices live on the default price record, not on the products table.
        $initialPricing = [];
        foreach (['cost_price', 'selling_price', 'wholesale_price', 'promotional_price'] as $priceField) {
            if (array_key_exists($priceField, $validated)) {
                $initialPricing[$priceField] = $validated[$priceField] === '' ? null : $validated[$priceField];
                unset($validated[$priceField]);
            }
        }

        // Keep image data out of the products table update.
        unset($validated['image'], $validated['images']);

        $uploadedFile = $request->file('image');
        $galleryFiles = array_values(array_filter($request->file('images', [])));
        $existingImageCount = $product->media()->where('collection', 'images')->count();
        if ($existingImageCount + count($galleryFiles) > 10) {
            throw ValidationException::withMessages([
                'images' => 'A product can have up to 10 images in total.',
            ]);
        }
        $storedPath = null;
        $additionalStoredPaths = [];
        $oldMediaDisk = null;
        $oldMediaPath = null;

        try {
            $product = DB::transaction(function () use (
                $product,
                $validated,
                $initialPricing,
                $uploadedFile,
                &$storedPath,
                &$oldMediaDisk,
                &$oldMediaPath,
                $galleryFiles,
                &$additionalStoredPaths
            ) {
                $product->update($validated);

                if (
                    $product->product_type === 'simple'
                    && array_key_exists('selling_price', $initialPricing)
                ) {
                    $priceRecord = $product->variants()
                        ->whereNull('deleted_at')
                        ->lockForUpdate()
                        ->first();

                    $priceData = array_merge([
                        'name' => 'Standard',
                        'sku' => null,
                        'barcode' => null,
                        'cost_price' => 0,
                        'selling_price' => 0,
                        'is_default' => true,
                        'status' => 'active',
                        'sort_order' => 0,
                    ], array_intersect_key(
                        $initialPricing,
                        array_flip(['cost_price', 'selling_price', 'wholesale_price', 'promotional_price'])
                    ));

                    if ($priceRecord) {
                        $priceRecord->update($priceData);
                    } else {
                        $product->variants()->create($priceData);
                    }
                }

                if ($uploadedFile !== null) {
                    // Store the replacement before changing the current
                    // primary media record.
                    $storedPath = $uploadedFile->store(
                        'products/' . $product->id . '/images',
                        'public'
                    );

                    if ($storedPath === false) {
                        throw new \RuntimeException(
                            'The replacement product image could not be stored.'
                        );
                    }

                    $imageSize = @getimagesize(
                        $uploadedFile->getRealPath()
                    );

                    // Lock the existing primary media row while replacing it.
                    $oldPrimaryMedia = ProductMedia::query()
                        ->where('product_id', $product->id)
                        ->where('collection', 'images')
                        ->where('is_primary', true)
                        ->lockForUpdate()
                        ->first();

                    if ($oldPrimaryMedia !== null) {
                        $oldMediaDisk = $oldPrimaryMedia->disk;
                        $oldMediaPath = $oldPrimaryMedia->path;

                        // Demote before inserting the new primary record
                        // to satisfy the PostgreSQL partial unique index.
                        $oldPrimaryMedia->is_primary = false;
                        $oldPrimaryMedia->save();

                        // Retain the historical row but exclude it from
                        // active media relationships.
                        $oldPrimaryMedia->delete();
                    }

                    ProductMedia::create([
                        'product_id' => $product->id,
                        'disk' => 'public',
                        'path' => $storedPath,
                        'collection' => 'images',
                        'original_name' => $uploadedFile->getClientOriginalName(),
                        'mime_type' => $uploadedFile->getMimeType(),
                        'size' => $uploadedFile->getSize(),
                        'width' => $imageSize !== false
                            ? ($imageSize[0] ?? null)
                            : null,
                        'height' => $imageSize !== false
                            ? ($imageSize[1] ?? null)
                            : null,
                        'alt_text' => $product->name,
                        'sort_order' => 0,
                        'is_primary' => true,
                    ]);
                }

                // Additional gallery uploads are appended without replacing
                // the existing primary image. The first image becomes primary
                // only when this product has no active primary image.
                $hasPrimary = ProductMedia::query()
                    ->where('product_id', $product->id)
                    ->where('collection', 'images')
                    ->where('is_primary', true)
                    ->exists();

                $nextOrder = (int) ProductMedia::query()
                    ->where('product_id', $product->id)
                    ->where('collection', 'images')
                    ->max('sort_order') + 1;

                foreach ($galleryFiles as $index => $galleryFile) {
                    $galleryPath = $galleryFile->store(
                        'products/' . $product->id . '/images',
                        'public'
                    );

                    if ($galleryPath === false) {
                        throw new \RuntimeException(
                            'A gallery image could not be stored.'
                        );
                    }

                    $additionalStoredPaths[] = $galleryPath;
                    $imageSize = @getimagesize($galleryFile->getRealPath());

                    ProductMedia::create([
                        'product_id' => $product->id,
                        'disk' => 'public',
                        'path' => $galleryPath,
                        'collection' => 'images',
                        'original_name' => $galleryFile->getClientOriginalName(),
                        'mime_type' => $galleryFile->getMimeType(),
                        'size' => $galleryFile->getSize(),
                        'width' => $imageSize !== false ? ($imageSize[0] ?? null) : null,
                        'height' => $imageSize !== false ? ($imageSize[1] ?? null) : null,
                        'alt_text' => $product->name,
                        'sort_order' => $nextOrder + $index,
                        'is_primary' => !$hasPrimary && $index === 0,
                    ]);
                }

                return $product;
            });
        } catch (\Throwable $exception) {
            // The DB transaction rolls back on failure. Remove only files
            // uploaded by this request; retain previous product media.
            if ($storedPath !== null) {
                Storage::disk('public')->delete($storedPath);
            }
            foreach ($additionalStoredPaths as $path) {
                Storage::disk('public')->delete($path);
            }

            throw $exception;
        }

        // The database replacement has committed. The old file can now
        // be removed without risking the active primary image.
        if (
            $uploadedFile !== null
            && $oldMediaDisk !== null
            && $oldMediaPath !== null
            && $oldMediaPath !== $storedPath
        ) {
            try {
                Storage::disk($oldMediaDisk)->delete($oldMediaPath);
            } catch (\Throwable $exception) {
                // Do not fail an otherwise successful product update if
                // physical cleanup fails. A cleanup job can handle orphans.
                report($exception);
            }
        }

        $product->refresh();

        $product->load([
            'category:id,name,slug',
            'brand:id,name,slug',
            'unit:id,name,short_name,slug',
            'media',
            'primaryMedia',
        ]);

        return response()->json([
            'message' => 'Product updated successfully.',
            'data' => $product,
        ]);
    }

    public function destroy(Product $product): JsonResponse
    {
        // Soft-delete the product only. Keep media records and files so
        // a future restore does not leave the product without its images.
        $product->delete();

        return response()->json([
            'message' => 'Product deleted successfully.',
        ]);
    }

    /**
     * Validate variant identifiers submitted with a new variable product.
     * Database unique indexes remain the final protection against races.
     */
    private function validateInitialVariantIdentifiers(array $variants): void
    {
        foreach (['sku', 'barcode'] as $field) {
            $seen = [];
            foreach ($variants as $index => $variant) {
                $value = $this->normalizeNullableValue($variant[$field] ?? null);
                if ($value === null) {
                    continue;
                }
                $normalized = mb_strtolower($value);
                if (isset($seen[$normalized])) {
                    throw ValidationException::withMessages([
                        "variants.$index.$field" => "Each variant must have a unique $field.",
                    ]);
                }
                $seen[$normalized] = true;

                $productExists = DB::table('products')
                    ->whereNull('deleted_at')
                    ->whereRaw("LOWER($field) = ?", [$normalized])
                    ->exists();
                $variantExists = DB::table('product_variants')
                    ->whereNull('deleted_at')
                    ->whereRaw("LOWER($field) = ?", [$normalized])
                    ->exists();
                if ($productExists || $variantExists) {
                    throw ValidationException::withMessages([
                        "variants.$index.$field" => "This $field is already in use.",
                    ]);
                }
            }
        }
    }

    private function validateProduct(
        Request $request,
        ?Product $product = null
    ): array {
        return $request->validate([
            'category_id' => [
                'required',
                'integer',
                Rule::exists('product_categories', 'id')
                    ->where(fn ($query) => $query->whereNull('deleted_at')),
            ],

            'brand_id' => [
                'nullable',
                'integer',
                Rule::exists('brands', 'id')
                    ->where(fn ($query) => $query->whereNull('deleted_at')),
            ],

            'unit_id' => [
                'required',
                'integer',
                Rule::exists('units', 'id')
                    ->where(fn ($query) => $query->whereNull('deleted_at')),
            ],

            'name' => [
                'required',
                'string',
                'max:200',
            ],

            'slug' => [
                'nullable',
                'string',
                'max:220',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
            ],

            'sku' => [
                'nullable',
                'string',
                'max:100',
                'required_if:product_type,simple',
            ],

            'model_number' => [
                'nullable',
                'string',
                'max:120',
            ],

            'barcode' => [
                'nullable',
                'string',
                'max:100',
            ],

            'cost_price' => ['sometimes', 'numeric', 'min:0'],
            'selling_price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'wholesale_price' => ['nullable', 'numeric', 'min:0'],
            'promotional_price' => ['nullable', 'numeric', 'min:0'],
            'variants' => ['sometimes', 'array', 'min:1', 'max:100'],
            'variants.*.name' => ['required', 'string', 'max:200'],
            'variants.*.sku' => ['nullable', 'string', 'max:100'],
            'variants.*.barcode' => ['nullable', 'string', 'max:100'],
            'variants.*.cost_price' => ['required', 'numeric', 'min:0'],
            'variants.*.selling_price' => ['required', 'numeric', 'min:0'],
            'variants.*.wholesale_price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.promotional_price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.is_default' => ['sometimes', 'boolean'],
            'variants.*.status' => ['sometimes', Rule::in(['active', 'inactive', 'archived'])],
            'variants.*.sort_order' => ['sometimes', 'integer', 'min:0', 'max:4294967295'],

            'product_type' => [
                'required',
                'string',
                Rule::in([
                    'simple',
                    'variable',
                ]),
            ],

            'short_description' => [
                'nullable',
                'string',
            ],

            'description' => [
                'nullable',
                'string',
            ],

            'status' => [
                'sometimes',
                'string',
                Rule::in([
                    'draft',
                    'active',
                    'inactive',
                    'archived',
                ]),
            ],

            'visibility' => [
                'sometimes',
                'string',
                Rule::in([
                    'visible',
                    'hidden',
                ]),
            ],

            'sort_order' => [
                'sometimes',
                'integer',
                'min:0',
                'max:4294967295',
            ],

            'image' => [
                'nullable',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],
            'images' => ['sometimes', 'array', 'max:10'],
            'images.*' => [
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],
        ]);
    }

    private function resolveSlug(
        ?string $slug,
        string $name,
        ?Product $product = null
    ): string {
        $baseSlug = Str::slug(trim($slug ?? ''));

        if ($baseSlug === '') {
            $baseSlug = Str::slug($name);
        }

        if ($baseSlug === '') {
            throw ValidationException::withMessages([
                'slug' => 'A valid slug could not be generated from the product name.',
            ]);
        }

        $candidate = $baseSlug;
        $counter = 2;

        while (
            Product::query()
                ->where('slug', $candidate)
                ->whereNull('deleted_at')
                ->when(
                    $product !== null,
                    fn ($query) => $query->where('id', '!=', $product->id),
                )
                ->exists()
        ) {
            $candidate = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $candidate;
    }

    private function resolveSku(
        ?string $sku,
        ?Product $product = null
    ): ?string {
        $sku = $this->normalizeNullableValue($sku);

        if ($sku === null) {
            return null;
        }

        $sku = strtoupper($sku);

        $exists = Product::query()
            ->whereRaw('LOWER(sku) = LOWER(?)', [$sku])
            ->whereNull('deleted_at')
            ->when(
                $product !== null,
                fn ($query) => $query->where('id', '!=', $product->id),
            )
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'sku' => 'The SKU has already been taken.',
            ]);
        }

        return $sku;
    }

    private function ensureBarcodeAvailable(
        ?string $barcode,
        ?Product $product = null
    ): void {
        if ($barcode === null) {
            return;
        }

        $exists = Product::query()
            ->whereRaw('LOWER(barcode) = LOWER(?)', [$barcode])
            ->whereNull('deleted_at')
            ->when(
                $product !== null,
                fn ($query) => $query->where('id', '!=', $product->id),
            )
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'barcode' => 'The barcode has already been taken.',
            ]);
        }
    }

    private function normalizeNullableValue(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
