<?php

namespace Database\Seeders;

use App\Models\Document;
use App\Models\GovernmentId;
use Illuminate\Database\Seeder;

class ContentInventorySeeder extends Seeder
{
    public function run(): void
    {
        $governmentIds = [
            [
                'name' => 'National ID (PhilSys)',
                'level' => 'National',
                'category' => 'Identity ID',
            ],
            [
                'name' => 'Passport',
                'level' => 'National',
                'category' => 'Travel Document',
            ],
            [
                'name' => 'Postal ID',
                'level' => 'National',
                'category' => 'Identity ID',
            ],
            [
                'name' => 'MySSS Card',
                'level' => 'National',
                'category' => 'Identity ID',
            ],
            [
                'name' => 'GSIS UMID Card',
                'level' => 'National',
                'category' => 'Identity ID',
            ],
            [
                'name' => 'Professional Regulation Commission (PRC) ID',
                'level' => 'National',
                'category' => 'Professional Credential',
            ],
            [
                'name' => 'Student Permit',
                'level' => 'National',
                'category' => 'Driving Credential',
            ],
            [
                'name' => 'Non-Professional Driver\'s License',
                'level' => 'National',
                'category' => 'Driving Credential',
            ],
            [
                'name' => 'Professional Driver\'s License',
                'level' => 'National',
                'category' => 'Driving Credential',
            ],
            [
                'name' => 'TIN ID',
                'level' => 'National',
                'category' => 'Tax ID',
            ],
            [
                'name' => 'OWWA E-Card',
                'level' => 'National',
                'category' => 'Sector-Specific ID',
            ],
            [
                'name' => 'Senior Citizen ID',
                'level' => 'Municipal / LGU',
                'category' => 'Sector-Specific ID',
            ],
            [
                'name' => 'Persons with Disability (PWD) ID',
                'level' => 'Municipal / LGU',
                'category' => 'Sector-Specific ID',
            ],
            [
                'name' => 'Solo Parent ID',
                'level' => 'Municipal / LGU',
                'category' => 'Sector-Specific ID',
            ],
        ];

        $documents = [
            [
                'name' => 'Community Tax Certificate (Cedula)',
                'level' => 'Municipal / LGU',
                'category' => 'Tax / Property',
            ],
            [
                'name' => 'Barangay First-Time Jobseeker Certification',
                'level' => 'Barangay',
                'category' => 'Certification',
            ],
            [
                'name' => 'Barangay Business Clearance',
                'level' => 'Barangay',
                'category' => 'Business / Permit',
            ],
            [
                'name' => 'Barangay Clearance',
                'level' => 'Barangay',
                'category' => 'Clearance',
            ],
            [
                'name' => 'Certificate of Residency',
                'level' => 'Barangay',
                'category' => 'Certification',
            ],
            [
                'name' => 'Certificate of Indigency',
                'level' => 'Barangay',
                'category' => 'Certification',
            ],
            [
                'name' => 'Certificate of Good Moral Character',
                'level' => 'Barangay',
                'category' => 'Certification',
            ],
            [
                'name' => 'Barangay Blotter Report',
                'level' => 'Barangay',
                'category' => 'Record / Report',
            ],
            [
                'name' => 'Local Birth Certificate Copy',
                'level' => 'Municipal / LGU',
                'category' => 'Civil Registry',
            ],
            [
                'name' => 'Local Marriage Certificate Copy',
                'level' => 'Municipal / LGU',
                'category' => 'Civil Registry',
            ],
            [
                'name' => 'Local Death Certificate Copy',
                'level' => 'Municipal / LGU',
                'category' => 'Civil Registry',
            ],
            [
                'name' => 'Marriage License',
                'level' => 'Municipal / LGU',
                'category' => 'Civil Registry',
            ],
            [
                'name' => 'Real Property Tax Clearance',
                'level' => 'Municipal / LGU',
                'category' => 'Tax / Property',
            ],
            [
                'name' => 'Tax Declaration',
                'level' => 'Municipal / LGU',
                'category' => 'Tax / Property',
            ],
            [
                'name' => 'National Police Clearance',
                'level' => 'National',
                'category' => 'Clearance',
            ],
            [
                'name' => 'NBI Clearance',
                'level' => 'National',
                'category' => 'Clearance',
            ],
            [
                'name' => 'PSA Birth Certificate',
                'level' => 'National',
                'category' => 'Civil Registry',
            ],
            [
                'name' => 'PSA Marriage Certificate',
                'level' => 'National',
                'category' => 'Civil Registry',
            ],
            [
                'name' => 'PSA Death Certificate',
                'level' => 'National',
                'category' => 'Civil Registry',
            ],
            [
                'name' => 'PSA CENOMAR',
                'level' => 'National',
                'category' => 'Civil Registry',
            ],
            [
                'name' => 'Income Tax Return (ITR) / BIR Tax Filing',
                'level' => 'National',
                'category' => 'Tax / Property',
            ],
            [
                'name' => 'Voter\'s Certification',
                'level' => 'National',
                'category' => 'Certification',
            ],
        ];

        foreach ($governmentIds as $item) {
            GovernmentId::firstOrCreate(
                ['name' => $item['name']],
                [
                    'level' => $item['level'],
                    'category' => $item['category'],
                ]
            );
        }

        foreach ($documents as $item) {
            Document::firstOrCreate(
                ['name' => $item['name']],
                [
                    'level' => $item['level'],
                    'category' => $item['category'],
                ]
            );
        }
    }
}