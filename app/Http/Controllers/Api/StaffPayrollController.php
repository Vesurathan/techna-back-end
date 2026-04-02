<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Models\StaffPayrollPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StaffPayrollController extends Controller
{
    public function index(Request $request)
    {
        $query = StaffPayrollPayment::with(['staff', 'createdBy']);

        if ($request->filled('staff_id')) {
            $query->where('staff_id', $request->staff_id);
        }

        if ($request->filled('pay_year')) {
            $query->where('pay_year', (int) $request->pay_year);
        }

        if ($request->filled('pay_month')) {
            $query->where('pay_month', (int) $request->pay_month);
        }

        if ($request->filled('pay_period')) {
            $parts = explode('-', $request->pay_period, 2);
            if (count($parts) === 2) {
                $query->where('pay_year', (int) $parts[0])
                    ->where('pay_month', (int) $parts[1]);
            }
        }

        if ($request->filled('pay_type')) {
            $query->where('pay_type', $request->pay_type);
        }

        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('staff', function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('nic_number', 'like', "%{$search}%");
            });
        }

        $page = (int) $request->get('page', 1);
        $perPage = 20;

        $rows = $query->orderBy('pay_year', 'desc')
            ->orderBy('pay_month', 'desc')
            ->orderBy('payment_date', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'payroll_payments' => $rows->map(fn ($p) => $this->formatPayment($p)),
            'pagination' => [
                'current_page' => $rows->currentPage(),
                'per_page' => $rows->perPage(),
                'total' => $rows->total(),
                'last_page' => $rows->lastPage(),
                'from' => $rows->firstItem(),
                'to' => $rows->lastItem(),
                'has_more_pages' => $rows->hasMorePages(),
            ],
        ]);
    }

    public function staffSummary(Request $request, Staff $staff)
    {
        $query = StaffPayrollPayment::with(['createdBy', 'staff'])
            ->where('staff_id', $staff->id)
            ->orderBy('pay_year', 'desc')
            ->orderBy('pay_month', 'desc')
            ->orderBy('payment_date', 'desc');

        if ($request->filled('pay_year')) {
            $query->where('pay_year', (int) $request->pay_year);
        }

        $payments = $query->get();

        $byPeriod = [];
        foreach ($payments as $p) {
            $key = $p->pay_year.'-'.str_pad((string) $p->pay_month, 2, '0', STR_PAD_LEFT);
            if (! isset($byPeriod[$key])) {
                $byPeriod[$key] = 0;
            }
            $byPeriod[$key] += (float) $p->net_amount;
        }

        return response()->json([
            'staff' => [
                'id' => (string) $staff->id,
                'full_name' => $staff->full_name,
                'first_name' => $staff->first_name,
                'last_name' => $staff->last_name,
            ],
            'payroll_payments' => $payments->map(fn ($p) => $this->formatPayment($p)),
            'totals_by_period' => $byPeriod,
        ]);
    }

    public function show(StaffPayrollPayment $staff_payroll_payment)
    {
        $staff_payroll_payment->load(['staff', 'createdBy']);

        return response()->json([
            'payroll_payment' => $this->formatPayment($staff_payroll_payment, true),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validatePayload($request, false);

        $validated['created_by'] = Auth::id();

        $payment = StaffPayrollPayment::create($validated);
        $payment->load(['staff', 'createdBy']);

        return response()->json([
            'payroll_payment' => $this->formatPayment($payment, true),
            'message' => 'Payroll record created.',
        ], 201);
    }

    public function update(Request $request, StaffPayrollPayment $staff_payroll_payment)
    {
        $validated = $this->validatePayload($request, true);
        $staff_payroll_payment->update($validated);
        $staff_payroll_payment->load(['staff', 'createdBy']);

        return response()->json([
            'payroll_payment' => $this->formatPayment($staff_payroll_payment, true),
            'message' => 'Payroll record updated.',
        ]);
    }

    public function destroy(StaffPayrollPayment $staff_payroll_payment)
    {
        $staff_payroll_payment->delete();

        return response()->json(['message' => 'Payroll record deleted.']);
    }

    private function validatePayload(Request $request, bool $isUpdate): array
    {
        $payTypes = ['salary', 'stipend', 'allowance', 'bonus', 'research_grant', 'other'];
        $methods = ['bank_transfer', 'cash', 'cheque'];

        $idRule = $isUpdate
            ? ['sometimes', 'required', Rule::exists(Staff::class, 'id')]
            : ['required', Rule::exists(Staff::class, 'id')];
        $periodRule = $isUpdate
            ? ['sometimes', 'required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/']
            : ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'];

        $rules = [
            'staff_id' => $idRule,
            'pay_period' => $periodRule,
            'pay_type' => ['required', Rule::in($payTypes)],
            'gross_amount' => ['nullable', 'numeric', 'min:0'],
            'deductions' => ['nullable', 'numeric', 'min:0'],
            'net_amount' => ['required', 'numeric', 'min:0.01'],
            'payment_date' => ['required', 'date'],
            'payment_method' => ['required', Rule::in($methods)],
            'account_holder_name' => ['nullable', 'string', 'max:255'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'account_number' => ['nullable', 'string', 'max:64'],
            'bank_branch' => ['nullable', 'string', 'max:255'],
            'iban_swift' => ['nullable', 'string', 'max:64'],
            'transfer_reference' => ['nullable', 'string', 'max:255'],
            'transfer_memo' => ['nullable', 'string', 'max:5000'],
            'internal_notes' => ['nullable', 'string', 'max:5000'],
        ];

        $messages = [
            'pay_period.regex' => 'Pay period must be in YYYY-MM format.',
        ];

        $data = $request->validate($rules, $messages);

        if (($data['payment_method'] ?? null) === 'bank_transfer') {
            $bankData = $request->validate([
                'account_holder_name' => ['required', 'string', 'max:255'],
                'bank_name' => ['required', 'string', 'max:255'],
                'account_number' => ['required', 'string', 'max:64'],
            ], [], [
                'account_holder_name' => 'account holder name',
                'bank_name' => 'bank name',
                'account_number' => 'account number',
            ]);
            $data = array_merge($data, $bankData);
        }

        $out = $data;

        if (isset($data['pay_period'])) {
            $parts = explode('-', $data['pay_period'], 2);
            $out['pay_year'] = (int) $parts[0];
            $out['pay_month'] = (int) $parts[1];
            unset($out['pay_period']);
        }

        $out['deductions'] = isset($out['deductions']) && $out['deductions'] !== '' && $out['deductions'] !== null
            ? (string) $out['deductions']
            : '0';
        if (array_key_exists('gross_amount', $out) && $out['gross_amount'] === null) {
            unset($out['gross_amount']);
        }

        if ($out['payment_method'] !== 'bank_transfer') {
            $out['account_holder_name'] = null;
            $out['bank_name'] = null;
            $out['account_number'] = null;
            $out['bank_branch'] = null;
            $out['iban_swift'] = null;
            $out['transfer_reference'] = null;
        }

        return $out;
    }

    private function formatPayment(StaffPayrollPayment $p, bool $detailed = false): array
    {
        $period = sprintf('%04d-%02d', $p->pay_year, $p->pay_month);

        $base = [
            'id' => (string) $p->id,
            'staff_id' => (string) $p->staff_id,
            'staff' => $p->relationLoaded('staff') && $p->staff ? [
                'id' => (string) $p->staff->id,
                'full_name' => $p->staff->full_name,
                'first_name' => $p->staff->first_name,
                'last_name' => $p->staff->last_name,
            ] : null,
            'pay_year' => $p->pay_year,
            'pay_month' => $p->pay_month,
            'pay_period' => $period,
            'pay_type' => $p->pay_type,
            'gross_amount' => $p->gross_amount !== null ? (float) $p->gross_amount : null,
            'deductions' => (float) $p->deductions,
            'net_amount' => (float) $p->net_amount,
            'payment_date' => $p->payment_date->format('Y-m-d'),
            'payment_method' => $p->payment_method,
            'created_at' => $p->created_at->toIso8601String(),
            'updated_at' => $p->updated_at->toIso8601String(),
        ];

        if ($detailed) {
            $base['account_holder_name'] = $p->account_holder_name;
            $base['bank_name'] = $p->bank_name;
            $base['account_number'] = $p->account_number;
            $base['bank_branch'] = $p->bank_branch;
            $base['iban_swift'] = $p->iban_swift;
            $base['transfer_reference'] = $p->transfer_reference;
            $base['transfer_memo'] = $p->transfer_memo;
            $base['internal_notes'] = $p->internal_notes;
            $base['created_by'] = $p->relationLoaded('createdBy') && $p->createdBy ? [
                'id' => (string) $p->createdBy->id,
                'name' => $p->createdBy->name,
            ] : null;
        }

        return $base;
    }
}
