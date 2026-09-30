<?php

return [
    'direction' => 'ltr',
    'skip_to_content' => [
        'label' => 'Lewati ke konten utama',
    ],
    'actions' => [
        'billing' => ['label' => 'Kelola langganan'],
        'logout' => ['label' => 'Keluar'],
        'open_database_notifications' => [
            'label' => 'Notifikasi',
            'label_with_unread_count' => '{1} Notifikasi, :count belum dibaca|[2,*] Notifikasi, :count belum dibaca',
        ],
        'open_user_menu' => ['label' => 'Menu pengguna'],
        'sidebar' => [
            'collapse' => ['label' => 'Tutup menu samping'],
            'expand' => ['label' => 'Buka menu samping'],
        ],
        'theme_switcher' => [
            'label' => 'Tema',
            'dark' => ['label' => 'Gunakan tema gelap'],
            'light' => ['label' => 'Gunakan tema terang'],
            'system' => ['label' => 'Ikuti tema perangkat'],
        ],
    ],
    'navigation' => ['label' => 'Navigasi menu samping'],
    'topbar' => ['label' => 'Bilah atas'],
    'avatar' => ['alt' => 'Avatar :name'],
    'logo' => ['alt' => 'Logo :name'],
    'tenant_menu' => [
        'search_field' => [
            'label' => 'Pencarian unit',
            'placeholder' => 'Cari',
        ],
    ],
];
