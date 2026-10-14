<?php

namespace App\Http\Controllers;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductVariantController extends Controller
{
    /**
     * List variants belonging to a variable product.
     */
    public function index(Product $product): JsonResponse
    {
        $this->ensureVariableProduct($product);

        $variants = $product->variants()
            ->with([
                'attributeValues.attribute',
                'attributeValues.attributeValue',
            ])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return response()->json([
            'data' => $variants,
        ]);
    }

    /**
     * Create a variant for a variable product.
     */
    public function store(
        Request $request,
        Product $product
    ): JsonResponse {
        $this->ensureVariableProduct($product);

        $data = $this->validateVariant($request);

        $attributeValues = $data['attribute_values'] ?? [];

        unset($data['attribute_values']);

        $this->validatePromotionDateRange($data);
        $this->validateAttributeSelections($attributeValues);
        $this->assertIdentifiersAvailable($data);

        try {
            $variant = DB::transaction(function () use (
                $product,
                $data,
                $attributeValues
            ) {
                $lockedProduct = Product::query()
                    ->whereKey($product->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->ensureVariableProduct($lockedProduct);

                if (($data['is_default'] ?? false) === true) {
                    $lockedProduct->variants()
                        ->where('is_default', true)
                        ->update([
                            'is_default' => false,
                        ]);
                }

                $variant = $lockedProduct->variants()
                    ->create($data);

                $this->syncAttributeValues(
                    $variant,
                    $attributeValues
                );

                return $variant->load([
                    'attributeValues.attribute',
                    'attributeValues.attributeValue',
                ]);
            }, 3);
        } catch (QueryException $exception) {
            $this->handleIdentifierConstraint(
                $exception,
                $data
            );

            throw $exception;
        }

        return response()->json([
            'message' => 'Product variant created successfully.',
            'data' => $variant,
        ], 201);
    }

    /**
     * Show one variant belonging to the given product.
     */
    public function show(
        Product $product,
        int $variant
    ): JsonResponse {
        $this->ensureVariableProduct($product);

        $productVariant = $this->findVariant(
            $product,
            $variant
        );

        return response()->json([
            'data' => $productVariant->load([
                'attributeValues.attribute',
                'attributeValues.attributeValue',
            ]),
        ]);
    }

    /**
     * Update a variant.
     */
    public function update(
        Request $request,
        Product $product,
        int $variant
    ): JsonResponse {
        $this->ensureVariableProduct($product);

        $productVariant = $this->findVariant(
            $product,
            $variant
        );

        $data = $this->validateVariant(
            $request,
            true
        );

        $attributeValues = $data['attribute_values'] ?? null;

        unset($data['attribute_values']);

        if ($attributeValues !== null) {
            $this->validateAttributeSelections(
                $attributeValues
            );
        }

        $this->assertIdentifiersAvailable(
            $data,
            $productVariant
        );

        try {
            $productVariant = DB::transaction(function () use (
                $product,
                $productVariant,
                $data,
                $attributeValues
            ) {
                $lockedProduct = Product::query()
                    ->whereKey($product->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->ensureVariableProduct($lockedProduct);

                $lockedVariant = $lockedProduct->variants()
                    ->whereKey($productVariant->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                /*
                 * Validate against the current stored dates as well
                 * as any dates supplied in this partial update.
                 */
                $this->validatePromotionDateRange(
                    $data,
                    $lockedVariant
                );

                if (($data['is_default'] ?? false) === true) {
                    $lockedProduct->variants()
                        ->where('is_default', true)
                        ->where('id', '<>', $lockedVariant->id)
                        ->update([
                            'is_default' => false,
                        ]);
                }

                $lockedVariant->fill($data);
                $lockedVariant->save();

                if ($attributeValues !== null) {
                    $this->validateAttributeSelections(
                        $attributeValues
                    );

                    $this->syncAttributeValues(
                        $lockedVariant,
                        $attributeValues
                    );
                }

                return $lockedVariant->load([
                    'attributeValues.attribute',
                    'attributeValues.attributeValue',
                ]);
            }, 3);
        } catch (QueryException $exception) {
            $this->handleIdentifierConstraint(
                $exception,
                $data
            );

            throw $exception;
        }

        return response()->json([
            'message' => 'Product variant updated successfully.',
            'data' => $productVariant,
        ]);
    }

    /**
     * Soft-delete a variant.
     */
    public function destroy(
        Product $product,
        int $variant
    ): JsonResponse {
        $this->ensureVariableProduct($product);

        DB::transaction(function () use (
            $product,
            $variant
        ) {
            $lockedProduct = Product::query()
                ->whereKey($product->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensureVariableProduct($lockedProduct);

            $lockedVariant = $lockedProduct->variants()
                ->whereKey($variant)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedVariant->delete();
        }, 3);

        return response()->json([
            'message' => 'Product variant deleted successfully.',
        ]);
    }

    /**
     * Validate and normalize variant input.
     */
    private function validateVariant(
        Request $request,
        bool $isUpdate = false
    ): array {
        $rules = [
            'name' => [
                'nullable',
                'string',
                'max:200',
            ],
            'sku' => [
                'nullable',
                'string',
                'max:100',
            ],
            'barcode' => [
                'nullable',
                'string',
                'max:100',
            ],
            'cost_price' => [
                'sometimes',
                'numeric',
                'min:0',
            ],
            'selling_price' => [
                'sometimes',
                'numeric',
                'min:0',
            ],
            'wholesale_price' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'promotional_price' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'promotion_starts_at' => [
                'nullable',
                'date',
            ],
            'promotion_ends_at' => [
                'nullable',
                'date',
            ],
            'is_default' => [
                'sometimes',
                'boolean',
            ],
            'status' => [
                'sometimes',
                'in:active,inactive,archived',
            ],
            'sort_order' => [
                'sometimes',
                'integer',
                'min:0',
                'max:4294967295',
            ],
            'attribute_values' => [
                'sometimes',
                'array',
                'max:50',
            ],
            'attribute_values.*.attribute_id' => [
                'required',
                'integer',
                'distinct',
            ],
            'attribute_values.*.attribute_value_id' => [
                'required',
                'integer',
            ],
        ];

        $data = $request->validate($rules);

        foreach (['sku', 'barcode', 'name'] as $field) {
            if (
                array_key_exists($field, $data)
                && $data[$field] !== null
            ) {
                $data[$field] = trim($data[$field]);

                if ($data[$field] === '') {
                    $data[$field] = null;
                }
            }
        }

        foreach ([
            'wholesale_price',
            'promotional_price',
            'promotion_starts_at',
            'promotion_ends_at',
        ] as $field) {
            if (
                array_key_exists($field, $data)
                && $data[$field] === ''
            ) {
                $data[$field] = null;
            }
        }

        if (array_key_exists('is_default', $data)) {
            $data['is_default'] = filter_var(
                $data['is_default'],
                FILTER_VALIDATE_BOOLEAN
            );
        }

        /*
         * On creation, the database defaults cost_price and
         * selling_price when the request omits them.
         *
         * Partial updates intentionally preserve omitted fields.
         */
        return $data;
    }

    /**
     * Validate effective promotion dates.
     *
     * On update, omitted fields are taken from the existing variant.
     */
    private function validatePromotionDateRange(
        array $data,
        ?ProductVariant $variant = null
    ): void {
        $start = array_key_exists(
            'promotion_starts_at',
            $data
        )
            ? $data['promotion_starts_at']
            : $variant?->promotion_starts_at;

        $end = array_key_exists(
            'promotion_ends_at',
            $data
        )
            ? $data['promotion_ends_at']
            : $variant?->promotion_ends_at;

        $start = $this->normalizeDateValue($start);
        $end = $this->normalizeDateValue($end);

        if ($start === null || $end === null) {
            return;
        }

        if (Carbon::parse($end)->lt(Carbon::parse($start))) {
            throw ValidationException::withMessages([
                'promotion_ends_at' =>
                    'The promotion end date must be on or after the start date.',
            ]);
        }
    }

    /**
     * Normalize an optional date to a Carbon instance or null.
     */
    private function normalizeDateValue(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value;
        }

        return Carbon::parse($value);
    }

    /**
     * Validate that every selected attribute and value is usable.
     */
    private function validateAttributeSelections(
        array $selections
    ): void {
        $errors = [];

        foreach ($selections as $index => $selection) {
            $attribute = Attribute::query()
                ->whereKey($selection['attribute_id'])
                ->where('status', 'active')
                ->first();

            if (! $attribute) {
                $errors["attribute_values.$index.attribute_id"] =
                    'The selected attribute is invalid or inactive.';

                continue;
            }

            if (! $attribute->isSelectable()) {
                $errors["attribute_values.$index.attribute_id"] =
                    'Only selectable attributes can be assigned to variants.';

                continue;
            }

            $value = AttributeValue::query()
                ->whereKey($selection['attribute_value_id'])
                ->where('attribute_id', $attribute->id)
                ->where('status', 'active')
                ->first();

            if (! $value) {
                $errors["attribute_values.$index.attribute_value_id"] =
                    'The value must be active and belong to the selected attribute.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Replace the variant's attribute selections.
     *
     * Call this method inside a database transaction.
     */
    private function syncAttributeValues(
        ProductVariant $variant,
        array $selections
    ): void {
        $variant->attributeValues()->delete();

        foreach ($selections as $selection) {
            $variant->attributeValues()->create([
                'attribute_id' => $selection['attribute_id'],
                'attribute_value_id' => $selection['attribute_value_id'],
            ]);
        }
    }

    /**
     * Check active product and variant identifiers before writing.
     *
     * Database unique constraints remain the final protection
     * against concurrent duplicate writes.
     */
    private function assertIdentifiersAvailable(
        array $data,
        ?ProductVariant $ignoreVariant = null
    ): void {
        foreach (['sku', 'barcode'] as $field) {
            if (
                ! array_key_exists($field, $data)
                || $data[$field] === null
            ) {
                continue;
            }

            $value = trim((string) $data[$field]);

            if ($value === '') {
                continue;
            }

            $normalized = mb_strtolower($value);

            $productExists = DB::table('products')
                ->whereNull('deleted_at')
                ->whereRaw("LOWER($field) = ?", [$normalized])
                ->exists();

            $variantQuery = DB::table('product_variants')
                ->whereNull('deleted_at')
                ->whereRaw("LOWER($field) = ?", [$normalized]);

            if ($ignoreVariant !== null) {
                $variantQuery->where(
                    'id',
                    '<>',
                    $ignoreVariant->id
                );
            }

            if ($productExists || $variantQuery->exists()) {
                throw ValidationException::withMessages([
                    $field => "This $field is already in use.",
                ]);
            }
        }
    }

    /**
     * Translate known PostgreSQL identifier conflicts into
     * field-specific validation errors.
     *
     * Unknown unique violations are deliberately rethrown.
     */
    private function handleIdentifierConstraint(
        QueryException $exception,
        array $data
    ): void {
        $sqlState = $exception->errorInfo[0] ?? null;

        if ($sqlState !== '23505') {
            return;
        }

        $message = mb_strtolower($exception->getMessage());

        $constraint = mb_strtolower(
            (string) ($exception->errorInfo[2] ?? '')
        );

        $field = null;

        /*
         * The shared identifier registry can report the conflicting
         * identifier type in PostgreSQL's duplicate-key detail.
         */
        if (
            preg_match(
                '/key\s*\(\s*identifier_type\s*,\s*normalized_value\s*\)\s*=\s*\(\s*(sku|barcode)\s*,/i',
                $message,
                $matches
            ) === 1
        ) {
            $field = mb_strtolower($matches[1]);
        }

        /*
         * Handle constraints that directly identify the field.
         */
        if ($field === null) {
            if (
                str_contains($constraint, 'barcode')
                || preg_match(
                    '/key\s*\(\s*barcode\s*\)\s*=/i',
                    $message
                ) === 1
            ) {
                $field = 'barcode';
            } elseif (
                str_contains($constraint, 'sku')
                || preg_match(
                    '/key\s*\(\s*sku\s*\)\s*=/i',
                    $message
                ) === 1
            ) {
                $field = 'sku';
            }
        }

        /*
         * If a registry conflict cannot identify the field from
         * PostgreSQL's detail, map it only when exactly one identifier
         * was submitted. Never guess when both were supplied.
         */
        if (
            $field === null
            && str_contains($message, 'product_identifiers_value_unique_active')
        ) {
            $submitted = [];

            foreach (['sku', 'barcode'] as $candidate) {
                if (
                    isset($data[$candidate])
                    && trim((string) $data[$candidate]) !== ''
                ) {
                    $submitted[] = $candidate;
                }
            }

            if (count($submitted) === 1) {
                $field = $submitted[0];
            }
        }

        if ($field === null) {
            return;
        }

        throw ValidationException::withMessages([
            $field => sprintf(
                'This %s may already be in use. Please verify it and try again.',
                $field
            ),
        ]);
    }

    /**
     * Ensure that this product supports variants.
     */
    private function ensureVariableProduct(Product $product): void
    {
        if ($product->product_type !== 'variable') {
            throw ValidationException::withMessages([
                'product_type' =>
                    'Variants can only be managed for variable products.',
            ]);
        }
    }

    /**
     * Find a variant only within its parent product.
     *
     * Soft-deleted variants are excluded by the model's
     * SoftDeletes global scope.
     */
    private function findVariant(
        Product $product,
        int $variant
    ): ProductVariant {
        return $product->variants()
            ->whereKey($variant)
            ->firstOrFail();
    }
}
