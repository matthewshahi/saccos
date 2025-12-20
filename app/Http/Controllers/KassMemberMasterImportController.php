<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class KassMemberMasterImportController extends Controller
{
    protected array $companyMap = [];
    protected array $deptMap = [];
    protected array $memberByAdm = [];
    protected array $memberByNatId = [];
    protected array $memberByName = [];

    /* ============================================================
     *  DASHBOARD
     * ============================================================ */
    public function index()
    {
        return view('kass.member_master_import.index', [
            'stats' => [
                'members'     => DB::table('sacco_members')->count(),
                'companies'   => DB::table('sacco_company')->count(),
                'departments' => DB::table('sacco_department')->count(),
            ]
        ]);
    }

    /* ============================================================
     *  PREVIEW
     * ============================================================ */
    public function preview(Request $request)
    {
        $request->validate([
            'excel_file' => ['required','file','mimes:xlsx,xls','max:51200']
        ]);

        $path = $request->file('excel_file')->store('temp/member_imports');

        [$rows, $issues] = $this->parseExcel(Storage::path($path));

        $token = 'mmi_' . uniqid();
        Storage::put("temp/member_imports/{$token}.json", json_encode([
            'rows'  => $rows,
            'path'  => $path,
            'time'  => now()->toDateTimeString()
        ]));

        return view('kass.member_master_import.preview', compact('rows','issues','token'));
    }

    /* ============================================================
     *  CONFIRM IMPORT
     * ============================================================ */
    public function confirm(Request $request)
    {
        $token = $request->input('token');
        $json  = "temp/member_imports/{$token}.json";

        if (!Storage::exists($json)) {
            return redirect()->route('kass.member_master_import.index')
                ->with('error','IMPORT SESSION EXPIRED.');
        }

        $payload = json_decode(Storage::get($json), true);
        $rows = $payload['rows'];

        $summary = [
            'rows'            => count($rows),
            'created'         => 0,
            'updated'         => 0,
            'matched_adm'     => 0,
            'matched_id'      => 0,
            'matched_name'    => 0,
            'companies'       => 0,
            'departments'     => 0,
            'skipped'         => 0,
        ];

        DB::beginTransaction();

        try {
            foreach ($rows as $r) {

                if ($r['NAME'] === '') {
    $summary['skipped']++;
    continue;
}

               $employerName = $r['EMPLOYER'] !== '' ? $r['EMPLOYER'] : 'UNKNOWN';

$companyId = $this->resolveCompany($employerName, $summary);
$deptId    = $this->resolveDepartment($companyId, $employerName, $summary);

                [$memberId, $mode] = $this->resolveMember(
                    $r['MEMBERSHIP NO'],
                    $r['ID NUMBER'],
                    $r['NAME'],
                    $deptId,
                    $summary
                );

                if ($this->updateMember($memberId, $r, $deptId)) {
                    $summary['updated']++;
                }
            }

            DB::commit();

            return redirect()
                ->route('kass.member_master_import.index')
                ->with('success','IMPORT COMPLETED')
                ->with('summary',$summary);

        } catch (\Throwable $e) {
            DB::rollBack();
            return redirect()->back()->with('error',$e->getMessage());
        }
    }

    /* ============================================================
     *  EXCEL PARSER — ACTIVE SHEET "ALL MEMBERS" ONLY
     * ============================================================ */
    protected function parseExcel(string $file): array
    {
        $spreadsheet = IOFactory::load($file);
        $sheet = $spreadsheet->getSheetByName('ALL MEMBERS');

        if (!$sheet) {
            throw new \Exception('SHEET "ALL MEMBERS" NOT FOUND.');
        }

        $highestRow = $sheet->getHighestDataRow();
        $rows = [];
        $issues = 0;

        for ($r = 2; $r <= $highestRow; $r++) {

            // Column A is a row counter — IGNORE IT

$name = $this->upper($sheet->getCell("B{$r}")->getValue());
$membership = $this->upper($sheet->getCell("C{$r}")->getValue());
$kraPin = $this->upper($sheet->getCell("D{$r}")->getValue());
$nationalId = $this->upper($sheet->getCell("E{$r}")->getValue());

// DOB: prefer YYYYMMDD (F), fallback to formatted date (J)
$dobRaw = $sheet->getCell("F{$r}")->getValue();
if ($dobRaw === '' || $dobRaw === null) {
    $dobRaw = $sheet->getCell("J{$r}")->getValue();
}
$dob = $this->normalizeDob($dobRaw);

$employer = $this->upper($sheet->getCell("G{$r}")->getValue());
$phone = $this->normalizePhone($sheet->getCell("H{$r}")->getValue());
$email = $this->normalizeEmail($sheet->getCell("I{$r}")->getValue());

$row = [
    'NAME'           => $name,
    'MEMBERSHIP NO'  => $membership,
    'KRA PIN'        => $kraPin,
    'ID NUMBER'      => $nationalId,
    'DATE OF BIRTH'  => $dob,
    'EMPLOYER'       => $employer,
    'PHONE NO'       => $phone,
    'EMAIL'          => $email,
];
if ($name === '') $issues++;



            

            $rows[] = $row;
        }

        return [$rows, $issues];
    }

    /* ============================================================
     *  COMPANY / DEPARTMENT
     * ============================================================ */
    protected function resolveCompany(string $name, array &$s): int
    {
        if (isset($this->companyMap[$name])) return $this->companyMap[$name];

        $row = DB::table('sacco_company')->where('company_name',$name)->first();
        if ($row) return $this->companyMap[$name] = $row->company_id;

        $id = DB::table('sacco_company')->insertGetId([
            'company_name' => $name,
            'company_transdate' => now(),
            'company_ip' => 'IMPORT',
            'company_deleted' => 'N'
        ]);

        $s['companies']++;
        return $this->companyMap[$name] = $id;
    }

    protected function resolveDepartment(int $companyId, string $name, array &$s): int
    {
        if (isset($this->deptMap[$companyId])) return $this->deptMap[$companyId];

        $row = DB::table('sacco_department')
            ->where('department_company_id',$companyId)
            ->first();

        if ($row) return $this->deptMap[$companyId] = $row->department_id;

        $id = DB::table('sacco_department')->insertGetId([
            'department_name' => $name,
            'department_company_id' => $companyId,
            'department_transdate' => now(),
            'department_ip' => 'IMPORT',
            'department_deleted' => 'N'
        ]);

        $s['departments']++;
        return $this->deptMap[$companyId] = $id;
    }

    /* ============================================================
     *  MEMBER RESOLUTION
     * ============================================================ */
    protected function resolveMember($adm, $nid, $name, $dept, array &$s): array
    {
        if ($adm && isset($this->memberByAdm[$adm])) {
            $s['matched_adm']++;
            return [$this->memberByAdm[$adm],'ADM'];
        }

        if ($adm) {
            $m = DB::table('sacco_members')->where('member_sacco_id',$adm)->first();
            if ($m) {
                $this->memberByAdm[$adm] = $m->member_id;
                $s['matched_adm']++;
                return [$m->member_id,'ADM'];
            }
        }

        if ($nid) {
            $m = DB::table('sacco_members')->where('member_national_id',$nid)->first();
            if ($m) {
                $this->memberByNatId[$nid] = $m->member_id;
                $s['matched_id']++;
                return [$m->member_id,'ID'];
            }
        }

        $key = $this->canonical($name);
        if (isset($this->memberByName[$key])) {
            $s['matched_name']++;
            return [$this->memberByName[$key],'NAME'];
        }

        $id = DB::table('sacco_members')->insertGetId([
            'member_name' => $name,
            'member_dept' => $dept,
            'member_sacco_id' => $adm ?: null,
            'member_national_id' => $nid ?: null,
            'member_active' => 'Y',
            'member_deleted' => 'N',
            'member_position' => 1,
            'member_password_last_changed' => now()->subWeek(),
            'member_transdate' => now(),
            'member_ip' => 'IMPORT'
        ]);

        $s['created']++;
        $this->memberByName[$key] = $id;

        return [$id,'NEW'];
    }

    protected function updateMember(int $id, array $r, int $dept): bool
    {
        $m = DB::table('sacco_members')->where('member_id',$id)->first();
        $u = [];

        if (!$m->member_dept) $u['member_dept'] = $dept;
        if (!$m->member_phone_no && $r['PHONE NO']) $u['member_phone_no'] = $r['PHONE NO'];
        if (!$m->member_email && $r['EMAIL']) $u['member_email'] = $r['EMAIL'];
        if (!$m->member_kra_pin && $r['KRA PIN']) $u['member_kra_pin'] = $r['KRA PIN'];
        if (!$m->member_dob && $r['DATE OF BIRTH']) $u['member_dob'] = $r['DATE OF BIRTH'];

        if (!$u) return false;

        DB::table('sacco_members')->where('member_id',$id)->update($u);
        return true;
    }

    /* ============================================================
     *  HELPERS
     * ============================================================ */
    protected function upper($v): string
    {
        return strtoupper(trim((string)$v));
    }

    protected function normalizePhone($v): ?string
    {
        $d = preg_replace('/\D/','',(string)$v);
        return (str_starts_with($d,'254') && strlen($d)>=12) ? $d : null;
    }

    protected function normalizeEmail($v): ?string
    {
        $e = strtolower(trim((string)$v));
        return filter_var($e,FILTER_VALIDATE_EMAIL) ? strtoupper($e) : null;
    }

    protected function normalizeDob($v): ?string
{
    if ($v === null || $v === '') {
        return null;
    }

    // 1️⃣ If already a DateTime / Carbon object
    if ($v instanceof \DateTimeInterface) {
        return Carbon::instance($v)->format('Y-m-d');
    }

    // Normalize to string
    $raw = trim((string)$v);

    // 2️⃣ Excel serial number
    if (is_numeric($raw)) {
        try {
            return Carbon::instance(
                \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($raw)
            )->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }

    // 3️⃣ Already expanded datetime string: YYYY-MM-DD HH:MM:SS(.ffffff)
    if (preg_match('/^\d{4}-\d{2}-\d{2}\s/', $raw)) {
        try {
            return Carbon::parse($raw)->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }

    // 4️⃣ YYYYMMDD
    if (preg_match('/^\d{8}$/', $raw)) {
        try {
            return Carbon::createFromFormat('Ymd', $raw)->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }

    // 5️⃣ DD/MM/YYYY
    if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $raw)) {
        try {
            return Carbon::createFromFormat('d/m/Y', $raw)->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }

    // 6️⃣ Already ISO date
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
        return $raw;
    }

    // Anything else → ignore
    return null;
}


    protected function canonical(string $name): string
    {
        $t = preg_split('/\s+/', strtolower($name));
        sort($t);
        return implode(' ',$t);
    }
}
