<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GovernmentId;
use Illuminate\Http\JsonResponse;

class GovernmentIdController extends Controller
{
    public function index(): JsonResponse
    {
        $governmentIds = GovernmentId::query()
            ->with([
                'agency',
                'fees' => fn ($query) => $query
                    ->orderBy('sort_order')
                    ->orderBy('id'),
            ])
            ->orderBy('name')
            ->get()
            ->map(function (GovernmentId $governmentId) {
                return [
                    'id' => $governmentId->id,
                    'name' => $governmentId->name,

                    'level' => $governmentId->level,
                    'category' => $governmentId->category,

                    'agency' => $governmentId->agency
                        ? [
                            'id' => $governmentId->agency->id,
                            'name' => $governmentId->agency->name,
                            'acronym' => $governmentId->agency->acronym,
                        ]
                        : null,

                    /*
                     * Temporary compatibility for
                     * the current Flutter UI.
                     */
                    'issued_by' =>
                        $governmentId->agency?->name,

                    'purpose' => $governmentId->purpose,
                    'description' => $governmentId->description,

                    'eligibility' => $governmentId->eligibility,
                    'requirements' => $governmentId->requirements,

                    /*
                     * Legacy readable fee summary.
                     *
                     * Keep this while Flutter still
                     * expects a single fee string.
                     */
                    'fee' => $governmentId->fee,

                    /*
                     * New structured multiple fees.
                     */
                    'fees' => $this->formatFees(
                        $governmentId
                    ),

                    /*
                     * Readable processing time.
                     */
                    'processing_time' =>
                        $governmentId->processing_time,

                    /*
                     * Structured processing time.
                     */
                    'processing_time_type' =>
                        $governmentId->processing_time_type,

                    'processing_time_min' =>
                        $governmentId->processing_time_min,

                    'processing_time_max' =>
                        $governmentId->processing_time_max,

                    'processing_time_unit' =>
                        $governmentId->processing_time_unit,

                    /*
                     * Readable validity.
                     */
                    'validity' =>
                        $governmentId->validity,

                    /*
                     * Structured validity.
                     */
                    'validity_type' =>
                        $governmentId->validity_type,

                    'validity_value' =>
                        $governmentId->validity_value,

                    'validity_unit' =>
                        $governmentId->validity_unit,

                    'office_location' =>
                        $governmentId->office_location,

                    'last_verified_at' =>
                        $governmentId
                            ->last_verified_at
                            ?->toISOString(),
                ];
            })
            ->values();

        return response()->json([
            'data' => $governmentIds,
        ]);
    }


    public function show(
        GovernmentId $governmentId
    ): JsonResponse {
        $governmentId->load([
            'agency',
            'fees' => fn ($query) => $query
                ->orderBy('sort_order')
                ->orderBy('id'),
        ]);

        return response()->json([
            'data' => [
                'id' => $governmentId->id,
                'name' => $governmentId->name,

                'level' => $governmentId->level,
                'category' => $governmentId->category,

                /*
                 * Issuing agency.
                 */
                'agency' => $governmentId->agency
                    ? [
                        'id' =>
                            $governmentId->agency->id,

                        'name' =>
                            $governmentId->agency->name,

                        'acronym' =>
                            $governmentId->agency->acronym,

                        'official_website' =>
                            $governmentId
                                ->agency
                                ->official_website,
                    ]
                    : null,

                /*
                 * Keep for backward compatibility
                 * with the current Flutter UI.
                 */
                'issued_by' =>
                    $governmentId->agency?->name,

                /*
                 * Basic research information.
                 */
                'purpose' =>
                    $governmentId->purpose,

                'description' =>
                    $governmentId->description,

                /*
                 * Requirements and eligibility.
                 */
                'eligibility' =>
                    $governmentId->eligibility,

                'requirements' =>
                    $governmentId->requirements,

                'prerequisite_notes' =>
                    $governmentId->prerequisite_notes,

                /*
                 * =================================================
                 * FEES
                 * =================================================
                 */

                /*
                 * Legacy readable summary.
                 *
                 * Example:
                 * Passport Fee: ₱950.00;
                 * Delivery Fee: ₱250.00
                 */
                'fee' =>
                    $governmentId->fee,

                /*
                 * New structured fee list.
                 */
                'fees' =>
                    $this->formatFees(
                        $governmentId
                    ),

                /*
                 * =================================================
                 * APPLICATION GUIDE
                 * =================================================
                 */

                'application_process' =>
                    $governmentId->application_process,

                /*
                 * Readable processing time.
                 *
                 * Example:
                 * 6–12 working days
                 */
                'processing_time' =>
                    $governmentId->processing_time,

                /*
                 * Structured processing time.
                 */
                'processing_time_type' =>
                    $governmentId->processing_time_type,

                'processing_time_min' =>
                    $governmentId->processing_time_min,

                'processing_time_max' =>
                    $governmentId->processing_time_max,

                'processing_time_unit' =>
                    $governmentId->processing_time_unit,

                'renewal_process' =>
                    $governmentId->renewal_process,

                'replacement_process' =>
                    $governmentId->replacement_process,

                /*
                 * =================================================
                 * VALIDITY
                 * =================================================
                 */

                /*
                 * Readable validity.
                 *
                 * Example:
                 * 10 years
                 */
                'validity' =>
                    $governmentId->validity,

                /*
                 * Structured validity.
                 */
                'validity_type' =>
                    $governmentId->validity_type,

                'validity_value' =>
                    $governmentId->validity_value,

                'validity_unit' =>
                    $governmentId->validity_unit,

                /*
                 * =================================================
                 * OFFICE INFORMATION
                 * =================================================
                 */

                'office_location' =>
                    $governmentId->office_location,

                'office_hours' =>
                    $governmentId->office_hours,

                /*
                 * =================================================
                 * SOURCES
                 * =================================================
                 */

                'official_link' =>
                    $governmentId->official_link,

                'official_sources' =>
                    $governmentId->official_sources,

                /*
                 * =================================================
                 * VERIFICATION
                 * =================================================
                 */

                'last_verified_at' =>
                    $governmentId
                        ->last_verified_at
                        ?->toISOString(),
            ],
        ]);
    }


    /*
     * =========================================================
     * FORMAT MULTIPLE FEES FOR API
     * =========================================================
     */

    private function formatFees(
        GovernmentId $governmentId
    ): array {
        return $governmentId
            ->fees
            ->map(function ($fee) {
                return [
                    'id' =>
                        $fee->id,

                    'label' =>
                        $fee->label,

                    'type' =>
                        $fee->type,

                    'amount_min' =>
                        $fee->amount_min,

                    'amount_max' =>
                        $fee->amount_max,

                    'currency' =>
                        $fee->currency,

                    'is_optional' =>
                        (bool) $fee->is_optional,

                    'notes' =>
                        $fee->notes,

                    /*
                     * Convenient readable value for
                     * Flutter or other API clients.
                     */
                    'display_amount' =>
                        $this->formatFeeAmount($fee),
                ];
            })
            ->values()
            ->all();
    }


    /*
     * Generate one readable amount for each fee.
     *
     * Examples:
     *
     * fixed  -> ₱950.00
     * range  -> ₱100.00–₱250.00
     * free   -> Free
     * varies -> Varies
     */
    private function formatFeeAmount(
        $fee
    ): string {
        return match ($fee->type) {
            'fixed' =>
                '₱'
                . number_format(
                    (float) $fee->amount_min,
                    2
                ),

            'range' =>
                '₱'
                . number_format(
                    (float) $fee->amount_min,
                    2
                )
                . '–₱'
                . number_format(
                    (float) $fee->amount_max,
                    2
                ),

            'free' =>
                'Free',

            'varies' =>
                'Varies',

            default =>
                'Not available',
        };
    }
}