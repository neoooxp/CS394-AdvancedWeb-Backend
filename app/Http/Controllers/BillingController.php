<?php

namespace App\Http\Controllers;

use App\Models\FeeStructure;
use App\Models\Guardian;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;

class BillingController extends Controller
{
    /**
     * Declare a new billing tier in the fee_structure table.
     */
    public function createFeeStructure(Request $request)
    {
        $request->validate([
            'fee_name'            => 'required|string',
            'base_amount'         => 'required|numeric|min:0',
            'discount_percentage' => 'nullable|numeric|min:0|max:100',
        ]);

        $feeStructure = FeeStructure::create([
            'fee_name'            => $request->fee_name,
            'base_amount'         => $request->base_amount,
            'discount_percentage' => $request->discount_percentage ?? 0.00,
        ]);

        Cache::forget('fee_structures:page:15');

        return response()->json([
            'message'       => 'Fee structure created successfully.',
            'fee_structure' => $feeStructure
        ], 201);
    }

    /**
     * Update an existing fee structure in the fee_structure table.
     */
    public function updateFeeStructure(Request $request, $id)
    {
        $feeStructure = FeeStructure::findOrFail($id);

        $request->validate([
            'fee_name'            => 'sometimes|string',
            'base_amount'         => 'sometimes|numeric|min:0',
            'discount_percentage' => 'nullable|numeric|min:0|max:100',
        ]);

        $feeStructure->update($request->only([
            'fee_name',
            'base_amount',
            'discount_percentage',
        ]));

        Cache::forget('fee_structures:page:15');

        return response()->json([
            'message'       => 'Fee structure updated successfully.',
            'fee_structure' => $feeStructure->fresh()
        ]);
    }

    /**
     * Iterate through all student fee assignments and generate monthly invoices.
     */
    public function generateInvoices()
    {
        try {
            $now     = Carbon::now();
            $dueDate = $now->copy()->endOfMonth();
            $created = 0;

            DB::transaction(function () use ($now, $dueDate, &$created) {
                $assignments = DB::table('student_fee_assignment')
                    ->join('students', 'student_fee_assignment.student_id', '=', 'students.student_id')
                    ->join('fee_structure', 'student_fee_assignment.fee_structure_id', '=', 'fee_structure.fee_structure_id')
                    ->join('student_guardians', 'students.student_id', '=', 'student_guardians.student_id')
                    ->select(
                        'student_guardians.guardian_id',
                        DB::raw('SUM(fee_structure.base_amount * (1 - fee_structure.discount_percentage / 100)) as total_amount')
                    )
                    ->groupBy('student_guardians.guardian_id')
                    ->get();

                if ($assignments->isEmpty()) {
                    $created = 0;
                    return;
                }

                $nowStr  = $now->toDateTimeString();
                $dateStr = $now->toDateString();
                $dueStr  = $dueDate->toDateString();

                $insertData = $assignments->map(fn($a) => [
                    'guardian_id'  => $a->guardian_id,
                    'invoice_date' => $dateStr,
                    'due_date'     => $dueStr,
                    'total_amount' => round((float) $a->total_amount, 2),
                    'status'       => 'Unpaid',
                    'created_at'   => $nowStr,
                    'updated_at'   => $nowStr,
                ])->toArray();

                Invoice::insert($insertData);
                $created = count($insertData);
            });

            return response()->json([
                'message'         => 'Monthly invoices generated successfully.',
                'invoices_created' => $created
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to generate monthly invoices: ' . $e->getMessage(), [
                'exception' => $e
            ]);

            return response()->json([
                'message' => 'Failed to generate monthly invoices.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Pull invoices and payments for a specific guardian account with optional status filter and pagination.
     */
    public function getLedger(Request $request, $guardianId)
    {
        $guardian = Guardian::with('user')
            ->where('guardian_id', $guardianId)
            ->orWhere('user_id', $guardianId)
            ->first();

        if (!$guardian) {
            return response()->json([
                'guardian'   => null,
                'invoices'   => [],
                'total_due'  => 0,
                'total_paid' => 0,
            ]);
        }

        $invoiceQuery = Invoice::with('payments')
            ->where('guardian_id', $guardian->guardian_id);

        if ($request->filled('status')) {
            $invoiceQuery->where('status', $request->query('status'));
        }

        $perPage = $request->query('per_page', 15);
        $invoices = $invoiceQuery->orderBy('created_at', 'desc')->paginate($perPage);

        $allInvoices = Invoice::where('guardian_id', $guardian->guardian_id)->get();
        $totalDue  = $allInvoices->where('status', 'Unpaid')->sum('total_amount');
        $totalPaid = Payment::whereIn('invoice_id', $allInvoices->pluck('invoice_id'))->sum('amount_paid');

        return response()->json([
            'guardian'   => $guardian,
            'invoices'   => $invoices,
            'total_due'  => $totalDue,
            'total_paid' => $totalPaid,
        ]);
    }

    /**
     * Log a payment and update the invoice status to Paid when fully settled.
     */
    public function recordPayment(Request $request)
    {
        $request->validate([
            'invoice_id'            => 'required|integer|exists:invoices,invoice_id',
            'amount_paid'           => 'required|numeric|min:0.01',
            'payment_method'        => 'required|string',
            'transaction_reference' => 'nullable|string',
        ]);

        $invoice = Invoice::findOrFail($request->invoice_id);

        $payment = Payment::create([
            'invoice_id'            => $invoice->invoice_id,
            'payment_date'          => now(),
            'amount_paid'           => $request->amount_paid,
            'payment_method'        => $request->payment_method,
            'transaction_reference' => $request->transaction_reference,
        ]);

        // Sum all payments for this invoice
        $totalPaid = Payment::where('invoice_id', $invoice->invoice_id)->sum('amount_paid');

        // Mark invoice as Paid if fully settled
        if ($totalPaid >= $invoice->total_amount) {
            $invoice->update(['status' => 'Paid']);
        }

        return response()->json([
            'message'     => 'Payment recorded successfully.',
            'payment'     => $payment,
            'invoice'     => $invoice->fresh(),
            'total_paid'  => $totalPaid,
        ]);
    }

    /**
     * Return a single invoice with its guardian and payments.
     */
    public function getInvoice($id)
    {
        $invoice = Invoice::with(['guardian.user', 'payments'])->findOrFail($id);
        return response()->json($invoice);
    }

    /**
     * Record payments for multiple invoices in a single request.
     */
    public function recordBulkPayments(Request $request)
    {
        $request->validate([
            'payments' => 'required|array|min:1',
            'payments.*.invoice_id'            => 'required|integer|exists:invoices,invoice_id',
            'payments.*.amount_paid'           => 'required|numeric|min:0.01',
            'payments.*.payment_method'        => 'required|string',
            'payments.*.transaction_reference' => 'nullable|string',
        ]);

        $results = [];

        DB::transaction(function () use ($request, &$results) {
            foreach ($request->payments as $p) {
                $invoice = Invoice::findOrFail($p['invoice_id']);

                $payment = Payment::create([
                    'invoice_id'            => $invoice->invoice_id,
                    'payment_date'          => now(),
                    'amount_paid'           => $p['amount_paid'],
                    'payment_method'        => $p['payment_method'],
                    'transaction_reference' => $p['transaction_reference'] ?? null,
                ]);

                $totalPaid = Payment::where('invoice_id', $invoice->invoice_id)->sum('amount_paid');

                if ($totalPaid >= $invoice->total_amount) {
                    $invoice->update(['status' => 'Paid']);
                }

                $results[] = [
                    'payment'    => $payment,
                    'invoice'    => $invoice->fresh()->load('guardian.user'),
                    'total_paid' => $totalPaid,
                ];
            }
        });

        return response()->json([
            'message' => 'Bulk payments recorded successfully.',
            'results' => $results,
        ]);
    }

    /**
     * Return all registered fee structures.
     */
    public function getFeeStructures(Request $request)
    {
        $perPage = $request->query('per_page', 15);
        $cacheKey = 'fee_structures:page:' . $perPage;

        $data = Cache::remember($cacheKey, 1800, function () use ($perPage) {
            if (FeeStructure::count() === 0) {
                FeeStructure::create(['fee_name' => 'Standard Route (Monthly)', 'base_amount' => 150.00, 'discount_percentage' => 0.00]);
                FeeStructure::create(['fee_name' => 'Special Ed (Monthly)', 'base_amount' => 220.00, 'discount_percentage' => 0.00]);
                FeeStructure::create(['fee_name' => 'Field Trip (Hourly)', 'base_amount' => 45.00, 'discount_percentage' => 0.00]);
                FeeStructure::create(['fee_name' => 'Late Fee Penalty', 'base_amount' => 25.00, 'discount_percentage' => 0.00]);
            }
            return FeeStructure::paginate($perPage);
        });

        return response()->json($data);
    }

    /**
     * Assign a fee structure to a target student.
     */
    public function assignFeeStructure(Request $request)
    {
        if (FeeStructure::count() === 0) {
            FeeStructure::create(['fee_name' => 'Standard Route (Monthly)', 'base_amount' => 150.00, 'discount_percentage' => 0.00]);
            FeeStructure::create(['fee_name' => 'Special Ed (Monthly)', 'base_amount' => 220.00, 'discount_percentage' => 0.00]);
            FeeStructure::create(['fee_name' => 'Field Trip (Hourly)', 'base_amount' => 45.00, 'discount_percentage' => 0.00]);
            FeeStructure::create(['fee_name' => 'Late Fee Penalty', 'base_amount' => 25.00, 'discount_percentage' => 0.00]);
        }

        $request->validate([
            'student_id'       => 'required|integer|exists:students,student_id',
            'fee_structure_id' => 'required|integer|exists:fee_structure,fee_structure_id',
        ]);

        DB::table('student_fee_assignment')->updateOrInsert(
            [
                'student_id' => $request->student_id,
            ],
            [
                'fee_structure_id' => $request->fee_structure_id,
            ]
        );

        return response()->json([
            'message' => 'Fee structure assigned to student successfully.'
        ], 200);
    }

    /**
     * Return all invoices/payments for the Financial Overview ledger.
     */
    public function getInvoices(Request $request)
    {
        $perPage = $request->query('per_page', 15);
        $invoices = Invoice::with(['guardian.user', 'payments'])->orderBy('created_at', 'desc')->paginate($perPage);
        return response()->json($invoices);
    }

    /**
     * Update invoice payment status (Paid, Overdue, Pending, Unpaid).
     */
    public function updateInvoiceStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|string|in:Paid,Overdue,Pending,Unpaid',
        ]);

        $invoice = Invoice::findOrFail($id);
        $invoice->update(['status' => $request->status]);

        return response()->json([
            'message' => 'Invoice status updated successfully.',
            'invoice' => $invoice->fresh()->load('guardian.user')
        ]);
    }
}
