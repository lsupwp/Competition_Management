<?php
// Route: /
$title = 'Team Competition Management';

// Call hello component
include_once __DIR__ . '/../api/hello.php';

ob_start();
?>
<div class="hero min-h-[60vh]">
    <div class="hero-content text-center">
        <div class="max-w-md">
            <h1 class="text-5xl font-bold">Team Competition</h1>
            <p class="py-6">ระบบจัดการงานแข่งของทีม - สร้างทีม ลงงาน ติดตามสถานะ</p>

            <div class="alert alert-info mt-4">
                <span><?= htmlspecialchars($helloMessage) ?></span>
            </div>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-8">
    <?php
    $cardTitle = 'สร้างทีม';
    $cardBody = 'รวมทีม สร้างทีมแข่งกับเพื่อน';
    $cardActions = '<button class="btn btn-primary btn-sm">สร้าง</button>';
    include __DIR__ . '/../templates/components/card.php';
    ?>

    <?php
    $cardTitle = 'ลงงาน';
    $cardBody = 'เลือกงานแข่งที่สนใจ ลงทะเบียน';
    $cardActions = '<button class="btn btn-secondary btn-sm">ดูงาน</button>';
    include __DIR__ . '/../templates/components/card.php';
    ?>

    <?php
    $cardTitle = 'ติดตาม';
    $cardBody = 'ดูสถานะงาน ผลการแข่งขัน';
    $cardActions = '<button class="btn btn-accent btn-sm">ติดตาม</button>';
    include __DIR__ . '/../templates/components/card.php';
    ?>
</div>
<?php
$content = ob_get_clean();
include_once __DIR__ . '/../templates/layout.php';
