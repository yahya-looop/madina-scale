<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\FinancialAccount;
use App\Models\GoodsReceipt;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderRevision;
use App\Models\PurchaseRequisition;
use App\Models\Supplier;
use App\Models\SupplierContact;
use App\Models\SupplierDocument;
use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;
use App\Models\SupplierReturn;
use App\Services\ActivityLogService;
use App\Services\LocationInventoryAccessService;
use App\Services\ProcurementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProcurementController extends Controller
{
    public function __construct(
        private readonly ProcurementService $procurement,
        private readonly LocationInventoryAccessService $locationAccess,
        private readonly ActivityLogService $activityLog,
    ) {}

    public function overview(Request $request): JsonResponse
    {
        $user = $request->user();
        $locations = $this->locationAccess->viewableLocations($user);
        $locationIds = $locations->pluck('id')->all();
        $payableAccountId = FinancialAccount::query()->where('code', '2000')->value('id');
        $supplierBalances = $payableAccountId
            ? DB::table('journal_entry_lines')
                ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
                ->where('journal_entries.status', 'posted')
                ->where('journal_entry_lines.account_id', $payableAccountId)
                ->whereNotNull('journal_entry_lines.supplier_id')
                ->whereIn('journal_entry_lines.location_id', $locationIds)
                ->groupBy('journal_entry_lines.supplier_id')
                ->selectRaw('journal_entry_lines.supplier_id, SUM(journal_entry_lines.credit - journal_entry_lines.debit) AS balance')
                ->get()
            : collect();
        $outstandingPayable = (string) DB::table('journal_entry_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->where('journal_entries.status', 'posted')
            ->where('journal_entry_lines.account_id', $payableAccountId)
            ->whereNotNull('journal_entry_lines.supplier_id')
            ->whereIn('journal_entry_lines.location_id', $locationIds)
            ->selectRaw('COALESCE(SUM(journal_entry_lines.credit - journal_entry_lines.debit), 0) AS balance')
            ->value('balance');

        return response()->json([
            'suppliers' => Supplier::query()
                ->withCount(['purchaseOrders' => fn ($query) => $query->whereIn('location_id', $locationIds)])
                ->orderBy('name')
                ->get(),
            'requisitions' => PurchaseRequisition::query()->with(['location', 'items'])->whereIn('location_id', $locationIds)->latest()->limit(100)->get(),
            'purchase_orders' => PurchaseOrder::query()->with(['supplier', 'location', 'items.product:id,name,sku', 'items.variant:id,product_id,name,sku', 'items.variant.product:id,name', 'revisions.creator:id,name', 'revisions.reviewer:id,name'])->whereIn('location_id', $locationIds)->latest()->limit(100)->get(),
            'goods_receipts' => GoodsReceipt::query()->with(['items'])->whereIn('location_id', $locationIds)->latest()->limit(100)->get(),
            'invoices' => SupplierInvoice::query()->with(['supplier', 'purchaseOrder', 'items'])->whereHas('purchaseOrder', fn ($query) => $query->whereIn('location_id', $locationIds))->latest()->limit(100)->get(),
            'payments' => SupplierPayment::query()->with(['supplierInvoice'])->whereIn('location_id', $locationIds)->latest()->limit(100)->get(),
            'returns' => SupplierReturn::query()->with(['items'])->whereIn('location_id', $locationIds)->latest()->limit(100)->get(),
            'locations' => $locations,
            'products' => Product::query()->where('status', 'active')->where('product_type', 'simple')->orderBy('name')->get(['id', 'name', 'sku']),
            'variants' => ProductVariant::query()->where('status', 'active')->with('product:id,name')->orderBy('sku')->get(['id', 'product_id', 'name', 'sku']),
            'cash_accounts' => FinancialAccount::query()->where('is_active', true)->where('is_cash_bank', true)->orderBy('code')->get(['id', 'code', 'name']),
            'supplier_balances' => $supplierBalances,
            'summary' => [
                'active_suppliers' => Supplier::query()->where('status', 'active')->count(),
                'open_purchase_orders' => PurchaseOrder::query()->whereIn('location_id', $locationIds)->whereIn('status', ['approved', 'partially_received'])->count(),
                'unpaid_invoices' => SupplierInvoice::query()->whereHas('purchaseOrder', fn ($query) => $query->whereIn('location_id', $locationIds))->whereIn('status', ['posted', 'partially_paid'])->count(),
                'outstanding_payable' => $outstandingPayable,
            ],
        ]);
    }

    public function showSupplier(Request $request, Supplier $supplier): JsonResponse
    {
        $locationIds = $this->locationAccess->viewableLocations($request->user())->pluck('id')->all();
        $orders = $supplier->purchaseOrders()->with('location:id,name')->whereIn('location_id', $locationIds)->latest('order_date')->limit(20)->get();
        $invoicesCount = $supplier->invoices()->whereHas('purchaseOrder', fn ($query) => $query->whereIn('location_id', $locationIds))->count();
        $supplier->load(['contacts', 'documents']);
        $supplierData = $supplier->toArray();
        $supplierData['purchase_orders_count'] = $supplier->purchaseOrders()->whereIn('location_id', $locationIds)->count();
        $supplierData['invoices_count'] = $invoicesCount;
        $supplierData['purchase_orders'] = $orders->map(fn (PurchaseOrder $order): array => [
            'id' => $order->id, 'po_number' => $order->po_number, 'order_date' => $order->order_date->toDateString(),
            'status' => $order->status, 'grand_total' => $order->grand_total, 'location' => $order->location?->name,
        ])->values();
        $supplierData['documents'] = $supplier->documents->map(fn (SupplierDocument $document): array => [
            'id' => $document->id, 'category' => $document->category, 'original_name' => $document->original_name,
            'mime_type' => $document->mime_type, 'size' => $document->size,
            'expires_at' => $document->expires_at?->toDateString(), 'created_at' => $document->created_at?->toISOString(),
        ])->values();
        $supplierData['activity'] = ActivityLog::query()->where('subject_type', $supplier->getMorphClass())
            ->where('subject_id', $supplier->id)->latest('created_at')->limit(15)->get(['id', 'action', 'description', 'created_at']);

        return response()->json(['supplier' => $supplierData]);
    }

    public function exportSuppliers(Request $request): StreamedResponse
    {
        $suppliers = Supplier::query()->withCount('purchaseOrders')->orderBy('name')->get();
        $filename = 'suppliers-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($suppliers): void {
            $output = fopen('php://output', 'w');
            if ($output === false) {
                return;
            }
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['Supplier Code', 'Name', 'Legal Name', 'Email', 'Phone', 'Tax Number', 'City', 'Country', 'Payment Terms', 'Credit Days', 'Status', 'Purchase Orders']);
            foreach ($suppliers as $supplier) {
                fputcsv($output, [$supplier->supplier_code, $supplier->name, $supplier->legal_name, $supplier->email, $supplier->phone, $supplier->tax_number, $supplier->city, $supplier->country, $supplier->payment_terms, $supplier->credit_days, $supplier->status, $supplier->purchase_orders_count]);
            }
            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function storeSupplierContact(Request $request, Supplier $supplier): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'designation' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:180'],
            'phone' => ['nullable', 'string', 'max:40'],
            'is_primary' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $data['created_by'] = $request->user()->id;

        $contact = DB::transaction(function () use ($supplier, $data): SupplierContact {
            Supplier::query()->whereKey($supplier->id)->lockForUpdate()->firstOrFail();
            if (($data['is_primary'] ?? false) === true) {
                $supplier->contacts()->update(['is_primary' => false]);
            }

            return $supplier->contacts()->create($data);
        });
        $this->activityLog->recordForUser($request->user(), 'created', 'procurement', 'Added supplier contact for '.$supplier->supplier_code, $contact);

        return response()->json(['contact' => $contact], 201);
    }

    public function updateSupplierContact(Request $request, Supplier $supplier, SupplierContact $contact): JsonResponse
    {
        abort_unless($contact->supplier_id === $supplier->id, 404);
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:160'],
            'designation' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:180'],
            'phone' => ['nullable', 'string', 'max:40'],
            'is_primary' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $oldValues = $contact->only(array_keys($data));
        DB::transaction(function () use ($supplier, $contact, $data): void {
            Supplier::query()->whereKey($supplier->id)->lockForUpdate()->firstOrFail();
            if (($data['is_primary'] ?? false) === true) {
                $supplier->contacts()->where('id', '<>', $contact->id)->update(['is_primary' => false]);
            }
            $contact->update($data);
        });
        $this->activityLog->recordForUser($request->user(), 'updated', 'procurement', 'Updated supplier contact for '.$supplier->supplier_code, $contact, $oldValues, $contact->only(array_keys($data)));

        return response()->json(['contact' => $contact->fresh()]);
    }

    public function deleteSupplierContact(Request $request, Supplier $supplier, SupplierContact $contact): JsonResponse
    {
        abort_unless($contact->supplier_id === $supplier->id, 404);
        $contact->delete();
        $this->activityLog->recordForUser($request->user(), 'deleted', 'procurement', 'Deleted supplier contact for '.$supplier->supplier_code, $contact);

        return response()->json(['message' => 'Supplier contact deleted.']);
    }

    public function storeSupplierDocument(Request $request, Supplier $supplier): JsonResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
            'category' => ['nullable', Rule::in(['trade_license', 'tax_certificate', 'bank_document', 'agreement', 'identity', 'other'])],
            'expires_at' => ['nullable', 'date'],
        ]);
        $file = $request->file('file');
        abort_unless($file instanceof UploadedFile, 422, 'A valid supplier document is required.');
        $path = $file->store('procurement/suppliers/'.$supplier->id, 'local');
        abort_if($path === false, 500, 'The supplier document could not be stored.');

        try {
            $document = $supplier->documents()->create([
                'category' => $data['category'] ?? 'other',
                'disk' => 'local',
                'path' => $path,
                'original_name' => mb_substr(basename($file->getClientOriginalName()), 0, 255),
                'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
                'size' => (int) $file->getSize(),
                'expires_at' => $data['expires_at'] ?? null,
                'uploaded_by' => $request->user()->id,
            ]);
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }
        $this->activityLog->recordForUser($request->user(), 'created', 'procurement', 'Uploaded supplier document for '.$supplier->supplier_code, $document);

        return response()->json(['document' => [
            'id' => $document->id,
            'category' => $document->category,
            'original_name' => $document->original_name,
            'mime_type' => $document->mime_type,
            'size' => $document->size,
            'expires_at' => $document->expires_at?->toDateString(),
            'created_at' => $document->created_at?->toISOString(),
        ]], 201);
    }

    public function downloadSupplierDocument(Supplier $supplier, SupplierDocument $document): StreamedResponse
    {
        abort_unless($document->supplier_id === $supplier->id, 404);
        abort_unless(
            $document->disk === 'local'
            && str_starts_with($document->path, 'procurement/suppliers/'.$supplier->id.'/')
            && ! str_contains($document->path, '..')
            && ! str_contains($document->path, '\\'),
            404,
        );
        abort_unless(Storage::disk('local')->exists($document->path), 404, 'Supplier document not found.');

        return Storage::disk('local')->download($document->path, $document->original_name);
    }

    public function deleteSupplierDocument(Request $request, Supplier $supplier, SupplierDocument $document): JsonResponse
    {
        abort_unless($document->supplier_id === $supplier->id, 404);
        abort_unless(
            $document->disk === 'local'
            && str_starts_with($document->path, 'procurement/suppliers/'.$supplier->id.'/')
            && ! str_contains($document->path, '..')
            && ! str_contains($document->path, '\\'),
            404,
        );
        $disk = Storage::disk('local');
        if ($disk->exists($document->path) && ! $disk->delete($document->path)) {
            abort(500, 'The supplier document could not be deleted. Its record was retained.');
        }
        $document->delete();
        $this->activityLog->recordForUser($request->user(), 'deleted', 'procurement', 'Deleted supplier document for '.$supplier->supplier_code, $document);

        return response()->json(['message' => 'Supplier document deleted.']);
    }

    public function storeSupplier(Request $request): JsonResponse
    {
        $data = $request->validate([
            'supplier_code' => ['nullable', 'string', 'max:40', 'unique:suppliers,supplier_code'],
            'name' => ['required', 'string', 'max:180'],
            'legal_name' => ['nullable', 'string', 'max:180'],
            'email' => ['nullable', 'email', 'max:180'],
            'phone' => ['nullable', 'string', 'max:40'],
            'tax_number' => ['nullable', 'string', 'max:80'],
            'contact_person' => ['nullable', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:5000'],
            'city' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'payment_terms' => ['nullable', 'string', 'max:120'],
            'credit_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $data['supplier_code'] = $data['supplier_code'] ?: 'SUP-'.strtoupper(Str::random(8));
        $data['created_by'] = $request->user()->id;
        $supplier = Supplier::query()->create($data);
        $this->activityLog->recordForUser($request->user(), 'created', 'procurement', 'Created supplier '.$supplier->supplier_code, $supplier);

        return response()->json(['supplier' => $supplier], 201);
    }

    public function updateSupplier(Request $request, Supplier $supplier): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:180'],
            'legal_name' => ['nullable', 'string', 'max:180'],
            'email' => ['nullable', 'email', 'max:180'],
            'phone' => ['nullable', 'string', 'max:40'],
            'tax_number' => ['nullable', 'string', 'max:80'],
            'contact_person' => ['nullable', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:5000'],
            'city' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'payment_terms' => ['nullable', 'string', 'max:120'],
            'credit_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'status' => ['sometimes', Rule::in(['active', 'inactive', 'blocked'])],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $oldValues = $supplier->only(array_keys($data));
        $supplier->update($data);
        $this->activityLog->recordForUser($request->user(), 'updated', 'procurement', 'Updated supplier '.$supplier->supplier_code, $supplier, $oldValues, $supplier->only(array_keys($data)));

        return response()->json(['supplier' => $supplier->fresh()]);
    }

    public function storeRequisition(Request $request): JsonResponse
    {
        $data = $request->validate([
            'location_id' => ['required', 'integer', 'exists:locations,id'],
            'purpose' => ['nullable', 'string', 'max:5000'],
            'required_by' => ['nullable', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'items.*.product_variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'regex:/^\d{1,14}(?:\.\d{1,4})?$/'],
            'items.*.notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $this->locationAccess->assertManage($request->user(), (int) $data['location_id']);
        $requisition = $this->procurement->createRequisition($data, $request->user());

        return response()->json(['requisition' => $requisition], 201);
    }

    public function submitRequisition(Request $request, PurchaseRequisition $requisition): JsonResponse
    {
        $this->locationAccess->assertManage($request->user(), (int) $requisition->location_id);

        return response()->json(['requisition' => $this->procurement->submitRequisition($requisition, $request->user())]);
    }

    public function rejectRequisition(Request $request, PurchaseRequisition $requisition): JsonResponse
    {
        $this->locationAccess->assertManage($request->user(), (int) $requisition->location_id);
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:2000']]);

        return response()->json(['requisition' => $this->procurement->rejectRequisition($requisition, $data['reason'], $request->user())]);
    }

    public function cancelRequisition(Request $request, PurchaseRequisition $requisition): JsonResponse
    {
        $this->locationAccess->assertManage($request->user(), (int) $requisition->location_id);

        return response()->json(['requisition' => $this->procurement->cancelRequisition($requisition, $request->user())]);
    }

    public function approveRequisition(Request $request, PurchaseRequisition $requisition): JsonResponse
    {
        $this->locationAccess->assertManage($request->user(), (int) $requisition->location_id);

        return response()->json(['requisition' => $this->procurement->approveRequisition($requisition, $request->user())]);
    }

    public function storePurchaseOrder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'location_id' => ['required', 'integer', 'exists:locations,id'],
            'purchase_requisition_id' => ['nullable', 'integer', 'exists:purchase_requisitions,id'],
            'order_date' => ['nullable', 'date'],
            'expected_at' => ['nullable', 'date', 'after_or_equal:order_date'],
            'currency' => ['nullable', 'string', 'size:3'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'items.*.product_variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'items.*.description' => ['nullable', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'regex:/^\d{1,14}(?:\.\d{1,4})?$/'],
            'items.*.unit_cost' => ['required', 'regex:/^\d{1,14}(?:\.\d{1,2})?$/'],
            'items.*.discount_amount' => ['nullable', 'regex:/^\d{1,14}(?:\.\d{1,2})?$/'],
            'items.*.tax_amount' => ['nullable', 'regex:/^\d{1,14}(?:\.\d{1,2})?$/'],
        ]);
        $this->locationAccess->assertManage($request->user(), (int) $data['location_id']);
        $po = $this->procurement->createPurchaseOrder($data, $request->user());

        return response()->json(['purchase_order' => $po], 201);
    }

    public function submitPurchaseOrder(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->locationAccess->assertManage($request->user(), (int) $purchaseOrder->location_id);

        return response()->json(['purchase_order' => $this->procurement->submitPurchaseOrder($purchaseOrder, $request->user())]);
    }

    public function rejectPurchaseOrder(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->locationAccess->assertManage($request->user(), (int) $purchaseOrder->location_id);
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:2000']]);

        return response()->json(['purchase_order' => $this->procurement->rejectPurchaseOrder($purchaseOrder, $data['reason'], $request->user())]);
    }

    public function cancelPurchaseOrder(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->locationAccess->assertManage($request->user(), (int) $purchaseOrder->location_id);

        return response()->json(['purchase_order' => $this->procurement->cancelPurchaseOrder($purchaseOrder, $request->user())]);
    }

    public function approvePurchaseOrder(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->locationAccess->assertManage($request->user(), (int) $purchaseOrder->location_id);

        return response()->json(['purchase_order' => $this->procurement->approvePurchaseOrder($purchaseOrder, $request->user())]);
    }

    public function requestPurchaseOrderRevision(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->locationAccess->assertManage($request->user(), (int) $purchaseOrder->location_id);
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
            'order_date' => ['nullable', 'date'],
            'expected_at' => ['nullable', 'date', 'after_or_equal:order_date'],
            'currency' => ['nullable', 'string', 'size:3'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'items.*.product_variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'regex:/^\\d{1,14}(?:\\.\\d{1,4})?$/'],
            'items.*.unit_cost' => ['required', 'regex:/^\\d{1,14}(?:\\.\\d{1,2})?$/'],
            'items.*.discount_amount' => ['nullable', 'regex:/^\\d{1,14}(?:\\.\\d{1,2})?$/'],
            'items.*.tax_amount' => ['nullable', 'regex:/^\\d{1,14}(?:\\.\\d{1,2})?$/'],
            'items.*.description' => ['nullable', 'string', 'max:1000'],
        ]);

        return response()->json([
            'revision' => $this->procurement->requestPurchaseOrderRevision($purchaseOrder, $data, $request->user()),
        ], 201);
    }

    public function approvePurchaseOrderRevision(Request $request, PurchaseOrderRevision $revision): JsonResponse
    {
        $revision->loadMissing('purchaseOrder');
        $this->locationAccess->assertManage($request->user(), (int) $revision->purchaseOrder->location_id);

        return response()->json([
            'revision' => $this->procurement->approvePurchaseOrderRevision($revision, $request->user()),
            'purchase_order' => $revision->purchaseOrder->fresh()->load(['supplier', 'items', 'revisions']),
        ]);
    }

    public function rejectPurchaseOrderRevision(Request $request, PurchaseOrderRevision $revision): JsonResponse
    {
        $revision->loadMissing('purchaseOrder');
        $this->locationAccess->assertManage($request->user(), (int) $revision->purchaseOrder->location_id);
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:2000']]);

        return response()->json([
            'revision' => $this->procurement->rejectPurchaseOrderRevision($revision, $data['reason'], $request->user()),
        ]);
    }

    public function receiveGoods(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->locationAccess->assertReceive($request->user(), (int) $purchaseOrder->location_id);
        $data = $request->validate([
            'received_date' => ['nullable', 'date'],
            'delivery_note' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'idempotency_key' => ['required', 'uuid'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.purchase_order_item_id' => ['required', 'integer', 'exists:purchase_order_items,id'],
            'items.*.received_quantity' => ['required', 'regex:/^\d{1,14}(?:\.\d{1,4})?$/'],
            'items.*.accepted_quantity' => ['required', 'regex:/^\d{1,14}(?:\.\d{1,4})?$/'],
            'items.*.rejected_quantity' => ['nullable', 'regex:/^\d{1,14}(?:\.\d{1,4})?$/'],
            'items.*.rejection_reason' => ['nullable', 'string', 'max:255'],
        ]);
        $receipt = $this->procurement->receiveGoods($purchaseOrder, $data, $request->user());

        return response()->json(['goods_receipt' => $receipt], 201);
    }

    public function storeInvoice(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->locationAccess->assertManage($request->user(), (int) $purchaseOrder->location_id);
        $data = $request->validate([
            'invoice_number' => ['required', 'string', 'max:100', Rule::unique('supplier_invoices', 'invoice_number')->where('supplier_id', $purchaseOrder->supplier_id)],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:invoice_date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.purchase_order_item_id' => ['required', 'integer', 'exists:purchase_order_items,id'],
            'items.*.quantity' => ['required', 'regex:/^\d{1,14}(?:\.\d{1,4})?$/'],
            'items.*.unit_cost' => ['required', 'regex:/^\d{1,14}(?:\.\d{1,2})?$/'],
            'tax_total' => ['nullable', 'regex:/^\d{1,14}(?:\.\d{1,2})?$/'],
            'discount_total' => ['nullable', 'regex:/^\d{1,14}(?:\.\d{1,2})?$/'],
        ]);

        return response()->json(['invoice' => $this->procurement->createSupplierInvoice($purchaseOrder, $data, $request->user())], 201);
    }

    public function postInvoice(Request $request, SupplierInvoice $invoice): JsonResponse
    {
        $this->locationAccess->assertManage($request->user(), (int) $invoice->purchaseOrder()->value('location_id'));

        return response()->json(['invoice' => $this->procurement->postSupplierInvoice($invoice, $request->user())]);
    }

    public function payInvoice(Request $request, SupplierInvoice $invoice): JsonResponse
    {
        $this->locationAccess->assertManage($request->user(), (int) $invoice->purchaseOrder()->value('location_id'));
        $data = $request->validate([
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'regex:/^\d{1,14}(?:\.\d{1,2})?$/'],
            'payment_method' => ['required', Rule::in(['cash', 'bank', 'mobile_banking', 'card', 'other'])],
            'cash_account_id' => ['required', 'integer', 'exists:financial_accounts,id'],
            'reference' => ['nullable', 'string', 'max:120'],
            'idempotency_key' => ['required', 'uuid'],
        ]);

        return response()->json(['payment' => $this->procurement->paySupplierInvoice($invoice, $data, $request->user())], 201);
    }

    public function returnToSupplier(Request $request, SupplierInvoice $invoice): JsonResponse
    {
        $this->locationAccess->assertManage($request->user(), (int) $invoice->purchaseOrder()->value('location_id'));
        $data = $request->validate([
            'return_date' => ['nullable', 'date'],
            'reason' => ['required', 'string', 'max:2000'],
            'idempotency_key' => ['required', 'uuid'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.purchase_order_item_id' => ['required', 'integer', 'exists:purchase_order_items,id'],
            'items.*.quantity' => ['required', 'regex:/^\d{1,14}(?:\.\d{1,4})?$/'],
        ]);

        return response()->json(['supplier_return' => $this->procurement->returnToSupplier($invoice, $data, $request->user())], 201);
    }
}
