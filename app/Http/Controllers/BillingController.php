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
}
