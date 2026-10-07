<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\HsCode;
use App\Models\TenantSetting;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomsRateController extends Controller
{
    private array $settingDefaults = [
        'customs_exchange_rate' => 158.5,
        'customs_gct_company_rate' => 25,
        'customs_gct_personal_rate' => 15,
        'customs_caf_residential_jmd' => 2500,
        'customs_caf_commercial_jmd' => 5000,
        'customs_de_minimis_usd' => 100,
        'customs_default_duty_rate' => 20,
        'customs_insurance_rate' => 1,
        'customs_scf_rate' => 0,
        'customs_envl_rate' => 1,
        'customs_stamp_duty_jmd' => 100,
    ];

    public function index(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;
        $rates = HsCode::where('tenant_id', $tenantId)
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->input('search');
                $query->where(fn ($q) => $q->where('code', 'like', "%{$search}%")->orWhere('description', 'like', "%{$search}%"));
            })
            ->orderBy('code')
            ->paginate($this->perPage(request(), 20))
            ->withQueryString();

        $settings = $this->customsSettings($tenantId);

        return view('courier.customs-rates.index', compact('rates', 'settings'));
    }

    public function create()
    {
        return view('courier.customs-rates.create', ['rate' => null]);
    }

    public function store(Request $request)
    {
        $rate = HsCode::create($this->validatedRate($request) + ['tenant_id' => auth()->user()->tenant_id]);
        ActivityLog::record('created', 'hs_codes', 'Created customs HS code '.$rate->code);

        return redirect()->route('courier.customs-rates.index')->with('success', 'Customs rate created.');
    }

    public function edit(HsCode $customsRate)
    {
        $this->authorizeTenant($customsRate);

        return view('courier.customs-rates.edit', ['rate' => $customsRate]);
    }

    public function update(Request $request, HsCode $customsRate)
    {
        $this->authorizeTenant($customsRate);
        $customsRate->update($this->validatedRate($request));
        ActivityLog::record('updated', 'hs_codes', 'Updated customs HS code '.$customsRate->code);

        return redirect()->route('courier.customs-rates.index')->with('success', 'Customs rate updated.');
    }

    public function destroy(HsCode $customsRate)
    {
        $this->authorizeTenant($customsRate);
        $description = 'Deleted customs HS code '.$customsRate->code;
        $customsRate->delete();
        ActivityLog::record('deleted', 'hs_codes', $description);

        return redirect()->route('courier.customs-rates.index')->with('success', 'Customs rate deleted.');
    }

    public function updateSettings(Request $request)
    {
        $data = $request->validate([
            'customs_exchange_rate' => 'required|numeric|min:0',
            'customs_gct_company_rate' => 'required|numeric|min:0|max:100',
            'customs_gct_personal_rate' => 'required|numeric|min:0|max:100',
            'customs_caf_residential_jmd' => 'required|numeric|min:0',
            'customs_caf_commercial_jmd' => 'required|numeric|min:0',
            'customs_de_minimis_usd' => 'required|numeric|min:0',
            'customs_default_duty_rate' => 'required|numeric|min:0|max:100',
            'customs_insurance_rate' => 'required|numeric|min:0|max:100',
            'customs_scf_rate' => 'required|numeric|min:0|max:100',
            'customs_envl_rate' => 'required|numeric|min:0|max:100',
            'customs_stamp_duty_jmd' => 'required|numeric|min:0',
        ]);

        $tenantId = auth()->user()->tenant_id;
        foreach ($data as $key => $value) {
            TenantSetting::putValue($tenantId, $key, $value);
        }

        ActivityLog::record('updated', 'settings', 'Updated customs calculator settings');

        return redirect()->route('courier.customs-rates.index')->with('success', 'Customs calculator settings updated.');
    }

    public function import(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt|max:8192']);
        $handle = fopen($request->file('file')->getRealPath(), 'r');
        $header = array_map(fn ($value) => strtolower(trim($value)), fgetcsv($handle) ?: []);
        $created = 0;
        $updated = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $record = array_combine($header, $row);
            if (! $record || empty($record['code'])) {
                continue;
            }

            $payload = [
                'description' => $record['description'] ?? $record['item_description'] ?? '',
                'duty_rate' => $this->percentValue($record['duty_rate'] ?? $record['duty'] ?? $record['rate'] ?? 0),
                'is_active' => ! in_array(strtolower((string) ($record['is_active'] ?? '1')), ['0', 'false', 'no', 'inactive'], true),
            ];

            $rate = HsCode::updateOrCreate([
                'tenant_id' => auth()->user()->tenant_id,
                'code' => trim($record['code']),
            ], $payload);

            $rate->wasRecentlyCreated ? $created++ : $updated++;
        }
        fclose($handle);

        ActivityLog::record('imported', 'hs_codes', "Imported customs HS codes ({$created} created, {$updated} updated)");

        return redirect()->route('courier.customs-rates.index')->with('success', "{$created} customs rates created, {$updated} updated.");
    }

    public function export(): StreamedResponse
    {
        $filename = 'customs-hs-rates-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['code', 'description', 'duty_rate', 'is_active']);
            HsCode::where('tenant_id', auth()->user()->tenant_id)->orderBy('code')->chunk(200, function ($rates) use ($out) {
                foreach ($rates as $rate) {
                    fputcsv($out, [$rate->code, $rate->description, $rate->duty_rate, $rate->is_active ? 1 : 0]);
                }
            });
            fclose($out);
        }, $filename);
    }

    public function template(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['code', 'description', 'duty_rate', 'is_active']);
            fputcsv($out, ['8517.13', 'Smart phones', '20', '1']);
            fclose($out);
        }, 'customs-hs-rates-template.csv');
    }

    private function validatedRate(Request $request): array
    {
        return $request->validate([
            'code' => 'required|string|max:20',
            'description' => 'required|string|max:2000',
            'duty_rate' => 'required|numeric|min:0|max:100',
            'is_active' => 'nullable|boolean',
        ]) + ['is_active' => false];
    }

    private function customsSettings(int $tenantId): array
    {
        $stored = TenantSetting::where('tenant_id', $tenantId)
            ->whereIn('key', array_keys($this->settingDefaults))
            ->pluck('value', 'key')
            ->all();

        return array_merge($this->settingDefaults, $stored);
    }

    private function authorizeTenant(HsCode $rate): void
    {
        abort_unless($rate->tenant_id === auth()->user()->tenant_id, 404);
    }

    private function percentValue($value): float
    {
        return (float) str_replace('%', '', (string) $value);
    }
}
