<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductMedia;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
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
                'defaultVariant:id,product_id,name,sku,barcode,cost_price,selling_price,wholesale_price,promotional_price,is_default,status',
            ])
            ->orderBy('sort_order')
            ->orderBy('name');

        $search = trim((string) $request->query('search', ''));

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('name', 'ilike', '%'.$search.'%')
                    ->orWhere('slug', 'ilike', '%'.$search.'%')
                    ->orWhere('sku', 'ilike', '%'.$search.'%')
                    ->orWhere('model_number', 'ilike', '%'.$search.'%')
                    ->orWhere('barcode', 'ilike', '%'.$search.'%')
                    ->orWhereHas('variants', function ($variantQuery) use ($search): void {
                        $variantQuery
                            ->where('sku', 'ilike', '%'.$search.'%')
                            ->orWhere('barcode', 'ilike', '%'.$search.'%');
                    });
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
        $variantDrafts = $this->normalizeVariantDrafts(
            $validated['variants'] ?? [],
            $validated['product_type'],
        );
        unset($validated['variants']);

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
                $variantDrafts,
                &$storedPaths
            ) {
                $product = Product::create($validated);

                if ($product->product_type === 'simple' && array_key_exists('selling_price', $initialPricing)) {
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

                foreach ($variantDrafts as $variantDraft) {
                    $product->variants()->create($variantDraft);
                }

                foreach ($uploadedFiles as $index => $uploadedFile) {
                    $storedPath = $uploadedFile->store(
                        'products/'.$product->id.'/images',
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
                        'original_name' => mb_substr($uploadedFile->getClientOriginalName(), 0, 255),
                        'mime_type' => $uploadedFile->getMimeType(),
                        'size' => $uploadedFile->getSize(),
                        'width' => $imageSize !== false ? $imageSize[0] : null,
                        'height' => $imageSize !== false ? $imageSize[1] : null,
                        'alt_text' => $product->name,
                        'sort_order' => $index,
                        'is_primary' => $index === 0,
                    ]);
                }

                return $product;
            });
        } catch (QueryException $exception) {
            foreach ($storedPaths as $path) {
                Storage::disk('public')->delete($path);
            }

            $this->handleProductCreationConstraint($exception, $variantDrafts);

            throw $exception;
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

        if (
            $product->product_type === 'variable'
            && $validated['product_type'] === 'simple'
            && $product->variants()->exists()
        ) {
            throw ValidationException::withMessages([
                'product_type' => 'A variable product with existing variants cannot be changed to a simple product. Remove its active variants first.',
            ]);
        }

        $validated['slug'] = $this->resolveSlug(
            $validated['slug'] ?? null,
            $validated['name'],
            $product,
        );

        if ($validated['product_type'] === 'variable') {
            // Variable products own identifiers at variant level only.
            $validated['sku'] = null;
            $validated['barcode'] = null;
        } else {
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
        }

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
        $galleryFiles = $request->file('images', []);
        if ($galleryFiles instanceof UploadedFile) {
            $galleryFiles = [$galleryFiles];
        }
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
                        'products/'.$product->id.'/images',
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
                        'original_name' => mb_substr($uploadedFile->getClientOriginalName(), 0, 255),
                        'mime_type' => $uploadedFile->getMimeType(),
                        'size' => $uploadedFile->getSize(),
                        'width' => $imageSize !== false
                            ? $imageSize[0]
                            : null,
                        'height' => $imageSize !== false
                            ? $imageSize[1]
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
                        'products/'.$product->id.'/images',
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
                        'original_name' => mb_substr($galleryFile->getClientOriginalName(), 0, 255),
                        'mime_type' => $galleryFile->getMimeType(),
                        'size' => $galleryFile->getSize(),
                        'width' => $imageSize !== false ? $imageSize[0] : null,
                        'height' => $imageSize !== false ? $imageSize[1] : null,
                        'alt_text' => $product->name,
                        'sort_order' => $nextOrder + $index,
                        'is_primary' => ! $hasPrimary && $index === 0,
                    ]);
                }

                return $product;
            });
        } catch (QueryException $exception) {
            // The DB transaction rolls back on failure. Remove only files
            // uploaded by this request; retain previous product media.
            if (is_string($storedPath)) {
                Storage::disk('public')->delete($storedPath);
            }
            foreach ($additionalStoredPaths as $path) {
                Storage::disk('public')->delete($path);
            }

            $this->handleProductCreationConstraint($exception, []);

            throw $exception;
        } catch (\Throwable $exception) {
            // The DB transaction rolls back on failure. Remove only files
            // uploaded by this request; retain previous product media.
            if (is_string($storedPath)) {
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
     * @return array<string, mixed>
     */
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

            'variants' => $product === null && $request->input('product_type') === 'variable'
                ? ['required', 'array', 'min:1', 'max:100']
                : ['prohibited'],
            'variants.*.name' => ['required', 'string', 'max:200'],
            'variants.*.sku' => ['nullable', 'string', 'max:100'],
            'variants.*.barcode' => ['nullable', 'string', 'max:100'],
            'variants.*.cost_price' => ['sometimes', 'numeric', 'min:0'],
            'variants.*.selling_price' => ['required', 'numeric', 'min:0'],
            'variants.*.wholesale_price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.promotional_price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.is_default' => ['sometimes', 'boolean'],
            'variants.*.status' => ['sometimes', Rule::in(['active', 'inactive', 'archived'])],
            'variants.*.sort_order' => ['sometimes', 'integer', 'min:0', 'max:4294967295'],

            'image' => [
                'nullable',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
                'dimensions:max_width=5000,max_height=5000',
            ],
            'images' => ['sometimes', 'array', 'max:10'],
            'images.*' => [
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
                'dimensions:max_width=5000,max_height=5000',
            ],
        ]);
    }

    /**
     * Normalize variable-product variant drafts and enforce their batch invariants.
     *
     * @param  array<int, array<string, mixed>>  $variants
     * @return list<array<string, mixed>>
     */
    private function normalizeVariantDrafts(array $variants, string $productType): array
    {
        if ($productType !== 'variable') {
            return [];
        }

        $normalized = [];
        $defaultIndex = null;
        $activeIndexes = [];
        $seenIdentifiers = ['sku' => [], 'barcode' => []];

        foreach ($variants as $index => $variant) {
            $normalizedIndex = count($normalized);
            $status = $variant['status'] ?? 'active';
            $isDefault = filter_var(
                $variant['is_default'] ?? false,
                FILTER_VALIDATE_BOOLEAN,
            );

            if ($status === 'active') {
                $activeIndexes[] = $normalizedIndex;
            }

            if ($isDefault) {
                if ($defaultIndex !== null) {
                    throw ValidationException::withMessages([
                        "variants.$index.is_default" => 'Only one variant can be selected as the default.',
                    ]);
                }

                if ($status !== 'active') {
                    throw ValidationException::withMessages([
                        "variants.$index.is_default" => 'Only an active variant can be selected as the default.',
                    ]);
                }

                $defaultIndex = $normalizedIndex;
            }

            $normalizedVariant = [
                'name' => trim((string) $variant['name']),
                'sku' => $this->normalizeNullableValue(isset($variant['sku']) ? (string) $variant['sku'] : null),
                'barcode' => $this->normalizeNullableValue(isset($variant['barcode']) ? (string) $variant['barcode'] : null),
                'cost_price' => $variant['cost_price'] ?? 0,
                'selling_price' => $variant['selling_price'],
                'wholesale_price' => $this->normalizeNullableValue(isset($variant['wholesale_price']) ? (string) $variant['wholesale_price'] : null),
                'promotional_price' => $this->normalizeNullableValue(isset($variant['promotional_price']) ? (string) $variant['promotional_price'] : null),
                'is_default' => $isDefault,
                'status' => $status,
                'sort_order' => $variant['sort_order'] ?? $index,
            ];

            foreach (['sku', 'barcode'] as $identifierType) {
                $identifier = $normalizedVariant[$identifierType];

                if ($identifier === null) {
                    continue;
                }

                $normalizedIdentifier = mb_strtolower($identifier);

                if (isset($seenIdentifiers[$identifierType][$normalizedIdentifier])) {
                    throw ValidationException::withMessages([
                        "variants.$index.$identifierType" => "This $identifierType is duplicated in the submitted variants.",
                    ]);
                }

                $seenIdentifiers[$identifierType][$normalizedIdentifier] = $index;
            }

            if ($normalizedVariant['sku'] !== null) {
                $normalizedVariant['sku'] = mb_strtoupper($normalizedVariant['sku']);
            }

            $normalized[] = $normalizedVariant;
        }

        $submittedIdentifiers = array_filter(
            $seenIdentifiers,
            static fn (array $identifiers): bool => $identifiers !== [],
        );

        if ($submittedIdentifiers !== []) {
            $existingIdentifiers = DB::table('product_identifiers')
                ->whereNull('deleted_at')
                ->where(function ($query) use ($submittedIdentifiers): void {
                    foreach ($submittedIdentifiers as $identifierType => $identifiers) {
                        $query->orWhere(function ($identifierQuery) use ($identifierType, $identifiers): void {
                            $identifierQuery
                                ->where('identifier_type', $identifierType)
                                ->whereIn('normalized_value', array_keys($identifiers));
                        });
                    }
                })
                ->get(['identifier_type', 'normalized_value']);

            foreach ($existingIdentifiers as $existingIdentifier) {
                $identifierType = (string) $existingIdentifier->identifier_type;
                $normalizedValue = (string) $existingIdentifier->normalized_value;
                $variantIndex = $seenIdentifiers[$identifierType][$normalizedValue];

                throw ValidationException::withMessages([
                    "variants.$variantIndex.$identifierType" => "This $identifierType is already in use.",
                ]);
            }
        }

        if ($activeIndexes === []) {
            throw ValidationException::withMessages([
                'variants' => 'A variable product must have at least one active variant.',
            ]);
        }

        if ($defaultIndex === null) {
            $defaultIndex = $activeIndexes[0];
            $normalized[$defaultIndex]['is_default'] = true;
        }

        return $normalized;
    }

    /**
     * Translate a PostgreSQL global identifier conflict into a variant field error.
     *
     * @param  list<array<string, mixed>>  $variants
     */
    private function handleProductCreationConstraint(
        QueryException $exception,
        array $variants,
    ): void {
        if (($exception->errorInfo[0] ?? null) !== '23505') {
            return;
        }

        $message = mb_strtolower($exception->getMessage());

        if (str_contains($message, 'products_slug_unique_active')) {
            throw ValidationException::withMessages([
                'slug' => 'The product slug is already in use.',
            ]);
        }

        foreach (['sku', 'barcode'] as $field) {
            if (str_contains($message, "products_{$field}_unique_active")) {
                throw ValidationException::withMessages([
                    $field => "This $field is already in use.",
                ]);
            }

            if (str_contains($message, "product_variants_{$field}_unique_active")) {
                foreach ($variants as $index => $variant) {
                    if (! empty($variant[$field])) {
                        throw ValidationException::withMessages([
                            "variants.$index.$field" => "This $field is already in use.",
                        ]);
                    }
                }

                throw ValidationException::withMessages([
                    'variants' => "A submitted variant contains a duplicate $field.",
                ]);
            }
        }

        if (! str_contains($message, 'product_identifiers_value_unique_active')) {
            return;
        }

        if (preg_match(
            '/key\s*\(\s*identifier_type\s*,\s*normalized_value\s*\)\s*=\s*\(\s*(sku|barcode)\s*,\s*([^\)]+)\)/i',
            $message,
            $matches,
        ) !== 1) {
            return;
        }

        $field = mb_strtolower($matches[1]);
        $value = trim($matches[2], " \"'");

        foreach ($variants as $index => $variant) {
            if (mb_strtolower((string) ($variant[$field] ?? '')) === $value) {
                throw ValidationException::withMessages([
                    "variants.$index.$field" => "This $field is already in use.",
                ]);
            }
        }

        if ($variants === []) {
            throw ValidationException::withMessages([
                $field => "This $field is already in use.",
            ]);
        }

        throw ValidationException::withMessages([
            'variants' => "A submitted variant contains a duplicate $field.",
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
            $candidate = $baseSlug.'-'.$counter;
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

        $identifierExists = DB::table('product_identifiers')
            ->where('identifier_type', 'sku')
            ->where('normalized_value', mb_strtolower($sku))
            ->whereNull('deleted_at')
            ->when(
                $product !== null,
                fn ($query) => $query->where(function ($ownerQuery) use ($product): void {
                    $ownerQuery
                        ->whereNull('owner_product_id')
                        ->orWhere('owner_product_id', '!=', $product->id);
                }),
            )
            ->exists();

        if ($exists || $identifierExists) {
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

        $identifierExists = DB::table('product_identifiers')
            ->where('identifier_type', 'barcode')
            ->where('normalized_value', mb_strtolower($barcode))
            ->whereNull('deleted_at')
            ->when(
                $product !== null,
                fn ($query) => $query->where(function ($ownerQuery) use ($product): void {
                    $ownerQuery
                        ->whereNull('owner_product_id')
                        ->orWhere('owner_product_id', '!=', $product->id);
                }),
            )
            ->exists();

        if ($exists || $identifierExists) {
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
