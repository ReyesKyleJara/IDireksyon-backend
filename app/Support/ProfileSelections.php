<?php

namespace App\Support;

use InvalidArgumentException;

/** Explicit profile choices by Government ID, separate from shared applicant facts. */
final readonly class ProfileSelections
{
    /** @var array<int, int> */
    private array $selections;

    public function __construct(array $selections = [])
    {
        if (count($selections) > 500) {
            throw new InvalidArgumentException('At most 500 profile selections may be supplied.');
        }
        $normalized = [];
        foreach ($selections as $governmentId => $profileId) {
            // Reject aliases, signs, decimals, zero, and integer overflow before converting keys.
            $key = (string) $governmentId;
            if (! preg_match('/^[1-9][0-9]*$/D', $key) || (string) (int) $key !== $key) {
                throw new InvalidArgumentException('Profile selection keys must be canonical positive Government ID integers.');
            }
            if (! is_int($profileId) || $profileId < 1) {
                throw new InvalidArgumentException('Selected profile values must be positive JSON integers.');
            }
            $normalized[(int) $governmentId] = $profileId;
        }
        ksort($normalized, SORT_NUMERIC);
        $this->selections = $normalized;
    }

    public function forGovernmentId(int $governmentId): ?int
    {
        return $this->selections[$governmentId] ?? null;
    }

    /** @return array<int, int> */
    public function all(): array
    {
        return $this->selections;
    }

    public function withSelection(int $governmentId, int $profileId): self
    {
        $existing = $this->forGovernmentId($governmentId);
        if ($existing !== null && $existing !== $profileId) {
            throw new InvalidArgumentException('Conflicting profile selections for the same Government ID.');
        }

        $updated = $this->selections;
        $updated[$governmentId] = $profileId;

        return new self($updated);
    }
}
