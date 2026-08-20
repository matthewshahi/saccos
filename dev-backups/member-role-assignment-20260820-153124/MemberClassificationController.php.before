<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class MemberClassificationController extends Controller
{
    /**
     * ---------------------------------------------------------
     * MASTER CLASSIFICATION LIST
     * ---------------------------------------------------------
     */
    public function index(Request $request)
    {
        $query = DB::table('sacco_member_classifications');

        if ($request->filled('search')) {
            $search = trim((string) $request->search);

            $query->where(function ($q) use ($search) {
                $q->where('classification_name', 'like', '%' . $search . '%')
                    ->orWhere('classification_code', 'like', '%' . $search . '%')
                    ->orWhere('classification_category', 'like', '%' . $search . '%')
                    ->orWhere('classification_description', 'like', '%' . $search . '%');
            });
        }

        if ($request->filled('category')) {
            $query->where(
                'classification_category',
                $request->category
            );
        }

        if ($request->filled('active')) {
            $query->where(
                'classification_active',
                strtoupper((string) $request->active)
            );
        }

        $classifications = $query
            ->orderBy('classification_category')
            ->orderBy('classification_sort_order')
            ->orderBy('classification_name')
            ->get();

        $categories = DB::table('sacco_member_classifications')
            ->whereNotNull('classification_category')
            ->where('classification_category', '<>', '')
            ->select('classification_category')
            ->distinct()
            ->orderBy('classification_category')
            ->pluck('classification_category');

        return view(
            'members.classifications.index',
            compact('classifications', 'categories')
        );
    }

    /**
     * ---------------------------------------------------------
     * CREATE CLASSIFICATION
     * ---------------------------------------------------------
     */
    public function create()
    {
        $categories = DB::table('sacco_member_classifications')
            ->whereNotNull('classification_category')
            ->where('classification_category', '<>', '')
            ->select('classification_category')
            ->distinct()
            ->orderBy('classification_category')
            ->pluck('classification_category');

        return view(
            'members.classifications.form',
            [
                'classification' => null,
                'categories' => $categories,
            ]
        );
    }

    /**
     * ---------------------------------------------------------
     * STORE CLASSIFICATION
     * ---------------------------------------------------------
     */
    public function store(Request $request)
    {
        $this->normaliseRequest($request);

        $validated = $request->validate([
            'classification_name' => [
                'required',
                'string',
                'max:150',
            ],

            'classification_code' => [
                'required',
                'string',
                'max:120',
                Rule::unique(
                    'sacco_member_classifications',
                    'classification_code'
                ),
            ],

            'classification_category' => [
                'nullable',
                'string',
                'max:120',
            ],

            'classification_description' => [
                'nullable',
                'string',
                'max:5000',
            ],

            'classification_active' => [
                'required',
                'in:Y,N',
            ],

            'classification_sort_order' => [
                'required',
                'integer',
                'min:0',
                'max:100000',
            ],
        ]);

        $validated['classification_user_id'] = auth()->id();
        $validated['classification_transdate'] = now();

        DB::table('sacco_member_classifications')
            ->insert($validated);

        return redirect()
            ->route('members.classifications.index')
            ->with(
                'success',
                'Member classification created successfully.'
            );
    }

    /**
     * ---------------------------------------------------------
     * EDIT CLASSIFICATION
     * ---------------------------------------------------------
     */
    public function edit(int $id)
    {
        $classification = $this->findClassificationOrFail($id);

        $categories = DB::table('sacco_member_classifications')
            ->whereNotNull('classification_category')
            ->where('classification_category', '<>', '')
            ->select('classification_category')
            ->distinct()
            ->orderBy('classification_category')
            ->pluck('classification_category');

        return view(
            'members.classifications.form',
            compact('classification', 'categories')
        );
    }

    /**
     * ---------------------------------------------------------
     * UPDATE CLASSIFICATION
     * ---------------------------------------------------------
     */
    public function update(Request $request, int $id)
    {
        $classification = $this->findClassificationOrFail($id);

        $this->normaliseRequest($request);

        $validated = $request->validate([
            'classification_name' => [
                'required',
                'string',
                'max:150',
            ],

            'classification_code' => [
                'required',
                'string',
                'max:120',
                Rule::unique(
                    'sacco_member_classifications',
                    'classification_code'
                )->ignore(
                    $classification->classification_id,
                    'classification_id'
                ),
            ],

            'classification_category' => [
                'nullable',
                'string',
                'max:120',
            ],

            'classification_description' => [
                'nullable',
                'string',
                'max:5000',
            ],

            'classification_active' => [
                'required',
                'in:Y,N',
            ],

            'classification_sort_order' => [
                'required',
                'integer',
                'min:0',
                'max:100000',
            ],
        ]);

        $validated['classification_user_id'] = auth()->id();
        $validated['classification_transdate'] = now();

        DB::table('sacco_member_classifications')
            ->where(
                'classification_id',
                $classification->classification_id
            )
            ->update($validated);

        return redirect()
            ->route('members.classifications.index')
            ->with(
                'success',
                'Member classification updated successfully.'
            );
    }

    /**
     * ---------------------------------------------------------
     * MEMBER CLASSIFICATIONS
     * ---------------------------------------------------------
     *
     * View-only for now.
     *
     * This shows all classifications / roles associated with
     * a particular SACCO member.
     */
    public function memberClassifications(int $member_id)
    {
        $member = DB::table('sacco_members')
            ->where('member_id', $member_id)
            ->first();

        abort_if(!$member, 404, 'Member not found.');

        $classifications = DB::table(
            'sacco_member_classification_members as mcm'
        )
            ->join(
                'sacco_member_classifications as c',
                'c.classification_id',
                '=',
                'mcm.classification_id'
            )
            ->where('mcm.member_id', $member_id)
            ->select([
                'mcm.member_classification_id',
                'mcm.member_id',
                'mcm.classification_id',
                'mcm.classification_date_from',
                'mcm.classification_date_to',
                'mcm.classification_member_active',
                'mcm.classification_member_notes',

                'c.classification_name',
                'c.classification_code',
                'c.classification_category',
                'c.classification_description',
                'c.classification_active',
                'c.classification_sort_order',
            ])
            ->orderByDesc('mcm.classification_member_active')
            ->orderBy('c.classification_category')
            ->orderBy('c.classification_sort_order')
            ->orderBy('c.classification_name')
            ->get();

        $currentClassifications = $classifications
            ->where('classification_member_active', 'Y')
            ->values();

        $previousClassifications = $classifications
            ->where('classification_member_active', 'N')
            ->values();

        return view(
            'members.classifications.member',
            compact(
                'member',
                'classifications',
                'currentClassifications',
                'previousClassifications'
            )
        );
    }

    /**
     * ---------------------------------------------------------
     * FIND CLASSIFICATION
     * ---------------------------------------------------------
     */
    private function findClassificationOrFail(int $id): object
    {
        $classification = DB::table(
            'sacco_member_classifications'
        )
            ->where('classification_id', $id)
            ->first();

        abort_if(
            !$classification,
            404,
            'Member classification not found.'
        );

        return $classification;
    }

    /**
     * ---------------------------------------------------------
     * NORMALISE CLASSIFICATION INPUT
     * ---------------------------------------------------------
     */
    private function normaliseRequest(Request $request): void
    {
        $name = trim(
            (string) $request->input('classification_name', '')
        );

        $code = trim(
            (string) $request->input('classification_code', '')
        );

        $category = trim(
            (string) $request->input('classification_category', '')
        );

        $description = trim(
            (string) $request->input('classification_description', '')
        );

        $request->merge([
            'classification_name' => $name,

            'classification_code' =>
                $code !== ''
                    ? Str::upper(Str::slug($code, '_'))
                    : '',

            'classification_category' =>
                $category !== ''
                    ? Str::upper(Str::slug($category, '_'))
                    : null,

            'classification_description' =>
                $description !== ''
                    ? $description
                    : null,

            'classification_active' =>
                Str::upper(
                    (string) $request->input(
                        'classification_active',
                        'Y'
                    )
                ),

            'classification_sort_order' =>
                $request->filled('classification_sort_order')
                    ? (int) $request->classification_sort_order
                    : 0,
        ]);
    }
}
