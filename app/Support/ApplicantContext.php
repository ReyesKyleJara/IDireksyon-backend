<?php

namespace App\Support;

use App\Models\GovernmentIdRequirementSet;
use InvalidArgumentException;

/** Applicant facts only; missing facts remain unknown rather than assumed. */
final readonly class ApplicantContext
{
    public ?int $age;

    public ?string $applicationType;

    public array $governmentIdIds;

    public array $documentIds;

    public function __construct(?int $age = null, ?string $applicationType = null, array $governmentIdIds = [], array $documentIds = [])
    {
        if ($age !== null && ($age < 0 || $age > 65535)) {
            throw new InvalidArgumentException('Age must be between 0 and 65535.');
        }
        if ($applicationType !== null && ! in_array($applicationType, array_keys(GovernmentIdRequirementSet::APPLICATION_TYPES), true)) {
            throw new InvalidArgumentException('Unsupported application type.');
        }

        $this->age = $age;
        $this->applicationType = $applicationType;
        $this->governmentIdIds = self::normalizeIds($governmentIdIds);
        $this->documentIds = self::normalizeIds($documentIds);
    }

    public function hasGovernmentId(int $id): bool
    {
        return in_array($id, $this->governmentIdIds, true);
    }

    public function hasDocument(int $id): bool
    {
        return in_array($id, $this->documentIds, true);
    }

    private static function normalizeIds(array $ids): array
    {
        foreach ($ids as $id) {
            if (! is_int($id) || $id < 1) {
                throw new InvalidArgumentException('Owned references must be positive integer IDs.');
            }
        }
        $ids = array_values(array_unique($ids));
        sort($ids, SORT_NUMERIC);

        return $ids;
    }
}
