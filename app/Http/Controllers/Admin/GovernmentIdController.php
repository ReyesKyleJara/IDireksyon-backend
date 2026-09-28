<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\GovernmentId;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GovernmentIdController extends Controller
{
    public function index(Request $request)
    {
        $sort = $request->input('sort', 'name_asc');

        $governmentIds = GovernmentId::query()
            ->with('agency')
            ->when($request->filled('q'), function ($query) use ($request) {
                $search = $request->string('q')->trim();

                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhereHas('agency', function ($agencyQuery) use ($search) {
                            $agencyQuery
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('acronym', 'like', "%{$search}%");
                        });
                });
            })
            ->when($request->filled('level'), function ($query) use ($request) {
                $query->where('level', $request->level);
            })
            ->when($request->filled('category'), function ($query) use ($request) {
                $query->where('category', $request->category);
            })
            ->when(
                $sort === 'name_asc',
                fn ($query) => $query->orderBy('name')
            )
            ->when(
                $sort === 'name_desc',
                fn ($query) => $query->orderByDesc('name')
            )
            ->when(
                $sort === 'level',
                fn ($query) => $query
                    ->orderBy('level')
                    ->orderBy('name')
            )
            ->when(
                $sort === 'category',
                fn ($query) => $query
                    ->orderBy('category')
                    ->orderBy('name')
            )
            ->when(
                $sort === 'recent',
                fn ($query) => $query->latest()
            )
            ->get();

        return view(
            'admin.government_ids.index',
            compact('governmentIds', 'sort')
        );
    }

    public function show(GovernmentId $governmentId)
    {
        $governmentId->load([
            'agency',
            'lastVerifier',
            'fees' => fn ($query) => $query->orderBy('sort_order'),
        ]);

        return view(
            'admin.government_ids.show',
            compact('governmentId')
        );
    }

    public function create()
    {
        $agencies = Agency::query()
            ->orderBy('name')
            ->get();

        return view(
            'admin.government_ids.create',
            compact('agencies')
        );
    }

    public function store(Request $request)
    {
        $validated = $this->validateGovernmentId($request);

        $feeRows = $this->prepareFeeRows(
            $validated['fees'] ?? []
        );

        $verifyToday = $request->boolean('verify_today');

        unset(
            $validated['fees'],
            $validated['verify_today']
        );

        /*
         * The new government_id_fees table
         * is now the source of truth for fees.
         *
         * These old structured single-fee columns
         * are retained in the database temporarily,
         * but should no longer be populated by the form.
         */
        unset(
            $validated['fee_type'],
            $validated['fee_min'],
            $validated['fee_max'],
            $validated['fee_currency'],
            $validated['fee_notes']
        );

        $validated = $this->prepareValidity($validated);

        $validated = $this->prepareProcessingTime(
            $validated
        );

        $governmentId = DB::transaction(function () use (
            $validated,
            $feeRows,
            $verifyToday
        ) {
            $governmentId = GovernmentId::create(
                $validated
            );

            $this->syncFees(
                $governmentId,
                $feeRows
            );

            if ($verifyToday) {
                $governmentId->last_verified_at = now();
                $governmentId->last_verified_by = auth()->id();
                $governmentId->save();
            }

            return $governmentId;
        });

        /*
         * After creating an ID,
         * go directly to its View page.
         */
        return redirect()
            ->route(
                'admin.government-ids.show',
                $governmentId
            )
            ->with(
                'success',
                'ID or credential added successfully.'
            );
    }

    public function edit(GovernmentId $governmentId)
    {
        $governmentId->load([
            'agency',
            'lastVerifier',
            'fees' => fn ($query) => $query->orderBy('sort_order'),
        ]);

        $agencies = Agency::query()
            ->orderBy('name')
            ->get();

        return view(
            'admin.government_ids.edit',
            compact(
                'governmentId',
                'agencies'
            )
        );
    }

    public function update(
        Request $request,
        GovernmentId $governmentId
    ) {
        $validated = $this->validateGovernmentId(
            $request
        );

        /*
         * Get the dynamic fee rows before removing
         * them from the main GovernmentId data.
         */
        $feeRows = $this->prepareFeeRows(
            $validated['fees'] ?? []
        );

        $verifyToday = $request->boolean(
            'verify_today'
        );

        /*
         * These values do not belong directly
         * in GovernmentId::update().
         */
        unset(
            $validated['fees'],
            $validated['verify_today']
        );

        /*
         * Stop using the old one-fee structured
         * fields as the source of truth.
         */
        unset(
            $validated['fee_type'],
            $validated['fee_min'],
            $validated['fee_max'],
            $validated['fee_currency'],
            $validated['fee_notes']
        );

        $validated = $this->prepareValidity(
            $validated
        );

        $validated = $this->prepareProcessingTime(
            $validated
        );

        DB::transaction(function () use (
            $governmentId,
            $validated,
            $feeRows,
            $verifyToday
        ) {
            /*
             * Update the main Government ID record.
             */
            $governmentId->update(
                $validated
            );

            /*
             * Save all multiple fee items.
             */
            $this->syncFees(
                $governmentId,
                $feeRows
            );

            /*
             * Only update verification information
             * when explicitly selected by researcher.
             */
            if ($verifyToday) {
                $governmentId->last_verified_at = now();
                $governmentId->last_verified_by = auth()->id();
                $governmentId->save();
            }
        });

        /*
         * IMPORTANT:
         * After Save Changes, take the researcher
         * directly to this ID's View page.
         */
        return redirect()
            ->route(
                'admin.government-ids.show',
                $governmentId
            )
            ->with(
                'success',
                'ID or credential updated successfully.'
            );
    }

    public function destroy(
        GovernmentId $governmentId
    ) {
        $governmentId->delete();

        return redirect()
            ->route(
                'admin.government-ids.index'
            )
            ->with(
                'success',
                'ID or credential deleted successfully.'
            );
    }

    private function validateGovernmentId(
        Request $request
    ): array {
        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'level' => [
                'nullable',
                'string',
                'max:100',
            ],

            'category' => [
                'nullable',
                'string',
                'max:100',
            ],

            'agency_id' => [
                'nullable',
                'integer',
                'exists:agencies,id',
            ],

            'purpose' => [
                'nullable',
                'string',
            ],

            'description' => [
                'nullable',
                'string',
            ],

            'eligibility' => [
                'nullable',
                'string',
            ],

            'requirements' => [
                'nullable',
                'string',
            ],

            'prerequisite_notes' => [
                'nullable',
                'string',
            ],

            /*
             * =====================================================
             * MULTIPLE FEES
             * =====================================================
             */

            'fees' => [
                'nullable',
                'array',
            ],

            'fees.*.label' => [
                'nullable',
                'string',
                'max:255',
            ],

            'fees.*.type' => [
                'nullable',
                'string',
                'in:fixed,range,free,varies',
            ],

            'fees.*.amount_min' => [
                'nullable',
                'numeric',
                'min:0',
                'max:99999999.99',
            ],

            'fees.*.amount_max' => [
                'nullable',
                'numeric',
                'min:0',
                'max:99999999.99',
            ],

            'fees.*.is_optional' => [
                'nullable',
                'boolean',
            ],

            'fees.*.notes' => [
                'nullable',
                'string',
            ],

            /*
             * Keep these old columns temporarily.
             * They are no longer used as the main
             * fee input system.
             */
            'fee' => [
                'nullable',
                'string',
                'max:255',
            ],

            'fee_type' => [
                'nullable',
                'string',
                'in:fixed,range,free,varies,not_applicable',
            ],

            'fee_min' => [
                'nullable',
                'numeric',
                'min:0',
                'max:99999999.99',
            ],

            'fee_max' => [
                'nullable',
                'numeric',
                'min:0',
                'max:99999999.99',
            ],

            'fee_currency' => [
                'nullable',
                'string',
                'in:PHP',
            ],

            'fee_notes' => [
                'nullable',
                'string',
            ],

            /*
             * =====================================================
             * APPLICATION PROCESS
             * =====================================================
             */

            'application_process' => [
                'nullable',
                'string',
            ],

            /*
             * =====================================================
             * PROCESSING TIME
             * =====================================================
             */

            'processing_time' => [
                'nullable',
                'string',
                'max:255',
            ],

            'processing_time_type' => [
                'nullable',
                'string',
                'in:fixed,range,same_day,varies,not_applicable',
            ],

            'processing_time_min' => [
                'nullable',
                'required_if:processing_time_type,fixed,range',
                'integer',
                'min:1',
                'max:999',
            ],

            'processing_time_max' => [
                'nullable',
                'required_if:processing_time_type,range',
                'integer',
                'min:1',
                'max:999',
                'gte:processing_time_min',
            ],

            'processing_time_unit' => [
                'nullable',
                'required_if:processing_time_type,fixed,range',
                'string',
                'in:calendar_day,working_day,week,month',
            ],

            /*
             * =====================================================
             * RENEWAL / REPLACEMENT
             * =====================================================
             */

            'renewal_process' => [
                'nullable',
                'string',
            ],

            'replacement_process' => [
                'nullable',
                'string',
            ],

            /*
             * =====================================================
             * VALIDITY
             * =====================================================
             */

            'validity' => [
                'nullable',
                'string',
                'max:255',
            ],

            'validity_type' => [
                'nullable',
                'string',
                'in:fixed,lifetime,no_expiration,until_age,varies,not_applicable',
            ],

            'validity_value' => [
                'nullable',
                'required_if:validity_type,fixed,until_age',
                'integer',
                'min:1',
                'max:999',
            ],

            'validity_unit' => [
                'nullable',
                'required_if:validity_type,fixed',
                'string',
                'in:day,week,month,year',
            ],

            /*
             * =====================================================
             * OFFICE INFORMATION
             * =====================================================
             */

            'office_location' => [
                'nullable',
                'string',
                'max:255',
            ],

            'office_hours' => [
                'nullable',
                'string',
                'max:255',
            ],

            /*
             * =====================================================
             * SOURCES
             * =====================================================
             */

            'official_link' => [
                'nullable',
                'url',
                'max:2048',
            ],

            'official_sources' => [
                'nullable',
                'string',
            ],

            /*
             * =====================================================
             * VERIFICATION
             * =====================================================
             */

            'verify_today' => [
                'nullable',
                'boolean',
            ],
        ]);
    }

    private function prepareValidity(
        array $validated
    ): array {
        $type =
            $validated['validity_type']
            ?? null;

        $value =
            $validated['validity_value']
            ?? null;

        $unit =
            $validated['validity_unit']
            ?? null;

        /*
         * FIXED PERIOD
         *
         * Example:
         * 10 years
         */
        if ($type === 'fixed') {
            if (!$value || !$unit) {
                $validated['validity'] = null;

                return $validated;
            }

            $unitLabel =
                $value == 1
                    ? $unit
                    : $unit . 's';

            $validated['validity'] =
                $value
                . ' '
                . $unitLabel;

            return $validated;
        }

        /*
         * UNTIL SPECIFIC AGE
         *
         * Example:
         * Until age 60
         */
        if ($type === 'until_age') {
            $validated['validity_unit'] = null;

            $validated['validity'] =
                $value
                    ? 'Until age ' . $value
                    : null;

            return $validated;
        }

        /*
         * These types don't require
         * a number or unit.
         */
        $validated['validity_value'] = null;
        $validated['validity_unit'] = null;

        $validated['validity'] = match ($type) {
            'lifetime' =>
                'Lifetime',

            'no_expiration' =>
                'No expiration',

            'varies' =>
                'Varies',

            'not_applicable' =>
                'Not applicable',

            default =>
                null,
        };

        return $validated;
    }

    private function prepareProcessingTime(
        array $validated
    ): array {
        $type =
            $validated['processing_time_type']
            ?? null;

        $min =
            $validated['processing_time_min']
            ?? null;

        $max =
            $validated['processing_time_max']
            ?? null;

        $unit =
            $validated['processing_time_unit']
            ?? null;

        $unitLabels = [
            'calendar_day' =>
                'calendar day',

            'working_day' =>
                'working day',

            'week' =>
                'week',

            'month' =>
                'month',
        ];

        /*
         * FIXED PROCESSING TIME
         *
         * Example:
         * 7 working days
         */
        if ($type === 'fixed') {
            $validated['processing_time_max'] = null;

            if (!$min || !$unit) {
                $validated['processing_time'] = null;

                return $validated;
            }

            $label =
                $unitLabels[$unit]
                ?? $unit;

            if ($min != 1) {
                $label .= 's';
            }

            $validated['processing_time'] =
                $min
                . ' '
                . $label;

            return $validated;
        }

        /*
         * RANGE
         *
         * Example:
         * 6–12 working days
         */
        if ($type === 'range') {
            if (!$min || !$max || !$unit) {
                $validated['processing_time'] = null;

                return $validated;
            }

            $label =
                $unitLabels[$unit]
                ?? $unit;

            if ($max != 1) {
                $label .= 's';
            }

            $validated['processing_time'] =
                $min
                . '–'
                . $max
                . ' '
                . $label;

            return $validated;
        }

        /*
         * These types do not need
         * number or unit values.
         */
        $validated['processing_time_min'] = null;
        $validated['processing_time_max'] = null;
        $validated['processing_time_unit'] = null;

        $validated['processing_time'] =
            match ($type) {
                'same_day' =>
                    'Same day',

                'varies' =>
                    'Varies',

                'not_applicable' =>
                    'Not applicable',

                default =>
                    null,
            };

        return $validated;
    }

    /*
     * =========================================================
     * MULTIPLE FEE PREPARATION
     * =========================================================
     */

    private function prepareFeeRows(
        array $rows
    ): array {
        $prepared = [];

        foreach ($rows as $index => $row) {
            $label = trim(
                (string) (
                    $row['label']
                    ?? ''
                )
            );

            $type =
                $row['type']
                ?? null;

            $amountMin =
                $row['amount_min']
                ?? null;

            $amountMax =
                $row['amount_max']
                ?? null;

            $notes = trim(
                (string) (
                    $row['notes']
                    ?? ''
                )
            );

            /*
             * Ignore completely empty fee rows.
             */
            $isCompletelyEmpty =
                $label === ''
                && !$type
                && (
                    $amountMin === null
                    || $amountMin === ''
                )
                && (
                    $amountMax === null
                    || $amountMax === ''
                )
                && $notes === '';

            if ($isCompletelyEmpty) {
                continue;
            }

            /*
             * Every real fee must have a name.
             */
            if ($label === '') {
                throw ValidationException::withMessages([
                    "fees.$index.label" =>
                        'The fee name is required.',
                ]);
            }

            /*
             * Every real fee must have a type.
             */
            if (!$type) {
                throw ValidationException::withMessages([
                    "fees.$index.type" =>
                        'Please select a fee type.',
                ]);
            }

            /*
             * FIXED FEE
             *
             * Example:
             * Passport Fee = ₱950
             */
            if ($type === 'fixed') {
                if (
                    $amountMin === null
                    || $amountMin === ''
                ) {
                    throw ValidationException::withMessages([
                        "fees.$index.amount_min" =>
                            'Enter the fee amount.',
                    ]);
                }

                $amountMax = null;
            }

            /*
             * RANGE
             *
             * Example:
             * Delivery = ₱100–₱250
             */
            if ($type === 'range') {
                if (
                    $amountMin === null
                    || $amountMin === ''
                    || $amountMax === null
                    || $amountMax === ''
                ) {
                    throw ValidationException::withMessages([
                        "fees.$index.amount_min" =>
                            'Enter both minimum and maximum amounts.',
                    ]);
                }

                if (
                    (float) $amountMax
                    <
                    (float) $amountMin
                ) {
                    throw ValidationException::withMessages([
                        "fees.$index.amount_max" =>
                            'The maximum amount must be greater than or equal to the minimum amount.',
                    ]);
                }
            }

            /*
             * FREE or VARIES
             * do not need monetary values.
             */
            if (
                in_array(
                    $type,
                    [
                        'free',
                        'varies',
                    ],
                    true
                )
            ) {
                $amountMin = null;
                $amountMax = null;
            }

            $prepared[] = [
                'label' =>
                    $label,

                'type' =>
                    $type,

                'amount_min' =>
                    $amountMin === ''
                        ? null
                        : $amountMin,

                'amount_max' =>
                    $amountMax === ''
                        ? null
                        : $amountMax,

                'currency' =>
                    'PHP',

                'is_optional' =>
                    filter_var(
                        $row['is_optional']
                            ?? false,
                        FILTER_VALIDATE_BOOLEAN
                    ),

                'notes' =>
                    $notes !== ''
                        ? $notes
                        : null,
            ];
        }

        return $prepared;
    }

    /*
     * =========================================================
     * SAVE MULTIPLE FEES
     * =========================================================
     */

    private function syncFees(
        GovernmentId $governmentId,
        array $feeRows
    ): void {
        /*
         * Replace current fee rows with
         * the latest submitted list.
         *
         * This is simple and appropriate
         * for the current CMS workflow.
         */
        $governmentId
            ->fees()
            ->delete();

        foreach ($feeRows as $index => $fee) {
            $governmentId
                ->fees()
                ->create([
                    'label' =>
                        $fee['label'],

                    'type' =>
                        $fee['type'],

                    'amount_min' =>
                        $fee['amount_min'],

                    'amount_max' =>
                        $fee['amount_max'],

                    'currency' =>
                        $fee['currency'],

                    'is_optional' =>
                        $fee['is_optional'],

                    'notes' =>
                        $fee['notes'],

                    'sort_order' =>
                        $index,
                ]);
        }

        /*
         * -----------------------------------------------------
         * LEGACY READABLE FEE SUMMARY
         * -----------------------------------------------------
         *
         * Flutter/API currently expects one readable
         * `fee` value.
         *
         * Example:
         *
         * Passport Fee: ₱950.00;
         * Delivery Fee: ₱250.00
         */

        $summary = collect($feeRows)
            ->map(function ($fee) {
                $amount = match ($fee['type']) {
                    'fixed' =>
                        '₱'
                        . number_format(
                            (float) $fee['amount_min'],
                            2
                        ),

                    'range' =>
                        '₱'
                        . number_format(
                            (float) $fee['amount_min'],
                            2
                        )
                        . '–₱'
                        . number_format(
                            (float) $fee['amount_max'],
                            2
                        ),

                    'free' =>
                        'Free',

                    'varies' =>
                        'Varies',

                    default =>
                        '',
                };

                return
                    $fee['label']
                    . ': '
                    . $amount;
            })
            ->implode('; ');

        /*
         * Keep the readable compatibility field.
         */
        $governmentId->fee =
            $summary !== ''
                ? $summary
                : null;

        /*
         * Clear the OLD structured single-fee fields
         * so they don't contain stale/conflicting data.
         */
        $governmentId->fee_type = null;
        $governmentId->fee_min = null;
        $governmentId->fee_max = null;
        $governmentId->fee_currency = null;
        $governmentId->fee_notes = null;

        $governmentId->save();
    }
}