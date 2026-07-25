<?php

namespace App\Http\Controllers;

use App\Models\FeeStructure;
use App\Models\Guardian;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        $now     = Carbon::now();
        $dueDate = $now->copy()->endOfMonth();
        $created = 0;

        DB::transaction(function () use ($now, $dueDate, &$created) {
            // Pull all student->fee->guardian assignments
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

            foreach ($assignments as $assignment) {
                Invoice::create([
                    'guardian_id'  => $assignment->guardian_id,
                    'invoice_date' => $now->toDateString(),
                    'due_date'     => $dueDate->toDateString(),
                    'total_amount' => $assignment->total_amount,
                    'status'       => 'Unpaid',
                ]);
                $created++;
            }
        });

        return response()->json([
            'message'         => 'Monthly invoices generated successfully.',
            'invoices_created' => $created
        ]);
    }

    /**
     * Pull all invoices and payments for a specific guardian account.
     */
    public function getLedger($guardianId)
    {
        $guardian = Guardian::with([
            'invoices.payments'
        ])->findOrFail($guardianId);

        $invoices = $guardian->invoices;
        $totalDue  = $invoices->where('status', 'Unpaid')->sum('total_amount');
        $totalPaid = $invoices->flatMap->payments->sum('amount_paid');

        return response()->json([
            'guardian'   => $guardian->load('user'),
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
     * Return all registered fee structures.
     */
    public function getFeeStructures()
    {
        if (FeeStructure::count() === 0) {
            FeeStructure::create(['fee_name' => 'Standard Route (Monthly)', 'base_amount' => 150.00, 'discount_percentage' => 0.00]);
            FeeStructure::create(['fee_name' => 'Special Ed (Monthly)', 'base_amount' => 220.00, 'discount_percentage' => 0.00]);
            FeeStructure::create(['fee_name' => 'Field Trip (Hourly)', 'base_amount' => 45.00, 'discount_percentage' => 0.00]);
            FeeStructure::create(['fee_name' => 'Late Fee Penalty', 'base_amount' => 25.00, 'discount_percentage' => 0.00]);
        }
        $structures = FeeStructure::all();
        return response()->json($structures);
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
    public function getInvoices()
    {
        $invoices = Invoice::with(['guardian.user', 'payments'])->orderBy('created_at', 'desc')->get();
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
