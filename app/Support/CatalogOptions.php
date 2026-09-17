<?php

namespace App\Support;

class CatalogOptions
{
    public const LEVELS = [
        'barangay' => 'Barangay',
        'municipal' => 'Municipal',
        'national' => 'National',
        'local' => 'Local (municipal / barangay)',
    ];

    public const RESEARCH_STAGES = [
        'draft' => 'Draft', 'needs_research' => 'Needs research', 'in_review' => 'Research in progress', 'verified' => 'Verified content',
    ];

    public const AVAILABILITY = [
        'unknown' => 'Issuance not confirmed', 'available' => 'New issuance confirmed',
        'temporarily_unavailable' => 'Temporarily unavailable', 'legacy' => 'Legacy credential', 'inactive' => 'Inactive / archived',
    ];

    public const RECORD_TYPES = [
        'id' => 'ID', 'certificate' => 'Certificate', 'clearance' => 'Clearance', 'license' => 'License',
        'permit' => 'Permit', 'civil_record' => 'Civil record', 'tax_record' => 'Tax record',
        'report' => 'Report', 'credential' => 'Credential', 'other' => 'Other / not classified',
    ];
}
