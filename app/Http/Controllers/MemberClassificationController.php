<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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
                $q->where(
                    'classification_name',
                    'like',
                    '%' . $search . '%'
                )
                    ->orWhere(
                        'classification_code',
                        'like',
                        '%' . $search . '%'
                    )
                    ->orWhere(
                        'classification_category',
                        'like',
                        '%' . $search . '%'
                    )
                    ->orWhere(
                        'classification_description',
                        'like',
                        '%' . $search . '%'
                    );
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

        $categories = $this->getCategories();

        return view(
            'members.classifications.index',
            compact(
                'classifications',
                'categories'
            )
        );
    }

    /**
     * ---------------------------------------------------------
     * CREATE CLASSIFICATION
     * ---------------------------------------------------------
     *
     * When member_id is supplied as a query parameter, the user
     * came here from a particular member's role screen.
     */
    public function create(Request $request)
    {
        $returnMemberId = null;
        $returnMember = null;

        $requestedMemberId = (int) $request->query('member_id', 0);

        if ($requestedMemberId > 0) {
            $returnMember = DB::table('sacco_members')
                ->where('member_id', $requestedMemberId)
                ->first();

            if ($returnMember) {
                $returnMemberId = $returnMember->member_id;
            }
        }

        $categories = $this->getCategories();

        return view(
            'members.classifications.form',
            [
                'classification' => null,
                'categories' => $categories,
                'returnMemberId' => $returnMemberId,
                'returnMember' => $returnMember,
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

            'return_member_id' => [
                'nullable',
                'integer',
                Rule::exists(
                    'sacco_members',
                    'member_id'
                ),
            ],
        ]);

        $returnMemberId =
            isset($validated['return_member_id'])
                ? (int) $validated['return_member_id']
                : null;

        unset($validated['return_member_id']);

        $validated['classification_user_id'] = auth()->id();
        $validated['classification_transdate'] = now();

        $classificationId = DB::table(
            'sacco_member_classifications'
        )->insertGetId(
            $validated,
            'classification_id'
        );

        if ($returnMemberId) {
            return redirect()
                ->route(
                    'members.classifications.member',
                    $returnMemberId
                )
                ->with(
                    'success',
                    'Classification created successfully. You can now assign it to this member.'
                );
        }

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

        $categories = $this->getCategories();

        return view(
            'members.classifications.form',
            [
                'classification' => $classification,
                'categories' => $categories,
                'returnMemberId' => null,
                'returnMember' => null,
            ]
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
     * VIEW ONE MEMBER'S CLASSIFICATIONS
     * ---------------------------------------------------------
     */
    public function memberClassifications(int $member_id)
    {
        $member = $this->findMemberOrFail($member_id);

        $classifications = DB::table(
            'sacco_member_classification_members as mcm'
        )
            ->join(
                'sacco_member_classifications as c',
                'c.classification_id',
                '=',
                'mcm.classification_id'
            )
            ->where(
                'mcm.member_id',
                $member_id
            )
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
            ->orderByDesc(
                'mcm.classification_member_active'
            )
            ->orderBy(
                'c.classification_category'
            )
            ->orderBy(
                'c.classification_sort_order'
            )
            ->orderBy(
                'c.classification_name'
            )
            ->get();

        $currentClassifications = $classifications
            ->where(
                'classification_member_active',
                'Y'
            )
            ->values();

        $previousClassifications = $classifications
            ->where(
                'classification_member_active',
                'N'
            )
            ->values();

        /*
         * Roles currently assigned to this member are removed from
         * the assignable list.
         *
         * Previous/inactive roles remain available because assigning
         * one again will reactivate its existing relationship row.
         */
        $currentClassificationIds = $currentClassifications
            ->pluck('classification_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $availableQuery = DB::table(
            'sacco_member_classifications'
        )
            ->where(
                'classification_active',
                'Y'
            );

        if (!empty($currentClassificationIds)) {
            $availableQuery->whereNotIn(
                'classification_id',
                $currentClassificationIds
            );
        }

        $availableClassifications = $availableQuery
            ->orderBy(
                'classification_category'
            )
            ->orderBy(
                'classification_sort_order'
            )
            ->orderBy(
                'classification_name'
            )
            ->get();

        $availableByCategory = $availableClassifications
            ->groupBy(function ($classification) {
                return $classification->classification_category
                    ?: 'OTHER';
            });

        return view(
            'members.classifications.member',
            compact(
                'member',
                'classifications',
                'currentClassifications',
                'previousClassifications',
                'availableClassifications',
                'availableByCategory'
            )
        );
    }

    /**
     * ---------------------------------------------------------
     * ASSIGN CLASSIFICATION / ROLE TO MEMBER
     * ---------------------------------------------------------
     *
     * A member can hold MANY classifications simultaneously.
     *
     * If this exact member/classification relationship existed
     * previously but was inactive, it is reactivated.
     */
    public function assignMemberClassification(
        Request $request,
        int $member_id
    ) {
        $member = $this->findMemberOrFail($member_id);

        $validated = $request->validate([
            'classification_id' => [
                'required',
                'integer',

                Rule::exists(
                    'sacco_member_classifications',
                    'classification_id'
                )->where(function ($query) {
                    $query->where(
                        'classification_active',
                        'Y'
                    );
                }),
            ],

            'classification_date_from' => [
                'nullable',
                'date',
            ],

            'classification_date_to' => [
                'nullable',
                'date',
            ],

            'classification_member_notes' => [
                'nullable',
                'string',
                'max:5000',
            ],
        ]);

        $dateFrom =
            !empty($validated['classification_date_from'])
                ? Carbon::parse(
                    $validated['classification_date_from']
                )->startOfDay()
                : null;

        $dateTo =
            !empty($validated['classification_date_to'])
                ? Carbon::parse(
                    $validated['classification_date_to']
                )->startOfDay()
                : null;

        if (
            $dateFrom !== null &&
            $dateTo !== null &&
            $dateTo->lt($dateFrom)
        ) {
            throw ValidationException::withMessages([
                'classification_date_to' =>
                    'The role end date cannot be earlier than the role start date.',
            ]);
        }

        $classification = DB::table(
            'sacco_member_classifications'
        )
            ->where(
                'classification_id',
                (int) $validated['classification_id']
            )
            ->first();

        abort_if(
            !$classification,
            404,
            'Classification not found.'
        );

        $notes = trim(
            (string) (
                $validated['classification_member_notes']
                ?? ''
            )
        );

        DB::transaction(function () use (
            $member_id,
            $validated,
            $dateFrom,
            $dateTo,
            $notes
        ) {
            DB::table(
                'sacco_member_classification_members'
            )->updateOrInsert(
                [
                    'member_id' => $member_id,

                    'classification_id' =>
                        (int) $validated['classification_id'],
                ],
                [
                    'classification_date_from' =>
                        $dateFrom?->format('Y-m-d'),

                    'classification_date_to' =>
                        $dateTo?->format('Y-m-d'),

                    'classification_member_active' => 'Y',

                    'classification_member_notes' =>
                        $notes !== ''
                            ? $notes
                            : null,

                    'classification_member_user_id' =>
                        auth()->id(),

                    'classification_member_transdate' =>
                        now(),
                ]
            );
        });

        return redirect()
            ->route(
                'members.classifications.member',
                $member->member_id
            )
            ->with(
                'success',
                $classification->classification_name .
                ' has been assigned to ' .
                $member->member_name .
                '.'
            );
    }

    /**
     * ---------------------------------------------------------
     * END / REMOVE ACTIVE ROLE FROM MEMBER
     * ---------------------------------------------------------
     *
     * We do not physically delete history.
     *
     * The relationship is marked inactive and an end date is
     * recorded.
     */
    public function endMemberClassification(
        Request $request,
        int $member_id,
        int $assignment_id
    ) {
        $member = $this->findMemberOrFail($member_id);

        $assignment = DB::table(
            'sacco_member_classification_members as mcm'
        )
            ->join(
                'sacco_member_classifications as c',
                'c.classification_id',
                '=',
                'mcm.classification_id'
            )
            ->where(
                'mcm.member_classification_id',
                $assignment_id
            )
            ->where(
                'mcm.member_id',
                $member_id
            )
            ->select([
                'mcm.member_classification_id',
                'mcm.classification_date_from',
                'mcm.classification_date_to',
                'mcm.classification_member_active',
                'c.classification_name',
            ])
            ->first();

        abort_if(
            !$assignment,
            404,
            'Member role assignment not found.'
        );

        $validated = $request->validate([
            'classification_date_to' => [
                'nullable',
                'date',
            ],
        ]);

        $dateTo =
            !empty($validated['classification_date_to'])
                ? Carbon::parse(
                    $validated['classification_date_to']
                )->startOfDay()
                : now()->startOfDay();

        if (
            !empty($assignment->classification_date_from) &&
            $dateTo->lt(
                Carbon::parse(
                    $assignment->classification_date_from
                )->startOfDay()
            )
        ) {
            throw ValidationException::withMessages([
                'classification_date_to' =>
                    'The role end date cannot be earlier than the role start date.',
            ]);
        }

        DB::table(
            'sacco_member_classification_members'
        )
            ->where(
                'member_classification_id',
                $assignment_id
            )
            ->where(
                'member_id',
                $member_id
            )
            ->update([
                'classification_member_active' => 'N',

                'classification_date_to' =>
                    $dateTo->format('Y-m-d'),

                'classification_member_user_id' =>
                    auth()->id(),

                'classification_member_transdate' =>
                    now(),
            ]);

        return redirect()
            ->route(
                'members.classifications.member',
                $member->member_id
            )
            ->with(
                'success',
                $assignment->classification_name .
                ' has been ended for ' .
                $member->member_name .
                '.'
            );
    }

    /**
     * ---------------------------------------------------------
     * HELPERS
     * ---------------------------------------------------------
     */
    private function findMemberOrFail(int $memberId): object
    {
        $member = DB::table('sacco_members')
            ->where(
                'member_id',
                $memberId
            )
            ->first();

        abort_if(
            !$member,
            404,
            'Member not found.'
        );

        return $member;
    }

    private function findClassificationOrFail(
        int $classificationId
    ): object {
        $classification = DB::table(
            'sacco_member_classifications'
        )
            ->where(
                'classification_id',
                $classificationId
            )
            ->first();

        abort_if(
            !$classification,
            404,
            'Member classification not found.'
        );

        return $classification;
    }

    private function getCategories()
    {
        return DB::table(
            'sacco_member_classifications'
        )
            ->whereNotNull(
                'classification_category'
            )
            ->where(
                'classification_category',
                '<>',
                ''
            )
            ->select(
                'classification_category'
            )
            ->distinct()
            ->orderBy(
                'classification_category'
            )
            ->pluck(
                'classification_category'
            );
    }

    /**
     * Normalise application codes and categories.
     */
    private function normaliseRequest(
        Request $request
    ): void {
        $name = trim(
            (string) $request->input(
                'classification_name',
                ''
            )
        );

        $code = trim(
            (string) $request->input(
                'classification_code',
                ''
            )
        );

        $category = trim(
            (string) $request->input(
                'classification_category',
                ''
            )
        );

        $description = trim(
            (string) $request->input(
                'classification_description',
                ''
            )
        );

        $request->merge([
            'classification_name' =>
                $name,

            'classification_code' =>
                $code !== ''
                    ? Str::upper(
                        Str::slug(
                            $code,
                            '_'
                        )
                    )
                    : '',

            'classification_category' =>
                $category !== ''
                    ? Str::upper(
                        Str::slug(
                            $category,
                            '_'
                        )
                    )
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
                $request->filled(
                    'classification_sort_order'
                )
                    ? (int) $request->classification_sort_order
                    : 0,
        ]);
    }
}
