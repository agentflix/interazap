<?php

declare(strict_types=1);

return [
    'contact_import' => [
        'storage_path' => env('CRM_CONTACT_IMPORT_STORAGE_PATH', 'imports/contacts'),
        'sample_rows' => (int) env('CRM_CONTACT_IMPORT_SAMPLE_ROWS', 5),
        'inline_max_rows' => (int) env('CRM_CONTACT_IMPORT_INLINE_MAX_ROWS', 1000),
        'export_filename' => env('CRM_CONTACT_EXPORT_FILENAME', 'contacts.csv'),
    ],
];
